<?php

namespace App\Services\WhatsApp;

use App\Events\SobresAlcanzados;
use App\Models\AvisoWhatsApp;
use App\Models\Culto;
use App\Models\Sobre;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Crea los avisos de WhatsApp para los líderes de la iglesia (un aviso por destinatario y evento).
 *
 * Destinatarios: usuarios de la iglesia cuyo rol está en config('whatsapp.avisos.roles')
 * (admin y tesorero por defecto) y que tienen un teléfono válido. Con el driver meta (y
 * credenciales) cada aviso sale solo; con link queda pendiente en el panel /avisos.
 *
 * Con el driver callmebot cada líder decide por sí mismo: quien activó WhatsApp en su perfil recibe el
 * aviso solo; quien no, lo tiene pendiente en el panel para mandarlo con un toque (wa.me).
 *
 * QE-009: el envío automático NUNCA ocurre en la petición que crea el aviso. La tabla avisos_whatsapp
 * es la cola y whatsapp:enviar-pendientes (cada minuto, planificador) la vacía. Un aviso que no pudo
 * salir queda pendiente en el panel con su último error: nunca se pierde.
 */
class AvisosWhatsAppService
{
    /** Intentos de envío automático antes de dejar el aviso para mandarlo a mano. */
    public const INTENTOS_MAXIMOS = 3;

    /** Minutos de espera tras el 1.º y el 2.º intento fallido. */
    private const ESPERAS_MINUTOS = [1, 5, 15];

    /** Pasado este tiempo un aviso ya no sale solo (p. ej. el cron estuvo caído días). */
    public const VIGENCIA_HORAS = 12;

    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    /**
     * Usuarios que deben recibir los avisos de la iglesia, con su teléfono ya normalizado.
     * Se descartan los teléfonos que no sirven y los repetidos (una persona con dos usuarios
     * recibe un solo aviso).
     *
     * El rol que cuenta es el mismo que usa CheckRole: el del TenantRole si el usuario lo tiene
     * y, si no, la columna `rol` de siempre.
     *
     * @return Collection<int, array{usuario: User, telefono: string}>
     */
    public function destinatarios(?int $tenantId): Collection
    {
        $roles = (array) config('whatsapp.avisos.roles', []);

        if ($roles === []) {
            return collect();
        }

        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_super_admin', false)
            ->whereNotNull('telefono')
            ->where('telefono', '<>', '')
            ->where(function ($q) use ($roles) {
                $q->where(fn ($legacy) => $legacy->whereNull('tenant_role_id')->whereIn('rol', $roles))
                    ->orWhereHas('tenantRole', fn ($rol) => $rol->whereIn('slug', $roles));
            })
            ->orderBy('id')
            ->get()
            ->map(fn (User $usuario) => ['usuario' => $usuario, 'telefono' => WhatsAppService::normalizarTelefono($usuario->telefono)])
            ->filter(fn (array $d) => $d['telefono'] !== null)
            ->unique('telefono')
            ->values();
    }

    /**
     * Avisos de «se creó un culto».
     *
     * @return Collection<int, AvisoWhatsApp>
     */
    public function cultoCreado(Culto $culto): Collection
    {
        $enlace = $this->enlaceRecuento($culto);

        return $this->crear(
            AvisoWhatsApp::EVENTO_CULTO_CREADO,
            $culto,
            MensajesWhatsApp::tituloCultoCreado($culto),
            fn (string $nombre, ?Tenant $iglesia) => MensajesWhatsApp::cultoCreado($culto, $nombre, $enlace, $iglesia),
            $enlace,
        );
    }

    /**
     * Avisos de «el culto llegó a N sobres».
     *
     * @return Collection<int, AvisoWhatsApp>
     */
    public function sobresAlcanzados(Culto $culto, int $cantidad): Collection
    {
        $enlace = $this->enlaceRecuento($culto);

        return $this->crear(
            AvisoWhatsApp::EVENTO_SOBRES_ALCANZADOS,
            $culto,
            MensajesWhatsApp::tituloSobresAlcanzados($culto, $cantidad),
            fn (string $nombre, ?Tenant $iglesia) => MensajesWhatsApp::sobresAlcanzados($culto, $cantidad, $nombre, $enlace, $iglesia),
            $enlace,
        );
    }

    /**
     * Se llama después de registrar un sobre. Si el culto ya tiene la cantidad configurada de sobres
     * y todavía no avisó, dispara SobresAlcanzados. Devuelve si lo disparó.
     *
     * Es una sola vez por culto: el culto se marca con un UPDATE condicional (aviso_sobres_at IS NULL),
     * así que si dos personas registran a la vez solo una gana, y con «>=» un salto de 9 a 11 tampoco
     * lo pierde. Nunca interrumpe el registro del sobre: un error aquí se reporta y se sigue.
     */
    public function sobreRegistrado(Culto $culto): bool
    {
        try {
            $umbral = max(1, (int) config('whatsapp.avisos.sobres_umbral', 10));
            $cantidad = Sobre::where('culto_id', $culto->id)->count();

            if ($cantidad < $umbral) {
                return false;
            }

            $reclamado = DB::table('cultos')
                ->where('id', $culto->id)
                ->whereNull('aviso_sobres_at')
                ->update(['aviso_sobres_at' => now()]);

            if ($reclamado === 0) {
                return false;
            }

            SobresAlcanzados::dispatch($culto, $cantidad);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * @param  callable(string, ?Tenant): string  $mensaje  arma el texto para un destinatario (nombre, iglesia)
     * @return Collection<int, AvisoWhatsApp>
     */
    private function crear(string $evento, Culto $culto, string $titulo, callable $mensaje, ?string $enlace): Collection
    {
        $iglesia = $culto->tenant_id ? Tenant::find($culto->tenant_id) : null;

        $avisos = $this->destinatarios($culto->tenant_id)->map(function (array $destinatario) use ($evento, $culto, $titulo, $mensaje, $enlace, $iglesia) {
            /** @var User $usuario */
            $usuario = $destinatario['usuario'];

            return AvisoWhatsApp::create([
                'tenant_id' => $culto->tenant_id,
                'evento' => $evento,
                'titulo' => $titulo,
                'mensaje' => $mensaje($usuario->name, $iglesia),
                'destinatario_nombre' => $usuario->name,
                'destinatario_telefono' => $destinatario['telefono'],
                'user_id' => $usuario->id,
                'enlace' => $enlace,
                'estado' => AvisoWhatsApp::PENDIENTE,
            ]);
        });

        $this->marcarEnvioAutomatico($avisos);

        return $avisos;
    }

    /**
     * QE-009: aquí NO se envía nada. Crear un culto o guardar el 10.º sobre no puede esperar a un
     * tercero (Hostinger corta a los 10 s y no hay workers de colas). Solo se marca qué avisos puede
     * mandar el sistema solo (meta con credenciales, o callmebot a quien activó su WhatsApp); el
     * comando whatsapp:enviar-pendientes, que corre cada minuto desde el planificador, los envía.
     * Los demás quedan pendientes en el panel /avisos para mandarlos con un toque, como siempre.
     *
     * @param  Collection<int, AvisoWhatsApp>  $avisos
     */
    private function marcarEnvioAutomatico(Collection $avisos): void
    {
        foreach ($avisos as $aviso) {
            try {
                $usuario = $aviso->user_id ? User::find($aviso->user_id) : null;

                if ($usuario !== null && $this->whatsapp->puedeEnviarA($usuario)) {
                    $aviso->forceFill(['envio_automatico' => true])->save();
                }
            } catch (Throwable $e) {
                // Si no se pudo decidir, el aviso queda manual en el panel: nunca se pierde.
                report($e);
            }
        }
    }

    /**
     * Envía los avisos automáticos que esperan su turno (lo llama whatsapp:enviar-pendientes, fuera de
     * cualquier petición). Cada aviso se reclama con un UPDATE condicional antes de enviarlo, así que
     * dos corridas a la vez nunca lo mandan dos veces. Reintentos acotados: tras INTENTOS_MAXIMOS el
     * aviso deja de ser automático y queda pendiente en el panel con su último error. Un aviso que
     * esperó más de VIGENCIA_HORAS (el cron estuvo caído) no sale solo: ya no sirve como aviso.
     *
     * @return array{enviados: int, fallidos: int, vencidos: int}
     */
    public function enviarPendientes(int $maxSegundos = 50, int $limite = 100): array
    {
        $inicio = microtime(true);
        $resultado = ['enviados' => 0, 'fallidos' => 0, 'vencidos' => 0];

        $resultado['vencidos'] = AvisoWhatsApp::withoutGlobalScopes()
            ->where('estado', AvisoWhatsApp::PENDIENTE)
            ->where('envio_automatico', true)
            ->where('created_at', '<', now()->subHours(self::VIGENCIA_HORAS))
            ->update(['envio_automatico' => false, 'ultimo_error' => 'No salió a tiempo: se manda a mano desde el panel.']);

        $ids = AvisoWhatsApp::withoutGlobalScopes()
            ->where('estado', AvisoWhatsApp::PENDIENTE)
            ->where('envio_automatico', true)
            ->where('intentos', '<', self::INTENTOS_MAXIMOS)
            ->where(fn ($q) => $q->whereNull('proximo_intento_at')->orWhere('proximo_intento_at', '<=', now()))
            ->orderBy('id')
            ->limit($limite)
            ->pluck('id');

        foreach ($ids as $id) {
            if (microtime(true) - $inicio >= $maxSegundos) {
                break;
            }

            $aviso = AvisoWhatsApp::withoutGlobalScopes()->find($id);

            if ($aviso === null || ! $this->reclamar($aviso)) {
                continue;
            }

            try {
                $motivo = $this->enviarAviso($aviso);
            } catch (Throwable $e) {
                report($e);
                $motivo = 'Error inesperado al enviar: '.class_basename($e);
            }

            if ($motivo === null) {
                $resultado['enviados']++;

                continue;
            }

            $resultado['fallidos']++;
            $intentos = (int) $aviso->intentos + 1;
            $aviso->forceFill([
                'ultimo_error' => mb_substr($motivo, 0, 500),
                // Sin más intentos, vuelve a ser manual: el panel ofrece el enlace de siempre.
                'envio_automatico' => $intentos < self::INTENTOS_MAXIMOS,
            ])->save();
        }

        return $resultado;
    }

    /** Toma el aviso para esta corrida: suma el intento y fija la espera del siguiente. */
    private function reclamar(AvisoWhatsApp $aviso): bool
    {
        $intentos = (int) $aviso->intentos;
        $espera = self::ESPERAS_MINUTOS[$intentos] ?? 15;

        return AvisoWhatsApp::withoutGlobalScopes()
            ->where('id', $aviso->id)
            ->where('estado', AvisoWhatsApp::PENDIENTE)
            ->where('intentos', $intentos)
            ->update(['intentos' => $intentos + 1, 'proximo_intento_at' => now()->addMinutes($espera)]) === 1;
    }

    /**
     * Envía un aviso pendiente al WhatsApp de su destinatario con la clave que este activó, y lo marca
     * enviado (por el sistema, o por quien lo pidió desde el panel). Devuelve null si salió, o el motivo
     * (sin la clave) si no: el destinatario ya no existe, no activó WhatsApp, cambió de teléfono o
     * CallMeBot no lo aceptó.
     */
    public function enviarAviso(AvisoWhatsApp $aviso, ?User $por = null): ?string
    {
        // Pudieron atenderlo a mano (o descartarlo) mientras tanto.
        $aviso = AvisoWhatsApp::withoutGlobalScopes()->find($aviso->id);

        if ($aviso === null || ! $aviso->estaPendiente()) {
            return 'El aviso ya no estaba pendiente.';
        }

        $usuario = $aviso->user_id ? User::find($aviso->user_id) : null;

        if ($usuario === null) {
            return 'El aviso no tiene un usuario que lo reciba por WhatsApp.';
        }

        try {
            $destinatario = $this->whatsapp->destinatario($usuario);

            if (! $this->whatsapp->puedeEnviarA($destinatario)) {
                return "{$destinatario->nombre} no tiene WhatsApp automático activado.";
            }

            $this->whatsapp->enviarA($destinatario, $aviso->mensaje);
        } catch (WhatsAppEnvioException|WhatsAppNoConfiguradoException|InvalidArgumentException $e) {
            // El mensaje ya viene sin la clave ni el texto del aviso (ver CallMeBotDriver).
            Log::warning('WhatsApp: el aviso quedó pendiente porque no se pudo enviar solo.', ['aviso_id' => $aviso->id, 'motivo' => $e->getMessage()]);

            return $e->getMessage();
        }

        // $por es quien pulsó «Enviar ahora»; null cuando lo envió el sistema solo.
        $aviso->marcarEnviado($por);

        return null;
    }

    /** Pantalla del recuento del culto: es a donde llevan los avisos. */
    private function enlaceRecuento(Culto $culto): string
    {
        return route('recuento.index', ['culto_id' => $culto->id]);
    }
}

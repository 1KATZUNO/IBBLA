<?php

namespace App\Services\WhatsApp;

use App\Events\SobresAlcanzados;
use App\Jobs\EnviarAvisoWhatsApp;
use App\Models\AvisoWhatsApp;
use App\Models\Culto;
use App\Models\Sobre;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Crea los avisos de WhatsApp para los líderes de la iglesia (un aviso por destinatario y evento).
 *
 * Destinatarios: usuarios de la iglesia cuyo rol está en config('whatsapp.avisos.roles')
 * (admin y tesorero por defecto) y que tienen un teléfono válido. Con el driver meta (y
 * credenciales) cada aviso se envía solo con un job; con link queda pendiente en el panel /avisos.
 */
class AvisosWhatsAppService
{
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

        $this->enviarSiEsAutomatico($avisos);

        return $avisos;
    }

    /**
     * Con el driver meta (y credenciales) cada aviso se envía solo con un job y lo marca enviado.
     * Con link no se hace nada: quedan pendientes en el panel. Un fallo al encolar uno no impide
     * encolar el resto, y el aviso que no salió sigue pendiente en el panel para mandarlo a mano.
     *
     * @param  Collection<int, AvisoWhatsApp>  $avisos
     */
    private function enviarSiEsAutomatico(Collection $avisos): void
    {
        if (! $this->whatsapp->puedeEnviar()) {
            return;
        }

        foreach ($avisos as $aviso) {
            try {
                EnviarAvisoWhatsApp::dispatch($aviso->id);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** Pantalla del recuento del culto: es a donde llevan los avisos. */
    private function enlaceRecuento(Culto $culto): string
    {
        return route('recuento.index', ['culto_id' => $culto->id]);
    }
}

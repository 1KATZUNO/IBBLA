<?php

namespace App\Services\WhatsApp;

use App\Models\ActivacionWhatsApp;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Activar, probar y desactivar WhatsApp (CallMeBot) para una persona o un usuario del sistema.
 *
 * La persona agrega el bot a sus contactos, le manda la frase de activación y pega en su perfil el
 * enlace que el bot le responde. Aquí se valida ese enlace, se guarda la clave (cifrada) junto al
 * consentimiento y se manda el mensaje de prueba. Reglas que no se negocian:
 *
 *  - El número del enlace tiene que ser el que la iglesia tiene registrado para esa persona (o ese
 *    usuario): así nadie recibe el estado de cuenta de otro en un número ajeno. Si el número
 *    registrado cambia después, la clave deja de servir (ver ActivacionWhatsApp::estadoFrente).
 *  - Sin consentimiento marcado no se guarda nada.
 *  - La clave nunca vuelve a mostrarse, ni a la persona ni al administrador.
 */
class ActivacionWhatsAppService
{
    public const PERSONA = 'persona';

    public const USUARIO = 'usuario';

    /** Aún no hay fila: la persona nunca pegó su enlace. */
    public const SIN_ACTIVAR = 'sin_activar';

    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    public static function tipoDe(Persona|User $receptor): string
    {
        return $receptor instanceof Persona ? self::PERSONA : self::USUARIO;
    }

    /**
     * En qué punto está el receptor: SIN_ACTIVAR o uno de los ActivacionWhatsApp::ESTADO_* frente al
     * teléfono que tiene registrado hoy.
     */
    public function situacion(Persona|User $receptor): string
    {
        $activacion = $receptor->activacionWhatsApp;

        return $activacion === null
            ? self::SIN_ACTIVAR
            : $activacion->estadoFrente(WhatsAppService::normalizarTelefono($receptor->telefono));
    }

    /**
     * Valida lo que la persona pegó y guarda la activación (o la renueva si ya tenía una).
     *
     * @throws ActivacionNoValidaException con un mensaje por campo
     */
    public function activar(Persona|User $receptor, ?string $textoPegado, bool $acepta, ?Request $request = null): ActivacionWhatsApp
    {
        $tipo = self::tipoDe($receptor);
        $registrado = WhatsAppService::normalizarTelefono($receptor->telefono);
        $errores = [];

        if ($registrado === null) {
            $errores['telefono'] = $this->mensajeSinTelefono($tipo);
        }

        $enlace = EnlaceCallMeBot::analizar($textoPegado);

        if ($enlace === null) {
            $errores['enlace'] = $this->mensajeEnlace(EnlaceCallMeBot::diagnostico($textoPegado));
        } elseif ($enlace->traeTelefono && $enlace->telefono === null) {
            $errores['enlace'] = 'El número que trae ese enlace no es válido. Copie el mensaje completo que le respondió el bot y péguelo de nuevo.';
        } elseif ($registrado !== null && $enlace->telefono !== null && $enlace->telefono !== $registrado) {
            $errores['enlace'] = $this->mensajeOtroNumero($tipo, $enlace->telefono, $registrado);
        }

        if (! $acepta) {
            $errores['acepta'] = 'Marque la casilla para aceptar recibir mensajes por WhatsApp.';
        }

        if ($errores !== []) {
            throw new ActivacionNoValidaException($errores);
        }

        $activacion = ActivacionWhatsApp::withoutGlobalScopes()->firstOrNew(
            [$tipo === self::PERSONA ? 'persona_id' : 'user_id' => $receptor->id]
        );

        // forceFill con campos elegidos aquí, nunca con datos del formulario tal cual.
        $activacion->forceFill([
            'tenant_id' => $receptor->tenant_id,
            'telefono' => $registrado,
            'apikey' => $enlace->apikey,
            'activo' => true,
            'consentimiento_at' => now(),
            'consentimiento_version' => (string) config('whatsapp.activacion.version_texto', 'v1'),
            'consentimiento_ip' => $request?->ip(),
            'consentimiento_agente' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'revocado_at' => null,
            // Una clave nueva hay que volver a probarla.
            'verificado_at' => null,
            'ultimo_envio_at' => null,
            'ultimo_error' => null,
        ])->save();

        return $activacion;
    }

    /**
     * Retira el consentimiento: se borra la clave y se deja la fecha de revocación. Devuelve la
     * activación, o null si no había nada que desactivar.
     */
    public function desactivar(Persona|User $receptor): ?ActivacionWhatsApp
    {
        $activacion = ActivacionWhatsApp::withoutGlobalScopes()
            ->where(self::tipoDe($receptor) === self::PERSONA ? 'persona_id' : 'user_id', $receptor->id)
            ->first();

        if ($activacion === null || ! $activacion->activo) {
            return null;
        }

        $activacion->forceFill([
            'activo' => false,
            'apikey' => null,
            'revocado_at' => now(),
            'verificado_at' => null,
        ])->save();

        return $activacion;
    }

    /**
     * Manda el mensaje de prueba al propio receptor con su clave.
     *
     * @throws InvalidArgumentException si el teléfono registrado no sirve
     * @throws WhatsAppNoConfiguradoException si no tiene una activación utilizable
     * @throws WhatsAppEnvioException si CallMeBot no lo aceptó
     */
    public function enviarPrueba(Persona|User $receptor): void
    {
        $destinatario = $this->whatsapp->destinatario($receptor);

        $this->whatsapp->enviarA(
            $destinatario,
            MensajesWhatsApp::prueba($destinatario->nombre, $receptor->tenant),
            true,
        );
    }

    private function mensajeSinTelefono(string $tipo): string
    {
        $quien = $tipo === self::PERSONA ? 'la administración' : 'el administrador (Gestionar Usuarios)';

        return 'No tenemos un teléfono válido registrado a su nombre, y WhatsApp necesita el mismo número con el que usted activa el bot. '
            ."Pídale a {$quien} que lo escriba (8 dígitos, por ejemplo 8888-7777) y vuelva a intentarlo.";
    }

    private function mensajeOtroNumero(string $tipo, string $delEnlace, string $registrado): string
    {
        $dondeEsta = $tipo === self::PERSONA ? 'la iglesia tiene registrado para usted' : 'tiene registrado su usuario';
        $quien = $tipo === self::PERSONA ? 'la administración' : 'el administrador';

        return 'Ese enlace es del número '.WhatsAppService::formatearTelefono($delEnlace)
            .", pero el número que {$dondeEsta} es ".WhatsAppService::formatearTelefono($registrado).'. '
            ."Use el enlace del número registrado, o pídale a {$quien} que actualice su teléfono.";
    }

    private function mensajeEnlace(string $motivo): string
    {
        $copiar = 'Copie el mensaje completo que le respondió el bot y péguelo aquí.';

        return match ($motivo) {
            EnlaceCallMeBot::VACIO => 'Pegue aquí el mensaje que le respondió el bot. '.$copiar,
            EnlaceCallMeBot::OTRO_SITIO => 'Ese enlace no es de CallMeBot. Debe empezar con https://api.callmebot.com/whatsapp.php?phone=... '.$copiar,
            EnlaceCallMeBot::SIN_CLAVE => 'A ese enlace le falta la clave (apikey=...). '.$copiar,
            default => 'No encontramos el enlace del bot en lo que pegó. Debe empezar con https://api.callmebot.com/whatsapp.php?phone=... '.$copiar,
        };
    }
}

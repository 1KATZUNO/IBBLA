<?php

namespace App\Services\WhatsApp;

use App\Models\Persona;
use App\Models\User;
use InvalidArgumentException;

/**
 * Punto único para hablar con WhatsApp: normaliza teléfonos, arma mensajes desde las
 * plantillas de config/whatsapp.php y delega en el driver elegido (link, callmebot o meta).
 *
 * El resto del sistema no sabe qué driver hay detrás: pide un enlace (siempre disponible,
 * sirve de respaldo manual) o, si puedeEnviar(), pide el envío automático.
 *
 * Con el driver callmebot la clave es de cada receptor y no de la iglesia, así que la pregunta
 * correcta no es «¿se puede enviar?» sino «¿se le puede enviar a ESTA persona?»: ver
 * puedeEnviarA() y enviarA(). Quien no tiene clave sigue con el enlace wa.me de siempre.
 */
class WhatsAppService
{
    /** Código de país de Costa Rica. */
    public const CODIGO_PAIS = '506';

    private WhatsAppDriver $driver;

    /** Sin driver explícito se toma el de config('whatsapp.driver'); los tests inyectan el suyo. */
    public function __construct(?WhatsAppDriver $driver = null)
    {
        $this->driver = $driver ?? self::driverDesdeConfig();
    }

    /** Solo «meta» activa la API; cualquier otro valor (o un error de tipeo) cae en el driver seguro. */
    public static function driverDesdeConfig(): WhatsAppDriver
    {
        return match (strtolower(trim((string) config('whatsapp.driver', 'link')))) {
            'meta' => new MetaCloudDriver((array) config('whatsapp.meta', [])),
            'callmebot' => new CallMeBotDriver((array) config('whatsapp.callmebot', [])),
            default => new LinkDriver(),
        };
    }

    public function driver(): WhatsAppDriver
    {
        return $this->driver;
    }

    public function puedeEnviar(): bool
    {
        return $this->driver->puedeEnviar();
    }

    /**
     * true si el driver trabaja con la clave personal de cada receptor (callmebot). Con él puedeEnviar()
     * es siempre false: lo que importa es puedeEnviarA($receptor).
     */
    public function usaClavePorReceptor(): bool
    {
        return $this->driver instanceof WhatsAppDriverConClave;
    }

    /**
     * El destinatario de un mensaje: su nombre, su teléfono normalizado y, con un driver de clave por
     * receptor, su activación SOLO si se puede usar hoy (activa, legible y del mismo número que tiene
     * registrado ahora). Una persona que cambió de teléfono después de activar no recibe nada en el
     * número nuevo con la clave del viejo: vuelve al enlace manual hasta que active de nuevo.
     */
    public function destinatario(Persona|User $receptor): DestinatarioWhatsApp
    {
        $telefono = self::normalizarTelefono($receptor->telefono);
        $nombre = $receptor instanceof Persona ? (string) $receptor->nombre : (string) $receptor->name;
        $activacion = null;

        if ($this->usaClavePorReceptor()) {
            $candidata = $receptor->activacionWhatsApp;

            if ($candidata !== null && $candidata->utilizablePara($telefono)) {
                $activacion = $candidata;
            }
        }

        return new DestinatarioWhatsApp($nombre, $telefono, $activacion);
    }

    /**
     * ¿Se le puede enviar el mensaje sin que nadie toque «enviar»? Con un driver de clave por receptor
     * depende de que ese receptor tenga una activación utilizable; con los demás, del driver (meta con
     * credenciales sí; link no). Siempre hace falta un teléfono que sirva.
     */
    public function puedeEnviarA(Persona|User|DestinatarioWhatsApp $receptor): bool
    {
        $destinatario = $receptor instanceof DestinatarioWhatsApp ? $receptor : $this->destinatario($receptor);

        if ($destinatario->telefono === null) {
            return false;
        }

        return $this->usaClavePorReceptor() ? $destinatario->tieneClave() : $this->driver->puedeEnviar();
    }

    /**
     * Envío automático a un receptor. Con callmebot sale con SU clave y al número con el que la activó;
     * el resultado (cuándo, o por qué falló) queda en su activación. Con los demás drivers es lo mismo
     * que enviar() al teléfono del receptor.
     *
     * @throws InvalidArgumentException si el teléfono no es válido
     * @throws WhatsAppNoConfiguradoException si no se puede enviar a este receptor (sin clave, sin credenciales)
     * @throws WhatsAppEnvioException si el proveedor no entregó el mensaje
     */
    public function enviarA(Persona|User|DestinatarioWhatsApp $receptor, string $mensaje, bool $esPrueba = false): void
    {
        $destinatario = $receptor instanceof DestinatarioWhatsApp ? $receptor : $this->destinatario($receptor);

        if ($destinatario->telefono === null) {
            throw new InvalidArgumentException('El teléfono no es válido para WhatsApp.');
        }

        if (! $this->driver instanceof WhatsAppDriverConClave) {
            $this->driver->enviar($destinatario->telefono, $mensaje);

            return;
        }

        $activacion = $destinatario->activacion;

        if ($activacion === null) {
            throw WhatsAppNoConfiguradoException::receptorSinClave($destinatario->nombre);
        }

        try {
            $this->driver->enviarConClave($activacion->telefono, $mensaje, (string) $activacion->clave());
        } catch (WhatsAppEnvioException $e) {
            $activacion->registrarEnvio($e->getMessage());

            throw $e;
        }

        $activacion->registrarEnvio(null, $esPrueba);
    }

    /** Enlace wa.me con el mensaje escrito, o null si el teléfono no sirve. */
    public function enlace(?string $telefono, string $mensaje): ?string
    {
        $normalizado = self::normalizarTelefono($telefono);

        return $normalizado === null ? null : $this->driver->enlace($normalizado, $mensaje);
    }

    /**
     * Envío automático (solo drivers que lo soportan).
     *
     * @throws InvalidArgumentException si el teléfono no es válido
     * @throws WhatsAppNoConfiguradoException si el driver no puede enviar
     * @throws WhatsAppEnvioException si el proveedor no entregó el mensaje
     */
    public function enviar(?string $telefono, string $mensaje): void
    {
        $normalizado = self::normalizarTelefono($telefono);

        if ($normalizado === null) {
            throw new InvalidArgumentException('El teléfono no es válido para WhatsApp.');
        }

        $this->driver->enviar($normalizado, $mensaje);
    }

    /**
     * Teléfono a formato E.164 sin el «+» (el que pide wa.me), o null si no es válido.
     *
     * - Se quitan espacios, guiones, paréntesis y puntos. Cualquier otro carácter (letras,
     *   barras, comas) invalida el número: «8888-7777 / 2222-3333» no debe convertirse
     *   en un teléfono inventado.
     * - 8 dígitos de Costa Rica (empiezan en 2-8)   -> 506 + número
     * - 506 + 8 dígitos, con o sin «+» o «00»        -> tal cual
     * - «+» u «00» con otro país (p. ej. +505, +1)   -> se respeta (E.164: 9 a 15 dígitos)
     * - cualquier otra cosa                          -> null
     */
    public static function normalizarTelefono(?string $telefono): ?string
    {
        if ($telefono === null) {
            return null;
        }

        // \x{00A0} = espacio duro; \x{2010}-\x{2015} = guiones largos que pegan Word y las hojas de cálculo.
        $limpio = preg_replace('/[\s\x{00A0}\-\x{2010}-\x{2015}().]+/u', '', $telefono);

        if ($limpio === null || ! preg_match('/^\+?\d+$/', $limpio)) {
            return null;
        }

        $internacional = true;

        if (str_starts_with($limpio, '+')) {
            $digitos = substr($limpio, 1);
        } elseif (str_starts_with($limpio, '00')) {
            $digitos = substr($limpio, 2);
        } else {
            $digitos = $limpio;
            $internacional = false;
        }

        $local = '/^[2-8]\d{7}$/';
        $pais = self::CODIGO_PAIS;

        if (! $internacional && preg_match($local, $digitos)) {
            return $pais.$digitos;
        }

        // 506 + número local de 8 dígitos. Si empieza por 506 no cabe "otro país": o es de CR o no es válido.
        if (str_starts_with($digitos, $pais) && ($internacional || strlen($digitos) === 11)) {
            $resto = substr($digitos, strlen($pais));

            return preg_match($local, $resto) ? $pais.$resto : null;
        }

        if ($internacional && preg_match('/^[1-9]\d{8,14}$/', $digitos)) {
            return $digitos;
        }

        return null;
    }

    /** Teléfono ya normalizado a texto legible: «+506 8888-7777». */
    public static function formatearTelefono(?string $normalizado): string
    {
        if ($normalizado === null || $normalizado === '') {
            return '';
        }

        if (preg_match('/^'.self::CODIGO_PAIS.'(\d{4})(\d{4})$/', $normalizado, $m)) {
            return '+'.self::CODIGO_PAIS.' '.$m[1].'-'.$m[2];
        }

        return '+'.$normalizado;
    }

    /**
     * Rellena una plantilla de config con sus variables: «Hola {nombre}» + ['nombre' => 'Ana'].
     * Una sola pasada, así que un valor que contenga «{enlace}» no se vuelve a sustituir.
     *
     * @param  array<string, scalar|\Stringable|null>  $variables
     */
    public static function armarMensaje(string $plantilla, array $variables): string
    {
        $reemplazos = [];

        foreach ($variables as $clave => $valor) {
            $reemplazos['{'.$clave.'}'] = (string) $valor;
        }

        return strtr($plantilla, $reemplazos);
    }
}

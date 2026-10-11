<?php

namespace App\Services\WhatsApp;

use InvalidArgumentException;

/**
 * Punto único para hablar con WhatsApp: normaliza teléfonos, arma mensajes desde las
 * plantillas de config/whatsapp.php y delega en el driver elegido (link o meta).
 *
 * El resto del sistema no sabe qué driver hay detrás: pide un enlace (siempre disponible,
 * sirve de respaldo manual) o, si puedeEnviar(), pide el envío automático.
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

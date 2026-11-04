<?php

namespace App\Services\WhatsApp;

use RuntimeException;

/** El driver elegido no tiene las credenciales o la configuración que necesita para enviar. */
class WhatsAppNoConfiguradoException extends RuntimeException
{
    public static function meta(): self
    {
        return new self(
            'El driver «meta» de WhatsApp no está configurado: faltan WHATSAPP_META_TOKEN y/o '
            .'WHATSAPP_META_PHONE_NUMBER_ID en el .env. Mientras tanto use WHATSAPP_DRIVER=link.'
        );
    }

    public static function callmebotSinClave(): self
    {
        return new self(
            'CallMeBot necesita la clave personal de quien recibe el mensaje: esa persona debe activar el bot '
            .'desde su WhatsApp y pegar su enlace en su perfil. Mientras tanto se usa el enlace de WhatsApp (wa.me).'
        );
    }

    public static function receptorSinClave(string $nombre): self
    {
        return new self(
            "{$nombre} no tiene activado WhatsApp automático (no ha pegado su enlace de CallMeBot, lo desactivó "
            .'o cambió de número). Use el enlace de WhatsApp (wa.me).'
        );
    }

    public static function sinEnvioAutomatico(string $driver): self
    {
        return new self(
            "El driver «{$driver}» no envía mensajes por sí solo: abra el enlace de WhatsApp "
            .'(wa.me) y toque «enviar».'
        );
    }
}

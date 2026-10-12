<?php

namespace App\Services\WhatsApp;

/**
 * Driver por defecto: no envía nada, arma un enlace wa.me que abre el chat con el
 * mensaje escrito. La persona solo toca «enviar» en su WhatsApp (Web, escritorio o móvil).
 */
class LinkDriver implements WhatsAppDriver
{
    public function nombre(): string
    {
        return 'link';
    }

    public function puedeEnviar(): bool
    {
        return false;
    }

    public function enlace(string $telefono, string $mensaje): string
    {
        return self::construirUrl($telefono, $mensaje);
    }

    public function enviar(string $telefono, string $mensaje): void
    {
        throw WhatsAppNoConfiguradoException::sinEnvioAutomatico($this->nombre());
    }

    /**
     * https://wa.me/<E.164 sin +>?text=<mensaje codificado>
     *
     * Se usa rawurlencode (espacio = %20) porque es lo que WhatsApp documenta y lo que
     * cualquier cliente decodifica igual; el «+» de urlencode no siempre se lee como espacio.
     */
    public static function construirUrl(string $telefono, string $mensaje): string
    {
        $url = 'https://wa.me/'.$telefono;

        if ($mensaje !== '') {
            $url .= '?text='.rawurlencode($mensaje);
        }

        return $url;
    }
}

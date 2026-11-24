<?php

namespace App\Services\WhatsApp;

/**
 * Driver cuya clave de envío no es de la iglesia sino de cada receptor (CallMeBot): quien quiere
 * recibir mensajes activa el bot desde su propio WhatsApp y guarda la clave que le responde.
 *
 * Por eso no puede enviar «a secas» (puedeEnviar() es false): hay que saber a quién y con qué
 * clave. WhatsAppService decide por receptor; quien no tiene clave sigue con el enlace wa.me.
 */
interface WhatsAppDriverConClave extends WhatsAppDriver
{
    /**
     * Envía el mensaje con la clave personal del receptor.
     *
     * @param  string  $telefono  E.164 sin «+» (el mismo número con el que el receptor activó el bot)
     *
     * @throws WhatsAppNoConfiguradoException si falta la clave
     * @throws WhatsAppEnvioException si el proveedor no aceptó el mensaje
     */
    public function enviarConClave(string $telefono, string $mensaje, string $apikey): void;
}

<?php

namespace App\Services\WhatsApp;

/**
 * Forma de entregar un mensaje de WhatsApp.
 *
 * Los teléfonos llegan ya normalizados (E.164 sin el «+», p. ej. 50688887777): la
 * validación y la normalización las hace WhatsAppService antes de llamar al driver.
 */
interface WhatsAppDriver
{
    /** Nombre del driver tal como se escribe en config('whatsapp.driver'). */
    public function nombre(): string;

    /**
     * true si el driver entrega el mensaje por sí solo (API) y está listo para hacerlo.
     * false si solo prepara un enlace y una persona tiene que tocar «enviar».
     */
    public function puedeEnviar(): bool;

    /** Enlace wa.me que abre el chat con el mensaje ya escrito. */
    public function enlace(string $telefono, string $mensaje): string;

    /**
     * Entrega el mensaje sin intervención humana.
     *
     * @throws WhatsAppNoConfiguradoException si el driver no tiene lo necesario para enviar
     * @throws WhatsAppEnvioException si el proveedor rechazó o no pudo entregar el mensaje
     */
    public function enviar(string $telefono, string $mensaje): void;
}

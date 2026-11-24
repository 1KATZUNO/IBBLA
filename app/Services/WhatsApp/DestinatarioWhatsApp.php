<?php

namespace App\Services\WhatsApp;

use App\Models\ActivacionWhatsApp;

/**
 * A quién se le escribe y con qué: el nombre, el teléfono ya normalizado (o null si el que está
 * guardado no sirve) y, solo si el driver trabaja con clave por receptor y la persona tiene una
 * activación utilizable, esa activación.
 *
 * Lo arma WhatsAppService::destinatario() a partir de una Persona o un User.
 */
final class DestinatarioWhatsApp
{
    public function __construct(
        public readonly string $nombre,
        public readonly ?string $telefono,
        public readonly ?ActivacionWhatsApp $activacion = null,
    ) {
    }

    /** Hay una clave utilizable: activa, legible y del mismo número que tiene registrado hoy. */
    public function tieneClave(): bool
    {
        return $this->activacion !== null;
    }
}

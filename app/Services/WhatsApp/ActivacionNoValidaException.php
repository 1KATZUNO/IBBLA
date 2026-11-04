<?php

namespace App\Services\WhatsApp;

use RuntimeException;

/**
 * Lo que la persona hizo en el formulario de activación no sirve (enlace de otro número, casilla sin
 * marcar...). Trae un mensaje en español por campo para mostrarlo junto a él.
 */
class ActivacionNoValidaException extends RuntimeException
{
    /** @param array<string, string> $errores campo (enlace, acepta, telefono) => mensaje */
    public function __construct(private array $errores)
    {
        parent::__construct((string) (reset($errores) ?: 'La activación de WhatsApp no es válida.'));
    }

    /** @return array<string, string> */
    public function errores(): array
    {
        return $this->errores;
    }
}

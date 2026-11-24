<?php

namespace App\Events;

use App\Models\Culto;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se creó un culto desde la pantalla de Cultos. Lo escucha CrearAvisosWhatsApp.
 *
 * No se dispara al importar asistencias retroactivas: esos cultos son historia, no novedad.
 */
class CultoCreado
{
    use Dispatchable, SerializesModels;

    public function __construct(public Culto $culto)
    {
    }
}

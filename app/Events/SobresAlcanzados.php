<?php

namespace App\Events;

use App\Models\Culto;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un culto llegó a la cantidad de sobres configurada (whatsapp.avisos.sobres_umbral, 10 por defecto).
 * Se dispara una sola vez por culto; AvisosWhatsAppService::sobreRegistrado lo garantiza.
 */
class SobresAlcanzados
{
    use Dispatchable, SerializesModels;

    public function __construct(public Culto $culto, public int $cantidad)
    {
    }
}

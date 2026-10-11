<?php

namespace App\Listeners;

use App\Events\CultoCreado;
use App\Events\SobresAlcanzados;
use App\Services\WhatsApp\AvisosWhatsAppService;
use Throwable;

/**
 * Convierte los eventos del culto en avisos de WhatsApp para los líderes. Laravel registra solo los
 * métodos handle* de esta carpeta (event discovery); `php artisan event:list` los muestra.
 *
 * Los avisos son un extra: si algo falla aquí se reporta y se sigue, para no tumbar la creación del
 * culto ni el registro de un sobre, que son lo importante.
 */
class CrearAvisosWhatsApp
{
    public function __construct(private AvisosWhatsAppService $avisos)
    {
    }

    public function handleCultoCreado(CultoCreado $evento): void
    {
        try {
            $this->avisos->cultoCreado($evento->culto);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function handleSobresAlcanzados(SobresAlcanzados $evento): void
    {
        try {
            $this->avisos->sobresAlcanzados($evento->culto, $evento->cantidad);
        } catch (Throwable $e) {
            report($e);
        }
    }
}

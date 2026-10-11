<?php

namespace App\Jobs;

use App\Models\AvisoWhatsApp;
use App\Services\WhatsApp\WhatsAppNoConfiguradoException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use InvalidArgumentException;

/**
 * Envía un aviso pendiente por la API (driver meta) y lo marca como enviado.
 *
 * Solo se encola con el driver meta y credenciales (ver AvisosWhatsAppService). Si el envío
 * falla el aviso se queda pendiente: el panel /avisos sigue ofreciendo el enlace para mandarlo
 * a mano, así que nunca se pierde.
 */
class EnviarAvisoWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** Se recibe el id y no el modelo: el worker corre sin sesión y el scope por iglesia no aplica. */
    public function __construct(public int $avisoId)
    {
    }

    /** @return list<int> segundos de espera entre reintentos */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(WhatsAppService $whatsapp): void
    {
        $aviso = AvisoWhatsApp::withoutGlobalScopes()->find($this->avisoId);

        // Ya lo atendieron a mano (o lo descartaron) mientras esperaba en la cola.
        if (! $aviso || ! $aviso->estaPendiente()) {
            return;
        }

        try {
            $whatsapp->enviar($aviso->destinatario_telefono, $aviso->mensaje);
        } catch (WhatsAppNoConfiguradoException|InvalidArgumentException $e) {
            // Reintentar no lo arregla: falta configuración o el teléfono no sirve. Queda pendiente en el panel.
            report($e);
            $this->fail($e);

            return;
        }

        $aviso->marcarEnviado(null);
    }
}

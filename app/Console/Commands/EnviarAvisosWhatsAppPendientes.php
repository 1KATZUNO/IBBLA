<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\AvisosWhatsAppService;
use Illuminate\Console\Command;

/**
 * QE-009: envía los avisos de WhatsApp que el sistema puede mandar solo (meta con credenciales, o
 * callmebot a quien activó su WhatsApp). Corre cada minuto desde el planificador (routes/console.php),
 * nunca dentro de una petición: así crear un culto o guardar el 10.º sobre no esperan al proveedor.
 *
 * La cola es la propia tabla avisos_whatsapp (envio_automatico, intentos, proximo_intento_at,
 * ultimo_error). No usa las colas de Laravel: en Hostinger no hay workers residentes y así no depende
 * de QUEUE_CONNECTION ni deja trabajos serializados que nadie procesa.
 */
class EnviarAvisosWhatsAppPendientes extends Command
{
    protected $signature = 'whatsapp:enviar-pendientes
        {--max-segundos=50 : Deja de tomar avisos pasado este tiempo (el planificador corre cada minuto)}';

    protected $description = 'Envía los avisos de WhatsApp automáticos pendientes (reintentos acotados; los fallidos quedan en el panel).';

    public function handle(AvisosWhatsAppService $avisos): int
    {
        $r = $avisos->enviarPendientes(max(1, (int) $this->option('max-segundos')));

        $this->line("Avisos enviados: {$r['enviados']} · fallidos: {$r['fallidos']} · vencidos: {$r['vencidos']}");

        return self::SUCCESS;
    }
}

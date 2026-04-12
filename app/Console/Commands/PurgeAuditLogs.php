<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PurgeAuditLogs extends Command
{
    protected $signature = 'audit:purge {--days=30 : Eliminar registros mas antiguos que estos dias}';

    protected $description = 'Eliminar registros de auditoria antiguos';

    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $count = AuditLog::where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info('No hay registros de auditoria mas antiguos que '.$days.' dias.');

            return 0;
        }

        AuditLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Se eliminaron {$count} registros de auditoria con mas de {$days} dias.");

        return 0;
    }
}

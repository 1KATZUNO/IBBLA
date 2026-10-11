<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PurgeAuditLogs extends Command
{
    protected $signature = 'audit:purge
        {--days=30 : Eliminar registros mas antiguos que estos dias}
        {--auth-days=365 : Eliminar los registros de ingreso/salida mas antiguos que estos dias}';

    protected $description = 'Eliminar registros de auditoria antiguos (el historial de ingresos se conserva mas tiempo)';

    public function handle()
    {
        $days = (int) $this->option('days');
        $authDays = max((int) $this->option('auth-days'), $days);

        // El detalle de cada peticion de admin/tesorero (lo que mas crece) se purga a los
        // $days. Los ingresos, salidas e intentos fallidos son el "Historial de ingresos" que
        // consulta el cliente: se guardan mas tiempo, o la pantalla quedaria casi vacia.
        $peticiones = AuditLog::where('created_at', '<', now()->subDays($days))
            ->where(function ($q) {
                // action NOT IN (...) no incluye las filas con action NULL: se suman aparte.
                $q->whereNotIn('action', AuditLog::ACCIONES_DE_ACCESO)->orWhereNull('action');
            });
        $accesos = AuditLog::accesos()->where('created_at', '<', now()->subDays($authDays));

        $countPeticiones = (clone $peticiones)->count();
        $countAccesos = (clone $accesos)->count();

        if ($countPeticiones + $countAccesos === 0) {
            $this->info('No hay registros de auditoria para purgar (peticiones > '.$days.' dias, ingresos > '.$authDays.' dias).');

            return 0;
        }

        $peticiones->delete();
        $accesos->delete();

        $this->info("Se eliminaron {$countPeticiones} registros de auditoria con mas de {$days} dias.");
        $this->info("Se eliminaron {$countAccesos} registros de ingreso con mas de {$authDays} dias.");

        return 0;
    }
}

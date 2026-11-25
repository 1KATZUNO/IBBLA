<?php

namespace App\Console\Commands;

use App\Services\Promesas\ActivadorCortes;
use Illuminate\Console\Command;

/**
 * Pone en vigor los cortes de promesas ("promesas futuras") cuya fecha de inicio ya llego y que tienen
 * promesas programadas. Corre en el planificador cada diez minutos; ademas hay un disparo perezoso en
 * las peticiones web (middleware ActivarCortesPromesas) por si el cron no corre. Idempotente.
 *
 *   php artisan promesas:activar-cortes
 *   php artisan promesas:activar-cortes --tenant=7
 */
class ActivarCortesPromesas extends Command
{
    protected $signature = 'promesas:activar-cortes {--tenant= : solo esa iglesia (por defecto todas)}';

    protected $description = 'Activa los cortes de promesas programados cuya fecha de inicio ya llegó (los vacíos no se activan).';

    public function handle(ActivadorCortes $activador): int
    {
        $tenant = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $activados = $activador->activarPendientes($tenant);

        $this->info($activados === 0
            ? 'No hay cortes de promesas pendientes de activar.'
            : "Se activaron {$activados} ".($activados === 1 ? 'corte' : 'cortes').' de promesas.');

        return self::SUCCESS;
    }
}

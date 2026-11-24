<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Reportes\SincronizadorCompromisos;
use Illuminate\Console\Command;

/**
 * Recalcula la tabla `compromisos` con el motor unico de reportes (QA-025): las pantallas ya no
 * escriben al consultar. Idempotente (una segunda corrida no cambia nada) y acotado a los meses
 * con cultos hasta hoy. Tambien lo llaman el cierre de un culto y el boton del admin.
 *
 *   php artisan compromisos:sincronizar                  # todas las iglesias, anio en curso
 *   php artisan compromisos:sincronizar --anio=2025
 *   php artisan compromisos:sincronizar --tenant=7 --mes=10
 */
class SincronizarCompromisos extends Command
{
    protected $signature = 'compromisos:sincronizar {--tenant=* : id de la iglesia (por defecto todas)} {--anio= : anio (por defecto el actual)} {--mes= : solo ese mes}';

    protected $description = 'Recalcula la tabla compromisos con el motor unico (idempotente, solo meses con cultos).';

    public function handle(SincronizadorCompromisos $sincronizador): int
    {
        $anio = (int) ($this->option('anio') ?: now()->year);
        $mes = $this->option('mes') !== null ? (int) $this->option('mes') : null;
        if ($mes !== null && ($mes < 1 || $mes > 12)) {
            $this->error('El mes debe estar entre 1 y 12.');

            return self::FAILURE;
        }

        $ids = array_filter(array_map('intval', (array) $this->option('tenant')));
        $tenants = Tenant::query()->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))->orderBy('id')->get();
        if ($tenants->isEmpty()) {
            $this->warn('No hay iglesias que sincronizar.');

            return self::SUCCESS;
        }

        $filas = [];
        foreach ($tenants as $tenant) {
            $inicio = microtime(true);
            $r = $sincronizador->sincronizar($tenant, $anio, $mes);
            $filas[] = [
                $tenant->id.' '.($tenant->siglas ?? ''),
                $r['meses'] === [] ? '-' : implode(',', $r['meses']),
                $r['creados'],
                $r['actualizados'],
                $r['borrados'],
                $r['sin_cambios'],
                number_format(microtime(true) - $inicio, 2).' s',
            ];
        }

        $this->info("Compromisos de {$anio}".($mes ? " (mes {$mes})" : '').':');
        $this->table(['Iglesia', 'Meses', 'Creados', 'Actualizados', 'Borrados', 'Sin cambios', 'Tiempo'], $filas);

        return self::SUCCESS;
    }
}

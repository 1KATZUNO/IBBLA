<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Analisis\AnalizadorIngresos;
use App\Services\Analisis\ContextoAnalisis;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Cuadra sobres contra detalles, totales de culto y estados de cuenta, y detecta duplicados, sobres sin persona,
 * dólares sin tipo de cambio, cultos sin cerrar y compromisos desincronizados. Solo lee.
 *
 *   php artisan ingresos:analizar
 *   php artisan ingresos:analizar --anio=2026 --desde=2026-01-01 --salida=docs/plan-oct-2026/ANALISIS-INGRESOS.md
 */
class AnalizarIngresos extends Command
{
    protected $signature = 'ingresos:analizar
        {--iglesia= : Id o slug de la iglesia (por defecto, todas)}
        {--desde= : Solo cultos desde esta fecha (AAAA-MM-DD)}
        {--hasta= : Solo cultos hasta esta fecha (AAAA-MM-DD)}
        {--anio= : Año de los estados de cuenta y compromisos (por defecto, el actual)}
        {--limite=25 : Casos que se listan por tabla (0 = todos)}
        {--dias-sin-cerrar=7 : Días que un culto con sobres puede seguir abierto}
        {--salida= : Ruta del archivo Markdown (si no, se escribe en pantalla)}';

    protected $description = 'Analiza los ingresos: cuadra sobres, cultos y estados de cuenta y lista lo que no coincide (solo lectura)';

    public function handle(AnalizadorIngresos $analizador): int
    {
        try {
            $desde = $this->fecha('desde');
            $hasta = $this->fecha('hasta');
        } catch (Throwable) {
            $this->error('Las fechas deben escribirse como AAAA-MM-DD.');

            return self::FAILURE;
        }

        if ($desde && $hasta && $desde > $hasta) {
            $this->error('--desde no puede ser posterior a --hasta.');

            return self::FAILURE;
        }

        $anio = $this->option('anio') !== null ? (int) $this->option('anio') : null;
        if ($anio !== null && ($anio < 2000 || $anio > 2100)) {
            $this->error('--anio debe ser un año de cuatro cifras.');

            return self::FAILURE;
        }

        $iglesias = Tenant::query()->when($this->option('iglesia'), fn ($q, $i) => $q->where(fn ($w) => $w->where('id', is_numeric($i) ? (int) $i : 0)->orWhere('slug', $i)))->orderBy('id')->get();
        if ($iglesias->isEmpty()) {
            $this->error('No hay ninguna iglesia que coincida.');

            return self::FAILURE;
        }

        $hoy = Carbon::now();
        $resultados = [];
        foreach ($iglesias as $iglesia) {
            $contexto = new ContextoAnalisis(
                $iglesia, $desde, $hasta, $hoy, $anio ?? $hoy->year,
                max(0, (int) $this->option('limite')), max(0, (int) $this->option('dias-sin-cerrar')),
            );
            $resultados[] = [$contexto, $analizador->analizar($contexto)];
        }

        $informe = $analizador->informe($resultados, $hoy->format('d/m/Y H:i'));

        if ($ruta = $this->option('salida')) {
            File::ensureDirectoryExists(dirname($ruta));
            File::put($ruta, $informe);
            $this->info("Informe guardado en {$ruta}");
            foreach ($resultados as [$ctx, $secciones]) {
                foreach ($secciones as $s) {
                    $this->line(sprintf('  %-60s %5d hallazgos (%s)', "{$ctx->iglesia->nombre}: {$s->titulo}", $s->hallazgos, $s->estado()));
                }
            }
        } else {
            $this->line($informe);
        }

        return self::SUCCESS;
    }

    private function fecha(string $opcion): ?string
    {
        $valor = $this->option($opcion);

        return $valor ? Carbon::createFromFormat('!Y-m-d', (string) $valor)->toDateString() : null;
    }
}

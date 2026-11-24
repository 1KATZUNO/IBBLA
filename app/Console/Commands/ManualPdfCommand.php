<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Manual\Manual;
use App\Support\Manual\ManualPdf;
use Illuminate\Console\Command;

/**
 * Deja listo el PDF del manual de cada iglesia (por ejemplo al desplegar), para que la primera
 * persona que lo pida no espere a que se dibuje.
 */
class ManualPdfCommand extends Command
{
    protected $signature = 'manual:pdf {--iglesia= : Slug de una sola iglesia (por omision, todas las activas)}';

    protected $description = 'Genera de antemano el PDF del manual de usuario de cada iglesia';

    public function handle(): int
    {
        $paginas = Manual::paginas();
        if ($paginas === []) {
            $this->error('No hay paginas del manual en '.Manual::directorio());

            return self::FAILURE;
        }

        $iglesias = Tenant::query()
            ->where('activo', true)
            ->when($this->option('iglesia'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get();

        if ($iglesias->isEmpty()) {
            $this->warn('No hay iglesias activas que atender.');

            return self::SUCCESS;
        }

        foreach ($iglesias as $iglesia) {
            app()->instance('current_tenant', $iglesia);
            $ruta = ManualPdf::archivo($paginas, true);
            $this->info("{$iglesia->siglas}: ".basename($ruta).' ('.round(filesize($ruta) / 1024).' KB)');
        }

        app()->instance('current_tenant', null);

        return self::SUCCESS;
    }
}

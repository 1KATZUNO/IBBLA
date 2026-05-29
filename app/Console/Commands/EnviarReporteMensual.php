<?php

namespace App\Console\Commands;

use App\Exports\AsistenciaExport;
use App\Exports\IngresosExport;
use App\Exports\PromesasExport;
use App\Mail\ReporteMensualMail;
use App\Models\Culto;
use App\Models\RegistroExtraTipo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Comando: enviar correo automático mensual con reportes adjuntos (Fase 7).
 *
 * Uso:
 *   php artisan reporte:enviar-mensual               # mes anterior
 *   php artisan reporte:enviar-mensual --mes=4 --año=2026
 *
 * Destinatarios: lee la variable de entorno REPORTE_MENSUAL_TO
 * (lista separada por comas). Si está vacía, sale sin enviar.
 *
 * Se invoca desde Schedule::command(...)->monthlyOn(1, '08:00') en
 * routes/console.php — requiere cron `php artisan schedule:run`
 * corriendo cada minuto en el servidor.
 */
class EnviarReporteMensual extends Command
{
    protected $signature = 'reporte:enviar-mensual {--mes=} {--año=} {--dry-run}';

    protected $description = 'Envía por correo los reportes del mes (PDF + Excel) a los destinatarios configurados.';

    public function handle(): int
    {
        $hoy = Carbon::now();
        $mes = (int) ($this->option('mes') ?: $hoy->copy()->subMonth()->month);
        $año = (int) ($this->option('año') ?: $hoy->copy()->subMonth()->year);
        $nombreMes = Carbon::createFromDate($año, $mes, 1)->locale('es')->translatedFormat('F');

        $destinatarios = $this->parseDestinatarios();
        if (empty($destinatarios)) {
            $this->warn('No hay destinatarios configurados (REPORTE_MENSUAL_TO vacío). Aborto.');

            return self::SUCCESS;
        }

        $this->info("Generando reportes de {$nombreMes} {$año} para: ".implode(', ', $destinatarios));

        // 1) Generar adjuntos en storage/app/reportes-mensuales/
        $dir = storage_path('app/reportes-mensuales');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $slug = strtolower($nombreMes).'_'.$año;
        $adjuntos = [];

        // PDF asistencia
        try {
            $cultos = Culto::with(['asistencia.detallesClases.claseAsistencia', 'asistencia.registrosExtra.tipo'])
                ->whereYear('fecha', $año)
                ->whereMonth('fecha', $mes)
                ->orderBy('fecha', 'asc')
                ->get();
            $registroExtraTipos = RegistroExtraTipo::activos()->ordenados()->get();
            $pdf = Pdf::loadView('pdfs.asistencia-mes', compact('cultos', 'nombreMes', 'año', 'registroExtraTipos'));
            $path = "{$dir}/asistencia_{$slug}.pdf";
            $pdf->save($path);
            $adjuntos[] = ['path' => $path, 'filename' => "asistencia_{$slug}.pdf"];
        } catch (\Throwable $e) {
            $this->error('PDF asistencia falló: '.$e->getMessage());
        }

        // Excel asistencia — usamos Excel::raw para escribir el archivo en un
        // path absoluto conocido, evitando depender del disk config (Laravel 12
        // por defecto pone disco 'local' en storage/app/private).
        try {
            $cultos = $cultos ?? collect();
            $path = "{$dir}/asistencia_{$slug}.xlsx";
            $raw = Excel::raw(
                new AsistenciaExport($cultos, $registroExtraTipos ?? collect(), ucfirst($nombreMes).' '.$año),
                ExcelFormat::XLSX,
            );
            file_put_contents($path, $raw);
            $adjuntos[] = ['path' => $path, 'filename' => "asistencia_{$slug}.xlsx"];
        } catch (\Throwable $e) {
            $this->error('Excel asistencia falló: '.$e->getMessage());
        }

        // Excel ingresos
        try {
            $categories = tenant_categories();
            $slugs = $categories->pluck('slug')->toArray();
            $cultosIngresos = Culto::with(['totales', 'sobres.detalles', 'ofrendasSueltas'])
                ->whereYear('fecha', $año)
                ->whereMonth('fecha', $mes)
                ->orderBy('fecha', 'asc')
                ->get();
            $registros = [];
            foreach ($cultosIngresos as $culto) {
                if ($culto->totales) {
                    $row = [];
                    foreach ($slugs as $s) {
                        $row[$s] = $culto->totales->getCategoryTotal($s);
                    }
                    $row['fecha'] = $culto->fecha->format('d/m/Y');
                    $row['tipo'] = ucfirst($culto->tipo_culto);
                    $row['suelto'] = $culto->totales->total_suelto ?? 0;
                    $row['total'] = $culto->totales->total_general ?? 0;
                    $registros[] = $row;
                }
            }
            $path = "{$dir}/ingresos_{$slug}.xlsx";
            $raw = Excel::raw(new IngresosExport($registros, $categories, 'culto'), ExcelFormat::XLSX);
            file_put_contents($path, $raw);
            $adjuntos[] = ['path' => $path, 'filename' => "ingresos_{$slug}.xlsx"];
        } catch (\Throwable $e) {
            $this->error('Excel ingresos falló: '.$e->getMessage());
        }

        // Excel promesas (año en curso)
        try {
            $path = "{$dir}/promesas_{$slug}.xlsx";
            $raw = Excel::raw(new PromesasExport($año, $mes), ExcelFormat::XLSX);
            file_put_contents($path, $raw);
            $adjuntos[] = ['path' => $path, 'filename' => "promesas_{$slug}.xlsx"];
        } catch (\Throwable $e) {
            $this->error('Excel promesas falló: '.$e->getMessage());
        }

        $this->info('Adjuntos generados: '.count($adjuntos));

        if ($this->option('dry-run')) {
            $this->info('--dry-run activo: NO se envía el correo.');

            return self::SUCCESS;
        }

        // 2) Enviar correo
        try {
            Mail::to($destinatarios)->send(new ReporteMensualMail($mes, $año, $nombreMes, $adjuntos));
            $this->info('Correo enviado a: '.implode(', ', $destinatarios));
        } catch (\Throwable $e) {
            $this->error('Error enviando correo: '.$e->getMessage());

            return self::FAILURE;
        }

        // 3) Limpiar adjuntos del disco (ya enviados)
        foreach ($adjuntos as $adj) {
            if (file_exists($adj['path'])) {
                @unlink($adj['path']);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function parseDestinatarios(): array
    {
        $raw = env('REPORTE_MENSUAL_TO', '');
        $emails = array_filter(array_map('trim', explode(',', $raw)));

        return array_values(array_filter($emails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }
}

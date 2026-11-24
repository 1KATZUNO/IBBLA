<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Services\Reportes\AuditoriaReportes;
use App\Services\Reportes\Cultos\FiltroCultos;
use App\Services\Reportes\Cultos\GeneradorReporteCultos;
use App\Services\Reportes\Cultos\ReporteCultos;
use App\Services\Reportes\Exportaciones\CultosExcel;
use App\Services\Reportes\Exportaciones\CultosPdf;
use App\Services\Reportes\PeriodoReporte;
use App\Support\RangoFechas;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reporte de cultos (HU-R10): por rango de fechas o por cultos elegidos, y por tipo de culto.
 * Pantalla con vista previa, PDF y Excel; los tres salen del mismo ReporteCultos.
 *
 *   GET /reportes/cultos?fecha_inicio=2026-01-01&fecha_fin=2026-03-31&tipos[]=domingo&cultos[]=12
 *   GET /reportes/cultos/pdf?...&accion=ver|descargar
 *   GET /reportes/cultos/excel?...
 */
class CultosController extends Controller
{
    public function __construct(
        private readonly GeneradorReporteCultos $generador,
        private readonly AuditoriaReportes $auditoria,
    ) {}

    public function index(Request $request)
    {
        $tipos = FiltroCultos::tiposDisponibles();
        $filtro = FiltroCultos::desde($request, $tipos);

        return view('reportes.cultos', [
            'reporte' => $this->generador->generar($filtro),
            'filtro' => $filtro,
            'tipos' => $tipos,
            'cultosDelRango' => $this->generador->cultosDelRango($filtro),
            'entrada' => RangoFechas::entrada($request),
            'atajos' => RangoFechas::atajos(),
        ]);
    }

    public function pdf(Request $request): Response
    {
        $request->validate(['accion' => 'nullable|in:ver,descargar']);
        $reporte = $this->generador->generar(FiltroCultos::desde($request, FiltroCultos::tiposDisponibles()));
        $contenido = app(CultosPdf::class)->generar($reporte);
        $this->auditar($reporte, 'pdf');

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($request->query('accion') === 'descargar' ? 'attachment' : 'inline', CultosPdf::nombreArchivo($reporte)),
            'Content-Length' => strlen($contenido),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function excel(Request $request): Response
    {
        $reporte = $this->generador->generar(FiltroCultos::desde($request, FiltroCultos::tiposDisponibles()));
        $libro = app(CultosExcel::class)->generar($reporte);
        $this->auditar($reporte, 'excel');

        return $libro->descargar(CultosPdf::nombreArchivo($reporte, 'xlsx'));
    }

    private function auditar(ReporteCultos $reporte, string $formato): void
    {
        $t = $reporte->totales();
        $rango = $reporte->filtro->rango;
        $this->auditoria->registrar('cultos', $formato, PeriodoReporte::deFechas($rango->inicio, $rango->fin), null, 0, [
            'cultos' => $t['cultos'],
            'tipos' => $reporte->filtro->tipos,
            'cultos_elegidos' => $reporte->filtro->cultos,
            'total' => $t['total'],
        ]);
    }
}

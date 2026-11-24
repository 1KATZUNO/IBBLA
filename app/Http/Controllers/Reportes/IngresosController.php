<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Services\Reportes\AuditoriaReportes;
use App\Services\Reportes\Cultos\FiltroCultos;
use App\Services\Reportes\Exportaciones\IngresosExcel;
use App\Services\Reportes\Exportaciones\IngresosPdf;
use App\Services\Reportes\Ingresos\ReporteIngresos;
use App\Services\Reportes\PeriodoReporte;
use App\Support\RangoFechas;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reporte de ingresos (HU-R11): rango de fechas, por categoria y por metodo de pago, contra el
 * periodo anterior de igual duracion. Pantalla, PDF y Excel del mismo ReporteIngresos.
 *
 *   GET /reportes/ingresos?fecha_inicio=2026-09-01&fecha_fin=2026-09-30&tipos[]=domingo
 *   GET /reportes/ingresos/pdf?...&accion=ver|descargar
 *   GET /reportes/ingresos/excel?...
 */
class IngresosController extends Controller
{
    public function __construct(private readonly AuditoriaReportes $auditoria) {}

    public function index(Request $request)
    {
        $tipos = FiltroCultos::tiposDisponibles();
        $filtro = FiltroCultos::desde($request, $tipos);

        return view('reportes.ingresos', [
            'reporte' => ReporteIngresos::generar($filtro),
            'filtro' => $filtro,
            'tipos' => $tipos,
            'entrada' => RangoFechas::entrada($request),
            'atajos' => RangoFechas::atajos(),
        ]);
    }

    public function pdf(Request $request): Response
    {
        $request->validate(['accion' => 'nullable|in:ver,descargar']);
        $reporte = ReporteIngresos::generar(FiltroCultos::desde($request, FiltroCultos::tiposDisponibles()));
        $contenido = app(IngresosPdf::class)->generar($reporte);
        $this->auditar($reporte, 'pdf');

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($request->query('accion') === 'descargar' ? 'attachment' : 'inline', IngresosPdf::nombreArchivo($reporte)),
            'Content-Length' => strlen($contenido),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function excel(Request $request): Response
    {
        $reporte = ReporteIngresos::generar(FiltroCultos::desde($request, FiltroCultos::tiposDisponibles()));
        $libro = app(IngresosExcel::class)->generar($reporte);
        $this->auditar($reporte, 'excel');

        return $libro->descargar(IngresosPdf::nombreArchivo($reporte, 'xlsx'));
    }

    private function auditar(ReporteIngresos $reporte, string $formato): void
    {
        $rango = $reporte->filtro()->rango;
        $total = $reporte->total();
        $this->auditoria->registrar('ingresos', $formato, PeriodoReporte::deFechas($rango->inicio, $rango->fin), null, 0, [
            'cultos' => $reporte->actual->totales()['cultos'],
            'tipos' => $reporte->filtro()->tipos,
            'ingresos' => $total['actual'],
            'ingresos_periodo_anterior' => $total['anterior'],
        ]);
    }
}

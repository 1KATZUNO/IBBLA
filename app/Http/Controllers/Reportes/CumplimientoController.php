<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Ministerio;
use App\Models\Persona;
use App\Services\Reportes\AuditoriaReportes;
use App\Services\Reportes\CumplimientoPromesasService;
use App\Services\Reportes\Exportaciones\CumplimientoExcel;
use App\Services\Reportes\Exportaciones\CumplimientoPdf;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\ReporteCumplimiento;
use App\Support\Reportes\EntradaPeriodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reporte "Cumplimiento de promesas con corte a la fecha" (HU-R13): pantalla, PDF y Excel.
 * Los tres salen del mismo ReporteCumplimiento, asi que dicen lo mismo.
 *
 *   GET /reportes/cumplimiento?corte=2026-10-13&grupo=con_promesa
 *   GET /reportes/cumplimiento/pdf?...&accion=ver|descargar
 *   GET /reportes/cumplimiento/excel?...
 */
class CumplimientoController extends Controller
{
    public function __construct(
        private readonly CumplimientoPromesasService $servicio,
        private readonly AuditoriaReportes $auditoria,
    ) {}

    public function index(Request $request)
    {
        [$reporte, $errores] = $this->reporte($request);

        return view('reportes.cumplimiento', [
            'reporte' => $reporte,
            'resumen' => $reporte->resumen(),
            'errores' => $errores,
            'parametros' => $this->parametros($reporte),
            'ministerios' => Ministerio::where('activo', true)->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre']),
            'personas' => Persona::orderBy('nombre')->get(['id', 'nombre', 'activo']),
        ]);
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        $request->validate(['accion' => 'nullable|in:ver,descargar']);
        [$reporte, $errores] = $this->reporte($request);
        if ($errores->isNotEmpty()) {
            return $this->volverConErrores($request);
        }

        $contenido = app(CumplimientoPdf::class)->generar($reporte);
        $this->auditar($reporte, 'pdf');
        $disposicion = $request->query('accion') === 'descargar' ? 'attachment' : 'inline';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposicion, CumplimientoPdf::nombreArchivo($reporte)),
            'Content-Length' => strlen($contenido),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function excel(Request $request): Response|RedirectResponse
    {
        [$reporte, $errores] = $this->reporte($request);
        if ($errores->isNotEmpty()) {
            return $this->volverConErrores($request);
        }

        $libro = app(CumplimientoExcel::class)->generar($reporte);
        $this->auditar($reporte, 'excel');

        return $libro->descargar(CumplimientoExcel::nombreArchivo($reporte));
    }

    /** @return array{0: ReporteCumplimiento, 1: MessageBag} */
    private function reporte(Request $request): array
    {
        // Este reporte siempre es al corte: solo se lee la fecha de corte (hoy por defecto).
        $entrada = EntradaPeriodo::desde(['periodo' => EntradaPeriodo::CORTE, 'corte' => $request->query('corte')], EntradaPeriodo::CORTE);
        $filtro = FiltroPersonas::desde($request);

        $errores = new MessageBag;
        $errores->merge($entrada->errores);
        $errores->merge($filtro->errores);

        return [$this->servicio->generar($entrada->periodo, $filtro), $errores];
    }

    /** @return array<string, int|string> */
    private function parametros(ReporteCumplimiento $reporte): array
    {
        return ['corte' => $reporte->periodo->corte?->toDateString()] + $reporte->filtro->aParametros();
    }

    /** La pantalla vuelve a leer la misma URL y muestra los mismos motivos junto a los filtros. */
    private function volverConErrores(Request $request): RedirectResponse
    {
        return redirect()->route('reportes.cumplimiento', $request->except('accion'));
    }

    private function auditar(ReporteCumplimiento $reporte, string $formato): void
    {
        $resumen = $reporte->resumen();
        $this->auditoria->registrar('cumplimiento-promesas', $formato, $reporte->periodo, $reporte->filtro, count($reporte->filas), [
            'con_promesa' => $resumen['con_promesa'],
            'al_dia' => $resumen['al_dia'],
            'pendientes' => $resumen['pendientes'],
        ]);
    }
}

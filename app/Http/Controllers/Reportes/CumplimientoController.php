<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Services\Reportes\AuditoriaReportes;
use App\Services\Reportes\CandidatasSelector;
use App\Services\Reportes\CumplimientoPromesasService;
use App\Services\Reportes\EstadoCuenta;
use App\Services\Reportes\Exportaciones\CumplimientoExcel;
use App\Services\Reportes\Exportaciones\CumplimientoPdf;
use App\Services\Reportes\FilaCumplimiento;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\ReporteCumplimiento;
use App\Services\Reportes\VistaPrevia;
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
    /** Pestañas de la vista previa: ?ver= => estado. */
    private const VER = ['todos' => null, 'pendiente' => EstadoCuenta::PENDIENTE, 'al-dia' => EstadoCuenta::AL_DIA, 'sin-promesa' => EstadoCuenta::SIN_PROMESA];

    public function __construct(
        private readonly CumplimientoPromesasService $servicio,
        private readonly AuditoriaReportes $auditoria,
    ) {}

    public function index(Request $request)
    {
        [$reporte, $errores] = $this->reporte($request);

        // Vista previa (HU-R4): las mismas filas del PDF y del Excel, filtradas por estado (?ver=),
        // ordenadas (?orden=&dir=) y paginadas de 100 en 100 (?pagina=).
        $ver = in_array($request->query('ver'), array_keys(self::VER), true) ? $request->query('ver') : 'todos';
        $filas = $ver === 'todos' ? $reporte->filas : array_values(array_filter($reporte->filas, fn (FilaCumplimiento $f) => $f->estado === self::VER[$ver]));
        $ordenEstado = [EstadoCuenta::PENDIENTE => 0, EstadoCuenta::AL_DIA => 1, EstadoCuenta::SIN_PROMESA => 2];
        $vista = new VistaPrevia($filas, [
            'persona' => fn (FilaCumplimiento $f) => VistaPrevia::claveNombre($f->nombre),
            'correspondia' => fn (FilaCumplimiento $f) => $f->tienePromesa() ? $f->leCorrespondeCentimos : null,
            'dado' => fn (FilaCumplimiento $f) => $f->haDadoCentimos,
            'diferencia' => fn (FilaCumplimiento $f) => $f->tienePromesa() ? $f->diferenciaCentimos() : null,
            'estado' => fn (FilaCumplimiento $f) => $ordenEstado[$f->estado],
            'ultimo' => fn (FilaCumplimiento $f) => $f->ultimoAporte?->format('Y-m-d'),
        ], $request, 'estado');

        return view('reportes.cumplimiento', [
            'reporte' => $reporte,
            'resumen' => $reporte->resumen(),
            'errores' => $errores,
            'parametros' => $this->parametros($reporte),
            'selector' => app(CandidatasSelector::class)->datos($reporte->periodo),
            'vista' => $vista,
            'ver' => $ver,
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

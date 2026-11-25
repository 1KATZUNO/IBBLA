<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Services\Reportes\AuditoriaReportes;
use App\Services\Reportes\CandidatasSelector;
use App\Services\Reportes\Dinero;
use App\Services\Reportes\EstadoCuentaLoteService;
use App\Services\Reportes\Exportaciones\EstadoCuentaExcel;
use App\Services\Reportes\Exportaciones\EstadoCuentaPdf;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\LoteEstadosCuenta;
use App\Services\Reportes\VistaPrevia;
use App\Support\Reportes\EntradaPeriodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Estados de cuenta de un grupo de personas (HU-R5): pantalla para elegir periodo y personas, un PDF
 * con portada, indice y una seccion por persona, un ZIP con un PDF por persona y un Excel.
 *
 *   GET /reportes/estados-de-cuenta?periodo=meses&anio=2026&mes_desde=1&mes_hasta=9&grupo=con_promesa
 *   GET /reportes/estados-de-cuenta/pdf?...&accion=ver|descargar
 *   GET /reportes/estados-de-cuenta/zip?...
 *   GET /reportes/estados-de-cuenta/excel?...
 */
class EstadosCuentaController extends Controller
{
    public function __construct(
        private readonly EstadoCuentaLoteService $servicio,
        private readonly AuditoriaReportes $auditoria,
    ) {}

    public function index(Request $request)
    {
        [$entrada, $filtro, $errores] = $this->entrada($request);
        $lote = $this->servicio->generar($entrada->periodo, $filtro);

        // Vista previa (HU-R4): una fila por persona con las cifras del lote (las del PDF y el Excel),
        // ordenable y paginada de 100 en 100; los totales son de todo el lote, no de la pagina.
        $filas = [];
        $totales = ['contribuido' => 0, 'correspondia' => 0, 'dado' => 0, 'diferencia' => 0];
        foreach ($lote->estados as $datos) {
            $ultimo = $lote->resultado->cuenta($datos['persona']->id)?->ultimoAporteACompromisos();
            $filas[] = $datos + ['ultimo_aporte' => $ultimo];
            $totales['contribuido'] += Dinero::centimos($datos['total_dado']);
            if ($datos['tiene_compromisos']) {
                $totales['correspondia'] += Dinero::centimos($datos['total_esperado']);
                $totales['dado'] += Dinero::centimos($datos['dado_en_compromisos']);
                $totales['diferencia'] += Dinero::centimos($datos['diferencia']);
            }
        }
        $ordenEstado = ['PENDIENTE' => 0, 'AL DÍA' => 1, 'SIN PROMESA' => 2];
        $vista = new VistaPrevia($filas, [
            'persona' => fn (array $f) => VistaPrevia::claveNombre($f['persona']->nombre),
            'contribuido' => fn (array $f) => Dinero::centimos($f['total_dado']),
            'correspondia' => fn (array $f) => $f['tiene_compromisos'] ? Dinero::centimos($f['total_esperado']) : null,
            'dado' => fn (array $f) => $f['tiene_compromisos'] ? Dinero::centimos($f['dado_en_compromisos']) : null,
            'diferencia' => fn (array $f) => $f['tiene_compromisos'] ? Dinero::centimos($f['diferencia']) : null,
            'estado' => fn (array $f) => $ordenEstado[$f['tiene_compromisos'] ? ($f['al_dia'] ? 'AL DÍA' : 'PENDIENTE') : 'SIN PROMESA'],
            'ultimo' => fn (array $f) => $f['ultimo_aporte']?->format('Y-m-d'),
        ], $request, 'persona');

        return view('reportes.estados-cuenta', [
            'lote' => $lote,
            'entrada' => $entrada,
            'errores' => $errores,
            'parametros' => $entrada->aParametros() + $filtro->aParametros(),
            'selector' => app(CandidatasSelector::class)->datos($lote->periodo),
            'vista' => $vista,
            'totales' => array_map(fn (int $c) => Dinero::colones($c), $totales),
            'maxZip' => EstadoCuentaPdf::MAX_ZIP,
        ]);
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        $request->validate(['accion' => 'nullable|in:ver,descargar']);
        [$entrada, $filtro, $errores] = $this->entrada($request);
        if ($errores->isNotEmpty()) {
            return $this->volver($request);
        }

        $lote = $this->servicio->generar($entrada->periodo, $filtro);
        $contenido = app(EstadoCuentaPdf::class)->lote($lote);
        $this->auditar($lote, 'pdf');

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($request->query('accion') === 'descargar' ? 'attachment' : 'inline', EstadoCuentaPdf::nombreArchivo($lote)),
            'Content-Length' => strlen($contenido),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function zip(Request $request): Response|RedirectResponse
    {
        [$entrada, $filtro, $errores] = $this->entrada($request);
        if ($errores->isNotEmpty()) {
            return $this->volver($request);
        }

        $lote = $this->servicio->generar($entrada->periodo, $filtro);
        if ($lote->cantidad() === 0) {
            return $this->volver($request, 'No hay personas en ese filtro, así que no hay nada que exportar. Cambie el grupo o la selección.');
        }
        if ($lote->cantidad() > EstadoCuentaPdf::MAX_ZIP) {
            return $this->volver($request, 'El ZIP admite hasta '.EstadoCuentaPdf::MAX_ZIP.' personas y el filtro tiene '.$lote->cantidad().'. Use el PDF en un solo archivo o elija menos personas.');
        }

        $ruta = app(EstadoCuentaPdf::class)->zip($lote);
        $this->auditar($lote, 'zip');

        return response()->download($ruta, EstadoCuentaPdf::nombreArchivo($lote, 'zip'), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store, max-age=0',
        ])->deleteFileAfterSend();
    }

    public function excel(Request $request): Response|RedirectResponse
    {
        [$entrada, $filtro, $errores] = $this->entrada($request);
        if ($errores->isNotEmpty()) {
            return $this->volver($request);
        }

        $lote = $this->servicio->generar($entrada->periodo, $filtro, conDetalle: true);
        $libro = app(EstadoCuentaExcel::class)->generar($lote);
        $this->auditar($lote, 'excel');

        return $libro->descargar(EstadoCuentaExcel::nombreArchivo($lote));
    }

    /** @return array{0: EntradaPeriodo, 1: FiltroPersonas, 2: MessageBag} */
    private function entrada(Request $request): array
    {
        $entrada = EntradaPeriodo::desde($request, EntradaPeriodo::MESES);
        $filtro = FiltroPersonas::desde($request);

        $errores = new MessageBag;
        $errores->merge($entrada->errores);
        $errores->merge($filtro->errores);

        return [$entrada, $filtro, $errores];
    }

    /** La pantalla vuelve a leer la misma URL y muestra los motivos junto a los filtros. */
    private function volver(Request $request, ?string $mensaje = null): RedirectResponse
    {
        $redireccion = redirect()->route('reportes.estados-cuenta', $request->except('accion'));

        return $mensaje === null ? $redireccion : $redireccion->with('error', $mensaje);
    }

    private function auditar(LoteEstadosCuenta $lote, string $formato): void
    {
        $this->auditoria->registrar('estados-de-cuenta', $formato, $lote->periodo, $lote->filtro, $lote->cantidad());
    }
}

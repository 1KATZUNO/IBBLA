<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Ministerio;
use App\Models\Persona;
use App\Services\Reportes\AuditoriaReportes;
use App\Services\Reportes\EstadoCuentaLoteService;
use App\Services\Reportes\Exportaciones\EstadoCuentaExcel;
use App\Services\Reportes\Exportaciones\EstadoCuentaPdf;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\LoteEstadosCuenta;
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

        return view('reportes.estados-cuenta', [
            'lote' => $lote,
            'entrada' => $entrada,
            'errores' => $errores,
            'parametros' => $entrada->aParametros() + $filtro->aParametros(),
            'ministerios' => Ministerio::where('activo', true)->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre']),
            'personas' => Persona::orderBy('nombre')->get(['id', 'nombre', 'activo']),
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

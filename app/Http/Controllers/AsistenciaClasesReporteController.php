<?php

namespace App\Http\Controllers;

use App\Exports\AsistenciaPorClaseExport;
use App\Models\ClaseAsistencia;
use App\Services\ReporteAsistenciaClasesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidadorDeLaravel;
use Maatwebsite\Excel\Excel as FormatoExcel;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reporte de asistencia por clase en un rango de fechas: pantalla, PDF, CSV y Excel.
 * Los cuatro salen del mismo servicio, así que el promedio es siempre el mismo (ver ReporteAsistenciaClasesService).
 */
class AsistenciaClasesReporteController extends Controller
{
    public function __construct(private readonly ReporteAsistenciaClasesService $servicio) {}

    public function index(Request $request)
    {
        $validador = $this->validador($request);

        if ($validador->fails()) {
            // Se muestra la pantalla con el rango por defecto y el motivo del error, sin redirigir
            [$desde, $hasta] = ReporteAsistenciaClasesService::rangoPorDefecto();

            return $this->pantalla([
                'desde' => $desde,
                'hasta' => $hasta,
                'clase_id' => null,
                'incluir_ceros' => false,
            ])->withErrors($validador);
        }

        return $this->pantalla($this->filtros($validador));
    }

    public function pdf(Request $request)
    {
        $filtros = $this->filtrosOError($request);
        if ($filtros instanceof RedirectResponse) {
            return $filtros;
        }

        $pdf = Pdf::loadView('pdfs.asistencia-clases-rango', ['reporte' => $this->reporte($filtros)]);

        return $pdf->download($this->nombreArchivo($filtros, 'pdf'));
    }

    public function csv(Request $request)
    {
        $filtros = $this->filtrosOError($request);
        if ($filtros instanceof RedirectResponse) {
            return $filtros;
        }

        return Excel::download(
            new AsistenciaPorClaseExport($this->reporte($filtros), paraCsv: true),
            $this->nombreArchivo($filtros, 'csv'),
            FormatoExcel::CSV,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function excel(Request $request)
    {
        $filtros = $this->filtrosOError($request);
        if ($filtros instanceof RedirectResponse) {
            return $filtros;
        }

        return Excel::download(
            new AsistenciaPorClaseExport($this->reporte($filtros)),
            $this->nombreArchivo($filtros, 'xlsx'),
        );
    }

    /**
     * @param  array{desde: Carbon, hasta: Carbon, clase_id: ?int, incluir_ceros: bool}  $filtros
     */
    private function pantalla(array $filtros)
    {
        return view('ingresos-asistencia.asistencia-clases', [
            'reporte' => $this->reporte($filtros),
            'filtros' => $filtros,
            // Se incluyen las clases inactivas: pueden tener historia dentro del rango
            'clases' => ClaseAsistencia::regulares()->orderByDesc('activa')->ordenadas()->get(),
            'descarga' => array_filter([
                'desde' => $filtros['desde']->toDateString(),
                'hasta' => $filtros['hasta']->toDateString(),
                'clase_id' => $filtros['clase_id'],
                'incluir_ceros' => $filtros['incluir_ceros'] ? 1 : null,
            ], fn ($valor) => $valor !== null),
        ]);
    }

    /**
     * @param  array{desde: Carbon, hasta: Carbon, clase_id: ?int, incluir_ceros: bool}  $filtros
     */
    private function reporte(array $filtros): array
    {
        return $this->servicio->generar($filtros['desde'], $filtros['hasta'], $filtros['clase_id'], $filtros['incluir_ceros']);
    }

    /**
     * Filtros ya validados o, si no sirven, una redirección a la pantalla con el motivo.
     *
     * @return array{desde: Carbon, hasta: Carbon, clase_id: ?int, incluir_ceros: bool}|RedirectResponse
     */
    private function filtrosOError(Request $request): array|RedirectResponse
    {
        $validador = $this->validador($request);

        if ($validador->fails()) {
            return redirect()->route('ingresos-asistencia.asistencia-clases')->withErrors($validador);
        }

        return $this->filtros($validador);
    }

    /**
     * Sin fechas en la consulta se usan los últimos dos meses; el resto se valida.
     */
    private function validador(Request $request): ValidadorDeLaravel
    {
        [$desde, $hasta] = ReporteAsistenciaClasesService::rangoPorDefecto();

        $validador = Validator::make([
            'desde' => $request->query('desde') ?: $desde->toDateString(),
            'hasta' => $request->query('hasta') ?: $hasta->toDateString(),
            'clase_id' => $request->query('clase_id') ?: null,
            'incluir_ceros' => $request->query('incluir_ceros') ?: null,
        ], [
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'clase_id' => ['nullable', 'integer'],
            'incluir_ceros' => ['nullable', 'boolean'],
        ], [
            'desde.date_format' => 'La fecha «Desde» no es válida.',
            'hasta.date_format' => 'La fecha «Hasta» no es válida.',
            'hasta.after_or_equal' => 'La fecha «Hasta» no puede ser anterior a «Desde».',
            'clase_id.integer' => 'La clase elegida no es válida.',
            'incluir_ceros.boolean' => 'La opción de contar fechas en 0 no es válida.',
        ]);

        // exists:clases_asistencia no pasa por el filtro de iglesia del modelo; se consulta con él
        $validador->after(function (ValidadorDeLaravel $validador) {
            $claseId = $validador->getData()['clase_id'] ?? null;

            if ($claseId !== null && ! $validador->errors()->has('clase_id')
                && ! ClaseAsistencia::regulares()->whereKey((int) $claseId)->exists()) {
                $validador->errors()->add('clase_id', 'La clase elegida no existe.');
            }
        });

        return $validador;
    }

    /**
     * @return array{desde: Carbon, hasta: Carbon, clase_id: ?int, incluir_ceros: bool}
     */
    private function filtros(ValidadorDeLaravel $validador): array
    {
        $datos = $validador->validated();

        return [
            'desde' => Carbon::createFromFormat('Y-m-d', $datos['desde'])->startOfDay(),
            'hasta' => Carbon::createFromFormat('Y-m-d', $datos['hasta'])->startOfDay(),
            'clase_id' => isset($datos['clase_id']) ? (int) $datos['clase_id'] : null,
            'incluir_ceros' => (bool) ($datos['incluir_ceros'] ?? false),
        ];
    }

    /**
     * @param  array{desde: Carbon, hasta: Carbon, clase_id: ?int, incluir_ceros: bool}  $filtros
     */
    private function nombreArchivo(array $filtros, string $extension): string
    {
        return sprintf(
            'asistencia_por_clase_%s_a_%s.%s',
            $filtros['desde']->format('Y-m-d'),
            $filtros['hasta']->format('Y-m-d'),
            $extension,
        );
    }
}

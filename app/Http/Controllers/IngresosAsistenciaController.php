<?php

namespace App\Http\Controllers;

use App\Models\Culto;
use App\Services\Reportes\Exportaciones\AsistenciaExcel;
use App\Services\Reportes\Exportaciones\AsistenciaPdf;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\PeriodoReporte;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class IngresosAsistenciaController extends Controller
{
    /**
     * Build a registro row from raw sobres (transferencias) using dynamic categories.
     */
    private function buildRegistroFromSobres($sobres, array $slugs, array $extra = []): array
    {
        $row = $extra;
        foreach ($slugs as $slug) {
            $row[$slug] = $sobres->flatMap->detalles->where('categoria', $slug)->sum('monto');
        }
        $row['suelto'] = 0;
        $row['total'] = $sobres->flatMap->detalles->sum('monto');

        return $row;
    }

    public function index()
    {
        $categories = tenant_categories();
        $slugs = $categories->pluck('slug')->toArray();

        // Totales de la semana
        $inicioSemana = Carbon::now()->startOfWeek();
        $finSemana = Carbon::now()->endOfWeek();

        $cultosSemanales = Culto::whereBetween('fecha', [$inicioSemana, $finSemana])
            ->with(['totales', 'asistencia'])
            ->orderBy('fecha', 'desc')
            ->get();

        $totalSemanal = $cultosSemanales->sum(fn ($c) => $c->totales ? $c->totales->total_general : 0);

        // Distribución por categorías - dynamic
        $categorias = [];
        foreach ($slugs as $slug) {
            $categorias[$slug] = $cultosSemanales->sum(fn ($c) => $c->totales ? $c->totales->getCategoryTotal($slug) : 0);
        }
        $categorias['suelto'] = $cultosSemanales->sum(fn ($c) => $c->totales ? $c->totales->total_suelto : 0);

        return view('ingresos-asistencia.index', compact('cultosSemanales', 'totalSemanal', 'categorias', 'categories'));
    }

    public function asistencia(Request $request)
    {
        $query = Culto::with(['asistencia.detallesClases.claseAsistencia'])->orderBy('fecha', 'desc');

        // Filtro por mes
        if ($request->filled('mes') && $request->mes !== 'todos') {
            $query->whereMonth('fecha', $request->mes);
        }

        // Filtro por año
        if ($request->filled('año') && $request->año !== 'todos') {
            $query->whereYear('fecha', $request->año);
        }

        // Filtro por rango de fechas
        if ($request->filled('fecha_inicio')) {
            $query->where('fecha', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->where('fecha', '<=', $request->fecha_fin);
        }

        $cultos = $query->get();

        // Obtener meses disponibles
        $mesesDisponibles = Culto::selectRaw('MONTH(fecha) as numero, YEAR(fecha) as año')
            ->groupBy('numero', 'año')
            ->orderBy('año', 'desc')
            ->orderBy('numero', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'numero' => $item->numero,
                    'año' => $item->año,
                    'nombre' => Carbon::createFromDate($item->año, $item->numero, 1)->locale('es')->translatedFormat('F'),
                ];
            });

        // Obtener años únicos
        $añosDisponibles = Culto::selectRaw('YEAR(fecha) as año')
            ->groupBy('año')
            ->orderBy('año', 'desc')
            ->pluck('año');

        return view('ingresos-asistencia.asistencia', compact('cultos', 'mesesDisponibles', 'añosDisponibles'));
    }

    /**
     * Pantalla de ingresos vieja (por culto, semana o mes): los enlaces y marcadores viejos llevan al
     * Reporte de ingresos nuevo (HU-R11) con las mismas fechas; el detalle por culto esta en el
     * Reporte de cultos.
     */
    public function ingresos(Request $request)
    {
        return redirect()->route('reportes.ingresos', $this->parametrosIngresos($request));
    }

    /**
     * Fechas de los enlaces viejos (?fecha_inicio=&fecha_fin=, cualquiera de las dos puede faltar)
     * en el formato del reporte nuevo. Sin fechas, el reporte nuevo muestra el mes actual.
     *
     * @return array<string, string>
     */
    private function parametrosIngresos(Request $request): array
    {
        $fecha = fn (string $campo) => is_string($request->query($campo)) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->query($campo)) ? $request->query($campo) : null;
        $inicio = $fecha('fecha_inicio');
        $fin = $fecha('fecha_fin');
        if ($inicio === null && $fin === null) {
            return [];
        }

        return [
            'fecha_inicio' => $inicio ?? Carbon::parse($fin)->startOfYear()->toDateString(),
            'fecha_fin' => $fin ?? min(Carbon::parse($inicio)->endOfYear(), Carbon::today())->toDateString(),
        ];
    }

    public function pdfAsistencia(Request $request)
    {
        $query = Culto::with(['asistencia.detallesClases.claseAsistencia', 'asistencia.registrosExtra.tipo'])->orderBy('fecha', 'asc');

        if ($request->filled('fecha_inicio')) {
            $query->where('fecha', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->where('fecha', '<=', $request->fecha_fin);
        }

        $cultos = $query->with('serviceType')->get();
        $registroExtraTipos = \App\Models\RegistroExtraTipo::activos()->ordenados()->get();

        // Plantilla unica (HU-R14): mismas cifras que el PDF anterior, con encabezado, totales y pie
        $contenido = app(AsistenciaPdf::class)->generar($cultos, $registroExtraTipos, 'Reporte de asistencia', $this->periodoAsistencia($request));
        $fechaInicio = $request->filled('fecha_inicio') ? Carbon::parse($request->fecha_inicio) : ($cultos->first()?->fecha ?? now());
        $nombreArchivo = 'asistencia_'.$fechaInicio->locale('es')->isoFormat('dddd_D-M-Y');

        return $this->descargaPdf($contenido, $nombreArchivo.'.pdf');
    }

    private function descargaPdf(string $contenido, string $nombre): \Symfony\Component\HttpFoundation\Response
    {
        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition('attachment', $nombre, \Illuminate\Support\Str::ascii($nombre)),
            'Content-Length' => strlen($contenido),
        ]);
    }

    public function pdfAsistenciaCulto(Culto $culto)
    {
        $culto->load(['asistencia.detallesClases.claseAsistencia']);
        $pdf = Pdf::loadView('pdfs.asistencia-culto', compact('culto'));
        $nombreArchivo = 'asistencia_'.$culto->fecha->locale('es')->isoFormat('dddd_D-M-Y');

        return $pdf->download($nombreArchivo.'.pdf');
    }

    public function pdfAsistenciaMes(Request $request)
    {
        $mes = $request->get('mes');
        $año = $request->get('año');

        $cultos = Culto::with(['asistencia.detallesClases.claseAsistencia', 'asistencia.registrosExtra.tipo', 'serviceType'])
            ->whereYear('fecha', $año)
            ->whereMonth('fecha', $mes)
            ->orderBy('fecha', 'asc')
            ->get();

        $nombreMes = Carbon::createFromDate($año, $mes, 1)->locale('es')->translatedFormat('F');
        $registroExtraTipos = \App\Models\RegistroExtraTipo::activos()->ordenados()->get();

        // Plantilla unica (HU-R14): mismas cifras que el PDF anterior (pdfs/asistencia-mes)
        $contenido = app(AsistenciaPdf::class)->generar($cultos, $registroExtraTipos, 'Reporte de asistencia - '.ucfirst($nombreMes).' '.$año, ucfirst($nombreMes).' '.$año);

        return $this->descargaPdf($contenido, 'asistencia_'.$nombreMes.'_'.$año.'.pdf');
    }

    /**
     * Excel de asistencias (Fase 2).
     * Roy usa este Excel para gráficos propios y comparativos entre meses.
     */
    public function excelAsistencia(Request $request)
    {
        $query = Culto::with(['asistencia.detallesClases.claseAsistencia', 'asistencia.registrosExtra.tipo'])
            ->orderBy('fecha', 'asc');

        if ($request->filled('fecha_inicio')) {
            $query->where('fecha', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->where('fecha', '<=', $request->fecha_fin);
        }

        $cultos = $query->with('serviceType')->get();
        $registroExtraTipos = \App\Models\RegistroExtraTipo::activos()->ordenados()->get();
        $fechaInicio = $request->filled('fecha_inicio') ? Carbon::parse($request->fecha_inicio) : ($cultos->first()?->fecha ?? now());
        $nombreArchivo = 'asistencia_'.$fechaInicio->locale('es')->isoFormat('YYYY-MM-DD').'.xlsx';

        // Plantilla unica (HU-R14): mismas cifras que el Excel anterior
        return app(AsistenciaExcel::class)->generar($cultos, $registroExtraTipos, 'Reporte de asistencia', $this->periodoAsistencia($request))->descargar($nombreArchivo);
    }

    /** "Del 01/08/2026 al 31/08/2026" (o lo que se haya filtrado) para el encabezado de PDF y Excel. */
    private function periodoAsistencia(Request $request): string
    {
        return match (true) {
            $request->filled('fecha_inicio') && $request->filled('fecha_fin') => 'Del '.Carbon::parse($request->fecha_inicio)->format('d/m/Y').' al '.Carbon::parse($request->fecha_fin)->format('d/m/Y'),
            $request->filled('fecha_inicio') => 'Desde el '.Carbon::parse($request->fecha_inicio)->format('d/m/Y'),
            $request->filled('fecha_fin') => 'Hasta el '.Carbon::parse($request->fecha_fin)->format('d/m/Y'),
            default => 'Todos los cultos',
        };
    }

    public function excelAsistenciaMes(Request $request)
    {
        $mes = (int) $request->get('mes');
        $año = (int) $request->get('año');
        $cultos = Culto::with(['asistencia.detallesClases.claseAsistencia', 'asistencia.registrosExtra.tipo', 'serviceType'])
            ->whereYear('fecha', $año)
            ->whereMonth('fecha', $mes)
            ->orderBy('fecha', 'asc')
            ->get();
        $nombreMes = Carbon::createFromDate($año, $mes, 1)->locale('es')->translatedFormat('F');
        $registroExtraTipos = \App\Models\RegistroExtraTipo::activos()->ordenados()->get();

        // Plantilla unica (HU-R14): mismas cifras que el Excel anterior
        return app(AsistenciaExcel::class)
            ->generar($cultos, $registroExtraTipos, 'Reporte de asistencia - '.ucfirst($nombreMes).' '.$año, ucfirst($nombreMes).' '.$año)
            ->descargar('asistencia_'.strtolower($nombreMes).'_'.$año.'.xlsx');
    }

    /** PDF de ingresos viejo: ahora es el PDF del Reporte de ingresos (HU-R11), mismas fechas. */
    public function pdfIngresos(Request $request)
    {
        return redirect()->route('reportes.ingresos.pdf', $this->parametrosIngresos($request) + ['accion' => $request->query('accion') === 'descargar' ? 'descargar' : 'ver']);
    }

    /**
     * Excel de ingresos viejo: ahora es el Excel del Reporte de ingresos (HU-R11), mismas fechas.
     */
    public function excelIngresos(Request $request)
    {
        return redirect()->route('reportes.ingresos.excel', $this->parametrosIngresos($request));
    }

    /**
     * Excel de promesas: desde la E1 sale del motor unico (QE-002). Es el Excel de Cumplimiento de
     * promesas con corte al ultimo dia del anio o del mes pedido (nunca despues de hoy), asi que dice
     * lo mismo que el estado de cuenta, Compromisos y el Reporte General.
     */
    public function excelPromesas(Request $request)
    {
        $periodo = PeriodoReporte::alFinDe(
            $request->filled('año') ? (int) $request->get('año') : null,
            $request->filled('mes') ? (int) $request->get('mes') : null,
        );

        return redirect()->route('reportes.cumplimiento.excel', [
            'corte' => $periodo->corte->toDateString(),
            'grupo' => FiltroPersonas::CON_PROMESA,
        ]);
    }

    public function pdfIngresosTransferencias(Request $request)
    {
        $categories = tenant_categories();
        $slugs = $categories->pluck('slug')->toArray();
        $tipoReporte = $request->get('tipo_reporte', 'culto');
        $query = Culto::with(['sobres' => function ($q) {
            $q->where('metodo_pago', 'transferencia');
        }, 'totales'])->orderBy('fecha', 'asc');

        if ($request->filled('fecha_inicio')) {
            $query->where('fecha', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->where('fecha', '<=', $request->fecha_fin);
        }

        $cultos = $query->get();
        $registros = [];

        if ($tipoReporte == 'culto') {
            foreach ($cultos as $culto) {
                $registros[] = $this->buildRegistroFromSobres($culto->sobres ?? collect(), $slugs, [
                    'fecha' => $culto->fecha->format('d/m/Y'),
                    'tipo' => ucfirst($culto->tipo_culto),
                ]);
            }
        } elseif ($tipoReporte == 'semana') {
            $semanas = $cultos->groupBy(fn ($culto) => $culto->fecha->startOfWeek()->format('d/m/Y'));
            foreach ($semanas as $semana => $cultosSemana) {
                $registros[] = $this->buildRegistroFromSobres($cultosSemana->flatMap->sobres, $slugs, [
                    'fecha' => 'Semana del '.$semana,
                    'tipo' => 'Semanal',
                ]);
            }
        } elseif ($tipoReporte == 'mes') {
            $meses = $cultos->groupBy(fn ($culto) => $culto->fecha->format('Y-m'));
            foreach ($meses as $mes => $cultosMes) {
                $fecha = Carbon::parse($mes.'-01');
                $registros[] = $this->buildRegistroFromSobres($cultosMes->flatMap->sobres, $slugs, [
                    'fecha' => $fecha->locale('es')->translatedFormat('F Y'),
                    'tipo' => 'Mensual',
                ]);
            }
        }

        // Agregar tesoreros por fecha a pie de página
        $tesorerosPorFecha = [];
        foreach ($cultos as $c) {
            $fecha = $c->fecha->format('d/m/Y');
            $nombres = [];
            if (is_array($c->firmas_tesoreros)) {
                $nombres = array_filter($c->firmas_tesoreros);
            } elseif (is_string($c->firmas_tesoreros) && ! empty($c->firmas_tesoreros)) {
                $decoded = json_decode($c->firmas_tesoreros, true);
                if (is_array($decoded)) {
                    $nombres = array_filter($decoded);
                }
            }
            if (! empty($nombres)) {
                $tesorerosPorFecha[$fecha] = isset($tesorerosPorFecha[$fecha])
                    ? array_values(array_unique(array_merge($tesorerosPorFecha[$fecha], $nombres)))
                    : array_values(array_unique($nombres));
            }
        }
        // Ordenar por fecha ascendente si hay múltiples días
        if (! empty($tesorerosPorFecha)) {
            uksort($tesorerosPorFecha, function ($a, $b) {
                $da = \Carbon\Carbon::createFromFormat('d/m/Y', $a);
                $db = \Carbon\Carbon::createFromFormat('d/m/Y', $b);

                return $da <=> $db;
            });
        }

        $pdf = Pdf::loadView('pdfs.ingresos', ['registros' => $registros, 'tipoReporte' => $tipoReporte, 'soloTransferencias' => true, 'tesorerosPorFecha' => $tesorerosPorFecha, 'categories' => $categories]);

        return $pdf->download('ingresos_transferencias_'.$tipoReporte.'_'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * PDF de recuento de un culto (el que firman los tesoreros). Usa los mismos numeros que la
     * pantalla del culto (App\Support\ResumenCulto): todo en colones convertidos, USD aparte entre
     * parentesis y un solo total en cabecera, tabla y resumen (QA-026).
     */
    public function pdfRecuentoIndividual(Culto $culto)
    {
        return $this->pdfRecuento($culto, false)
            ->download('recuento_'.$culto->fecha->format('Y-m-d').'_'.$culto->tipo_culto.'.pdf');
    }

    public function pdfRecuentoTransferencias(Culto $culto)
    {
        return $this->pdfRecuento($culto, true)
            ->download('recuento_transferencias_'.$culto->fecha->format('Y-m-d').'_'.$culto->tipo_culto.'.pdf');
    }

    private function pdfRecuento(Culto $culto, bool $transferenciasOnly)
    {
        $culto->load(['sobres.persona', 'sobres.detalles', 'ofrendasSueltas', 'egresos', 'totales', 'cerradoPor:id,name']);
        $categories = tenant_categories();
        $categoriasSobre = $categories->where('es_ofrenda_suelta', false)->values();

        $sobres = $culto->sobres->sortBy('numero_sobre')->values();
        if ($transferenciasOnly) {
            $sobres = $sobres->where('metodo_pago', 'transferencia')->values();
        }

        $resumen = \App\Support\ResumenCulto::calcular(
            $sobres,
            $transferenciasOnly ? collect() : $culto->ofrendasSueltas,
            $transferenciasOnly ? collect() : $culto->egresos,
            $categoriasSobre,
            \App\Support\ResumenCulto::tipoCambioDelCulto($culto),
        );

        return Pdf::loadView('pdfs.recuento-individual', [
            'culto' => $culto,
            'categories' => $categoriasSobre,
            'resumen' => $resumen,
            'transferenciasOnly' => $transferenciasOnly,
        ]);
    }
}

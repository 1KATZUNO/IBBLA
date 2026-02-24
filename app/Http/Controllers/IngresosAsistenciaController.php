<?php

namespace App\Http\Controllers;

use App\Models\Culto;
use App\Models\Asistencia;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class IngresosAsistenciaController extends Controller
{
    /**
     * Build a registro row from a TotalesCulto using dynamic categories.
     */
    private function buildRegistroFromTotales($totales, array $slugs, array $extra = []): array
    {
        $row = $extra;
        foreach ($slugs as $slug) {
            $row[$slug] = $totales ? $totales->getCategoryTotal($slug) : 0;
        }
        $row['suelto'] = $totales->total_suelto ?? 0;
        $row['total'] = $totales->total_general ?? 0;
        return $row;
    }

    /**
     * Build a registro row by summing a group of cultos using dynamic categories.
     */
    private function buildRegistroFromGroup($cultosGroup, array $slugs, array $extra = []): array
    {
        $row = $extra;
        foreach ($slugs as $slug) {
            $row[$slug] = $cultosGroup->sum(fn($c) => $c->totales ? $c->totales->getCategoryTotal($slug) : 0);
        }
        $row['suelto'] = $cultosGroup->sum('totales.total_suelto');
        $row['total'] = $cultosGroup->sum('totales.total_general');
        return $row;
    }

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

        $totalSemanal = $cultosSemanales->sum(fn($c) => $c->totales ? $c->totales->total_general : 0);

        // Distribución por categorías - dynamic
        $categorias = [];
        foreach ($slugs as $slug) {
            $categorias[$slug] = $cultosSemanales->sum(fn($c) => $c->totales ? $c->totales->getCategoryTotal($slug) : 0);
        }
        $categorias['suelto'] = $cultosSemanales->sum(fn($c) => $c->totales ? $c->totales->total_suelto : 0);

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
            ->map(function($item) {
                return [
                    'numero' => $item->numero,
                    'año' => $item->año,
                    'nombre' => Carbon::createFromDate($item->año, $item->numero, 1)->locale('es')->translatedFormat('F')
                ];
            });

        // Obtener años únicos
        $añosDisponibles = Culto::selectRaw('YEAR(fecha) as año')
            ->groupBy('año')
            ->orderBy('año', 'desc')
            ->pluck('año');

        return view('ingresos-asistencia.asistencia', compact('cultos', 'mesesDisponibles', 'añosDisponibles'));
    }

    public function ingresos(Request $request)
    {
        $categories = tenant_categories();
        $slugs = $categories->pluck('slug')->toArray();
        $tipoReporte = $request->get('tipo_reporte', 'culto');
        $query = Culto::with(['totales', 'sobres.detalles', 'ofrendasSueltas'])->orderBy('fecha', 'desc');

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
                $t = $culto->totales;
                if ($t) {
                    $registros[] = $this->buildRegistroFromTotales($t, $slugs, [
                        'culto_id' => $culto->id,
                        'fecha' => $culto->fecha->format('d/m/Y'),
                        'tipo' => ucfirst($culto->tipo_culto),
                    ]);
                } else {
                    // Calcular en vivo desde sobres y suelto para cultos sin totales
                    $row = [
                        'culto_id' => $culto->id,
                        'fecha' => $culto->fecha->format('d/m/Y'),
                        'tipo' => ucfirst($culto->tipo_culto),
                    ];
                    foreach ($slugs as $slug) {
                        $row[$slug] = $culto->sobres->flatMap->detalles->where('categoria', $slug)->sum('monto');
                    }
                    $row['suelto'] = $culto->ofrendasSueltas->sum('monto');
                    $row['total'] = $culto->sobres->flatMap->detalles->sum('monto') + $row['suelto'];
                    $registros[] = $row;
                }
            }
        } elseif ($tipoReporte == 'semana') {
            $semanas = $cultos->groupBy(fn($culto) => $culto->fecha->startOfWeek()->format('d/m/Y'));
            foreach ($semanas as $semana => $cultosSeamana) {
                $registros[] = $this->buildRegistroFromGroup($cultosSeamana, $slugs, [
                    'fecha' => 'Semana del ' . $semana,
                    'tipo' => 'Semanal',
                ]);
            }
        } elseif ($tipoReporte == 'mes') {
            $meses = $cultos->groupBy(fn($culto) => $culto->fecha->format('Y-m'));
            foreach ($meses as $mes => $cultosMes) {
                $fecha = Carbon::parse($mes . '-01');
                $registros[] = $this->buildRegistroFromGroup($cultosMes, $slugs, [
                    'fecha' => $fecha->locale('es')->translatedFormat('F Y'),
                    'tipo' => 'Mensual',
                ]);
            }
        }

        return view('ingresos-asistencia.ingresos', compact('registros', 'categories'));
    }

    public function pdfAsistencia(Request $request)
    {
        $query = Culto::with(['asistencia.detallesClases.claseAsistencia'])->orderBy('fecha', 'asc');

        if ($request->filled('fecha_inicio')) {
            $query->where('fecha', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->where('fecha', '<=', $request->fecha_fin);
        }

        $cultos = $query->get();

        $pdf = Pdf::loadView('pdfs.asistencia', compact('cultos'));
        $fechaInicio = $request->filled('fecha_inicio') ? Carbon::parse($request->fecha_inicio) : ($cultos->first()?->fecha ?? now());
        $nombreArchivo = 'asistencia_' . $fechaInicio->locale('es')->isoFormat('dddd_D-M-Y');
        return $pdf->download($nombreArchivo . '.pdf');
    }

    public function pdfAsistenciaCulto(Culto $culto)
    {
        $culto->load(['asistencia.detallesClases.claseAsistencia']);
        $pdf = Pdf::loadView('pdfs.asistencia-culto', compact('culto'));
        $nombreArchivo = 'asistencia_' . $culto->fecha->locale('es')->isoFormat('dddd_D-M-Y');
        return $pdf->download($nombreArchivo . '.pdf');
    }

    public function pdfAsistenciaMes(Request $request)
    {
        $mes = $request->get('mes');
        $año = $request->get('año');

        $cultos = Culto::with(['asistencia.detallesClases.claseAsistencia'])
            ->whereYear('fecha', $año)
            ->whereMonth('fecha', $mes)
            ->orderBy('fecha', 'asc')
            ->get();

        $nombreMes = Carbon::createFromDate($año, $mes, 1)->locale('es')->translatedFormat('F');

        $pdf = Pdf::loadView('pdfs.asistencia-mes', compact('cultos', 'nombreMes', 'año'));
        return $pdf->download('asistencia_' . $nombreMes . '_' . $año . '.pdf');
    }

    public function pdfIngresos(Request $request)
    {
        $categories = tenant_categories();
        $slugs = $categories->pluck('slug')->toArray();
        $tipoReporte = $request->get('tipo_reporte', 'culto');
        $query = Culto::with('totales')->orderBy('fecha', 'asc');

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
                $registros[] = $this->buildRegistroFromTotales($culto->totales, $slugs, [
                    'fecha' => $culto->fecha->format('d/m/Y'),
                    'tipo' => ucfirst($culto->tipo_culto),
                ]);
            }
        } elseif ($tipoReporte == 'semana') {
            $semanas = $cultos->groupBy(fn($culto) => $culto->fecha->startOfWeek()->format('d/m/Y'));
            foreach ($semanas as $semana => $cultosSeamana) {
                $registros[] = $this->buildRegistroFromGroup($cultosSeamana, $slugs, [
                    'fecha' => 'Semana del ' . $semana,
                    'tipo' => 'Semanal',
                ]);
            }
        } elseif ($tipoReporte == 'mes') {
            $meses = $cultos->groupBy(fn($culto) => $culto->fecha->format('Y-m'));
            foreach ($meses as $mes => $cultosMes) {
                $fecha = Carbon::parse($mes . '-01');
                $registros[] = $this->buildRegistroFromGroup($cultosMes, $slugs, [
                    'fecha' => $fecha->locale('es')->translatedFormat('F Y'),
                    'tipo' => 'Mensual',
                ]);
            }
        }

        $pdf = Pdf::loadView('pdfs.ingresos', compact('registros', 'tipoReporte', 'categories'));
        return $pdf->download('ingresos_' . $tipoReporte . '_' . now()->format('Y-m-d') . '.pdf');
    }

    public function pdfIngresosTransferencias(Request $request)
    {
        $categories = tenant_categories();
        $slugs = $categories->pluck('slug')->toArray();
        $tipoReporte = $request->get('tipo_reporte', 'culto');
        $query = Culto::with(['sobres' => function ($q) { $q->where('metodo_pago', 'transferencia'); }, 'totales'])->orderBy('fecha', 'asc');

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
            $semanas = $cultos->groupBy(fn($culto) => $culto->fecha->startOfWeek()->format('d/m/Y'));
            foreach ($semanas as $semana => $cultosSemana) {
                $registros[] = $this->buildRegistroFromSobres($cultosSemana->flatMap->sobres, $slugs, [
                    'fecha' => 'Semana del ' . $semana,
                    'tipo' => 'Semanal',
                ]);
            }
        } elseif ($tipoReporte == 'mes') {
            $meses = $cultos->groupBy(fn($culto) => $culto->fecha->format('Y-m'));
            foreach ($meses as $mes => $cultosMes) {
                $fecha = Carbon::parse($mes . '-01');
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
            if (is_array($c->firmas_tesoreros)) { $nombres = array_filter($c->firmas_tesoreros); }
            elseif (is_string($c->firmas_tesoreros) && !empty($c->firmas_tesoreros)) {
                $decoded = json_decode($c->firmas_tesoreros, true);
                if (is_array($decoded)) { $nombres = array_filter($decoded); }
            }
            if (!empty($nombres)) {
                $tesorerosPorFecha[$fecha] = isset($tesorerosPorFecha[$fecha])
                    ? array_values(array_unique(array_merge($tesorerosPorFecha[$fecha], $nombres)))
                    : array_values(array_unique($nombres));
            }
        }
        // Ordenar por fecha ascendente si hay múltiples días
        if (!empty($tesorerosPorFecha)) {
            uksort($tesorerosPorFecha, function ($a, $b) {
                $da = \Carbon\Carbon::createFromFormat('d/m/Y', $a);
                $db = \Carbon\Carbon::createFromFormat('d/m/Y', $b);
                return $da <=> $db;
            });
        }

        $pdf = Pdf::loadView('pdfs.ingresos', ['registros' => $registros, 'tipoReporte' => $tipoReporte, 'soloTransferencias' => true, 'tesorerosPorFecha' => $tesorerosPorFecha, 'categories' => $categories]);
        return $pdf->download('ingresos_transferencias_' . $tipoReporte . '_' . now()->format('Y-m-d') . '.pdf');
    }

    public function pdfRecuentoIndividual(Culto $culto)
    {
        $culto->load(['sobres.persona', 'sobres.detalles', 'ofrendasSueltas', 'totales']);
        $categories = tenant_categories();

        // Build totalesPorCategoria dynamically
        $totalesPorCategoria = [];
        foreach ($categories as $cat) {
            $totalesPorCategoria[$cat->slug] = 0;
        }
        foreach ($culto->sobres as $sobre) {
            foreach ($sobre->detalles as $detalle) {
                $slug = strtolower($detalle->categoria);
                if (isset($totalesPorCategoria[$slug])) {
                    $totalesPorCategoria[$slug] += $detalle->monto;
                }
            }
        }

        $pdf = Pdf::loadView('pdfs.recuento-individual', compact('culto', 'totalesPorCategoria', 'categories'));
        return $pdf->download('recuento_' . $culto->fecha->format('Y-m-d') . '_' . $culto->tipo_culto . '.pdf');
    }

    public function pdfRecuentoTransferencias(Culto $culto)
    {
        $culto->load(['sobres' => function ($q) { $q->where('metodo_pago', 'transferencia'); }, 'sobres.persona', 'sobres.detalles', 'totales']);
        $categories = tenant_categories();

        $totalesPorCategoria = [];
        foreach ($categories as $cat) {
            $totalesPorCategoria[$cat->slug] = 0;
        }
        foreach ($culto->sobres as $sobre) {
            foreach ($sobre->detalles as $detalle) {
                $slug = strtolower($detalle->categoria);
                if (isset($totalesPorCategoria[$slug])) {
                    $totalesPorCategoria[$slug] += $detalle->monto;
                }
            }
        }

        $pdf = Pdf::loadView('pdfs.recuento-individual', ['culto' => $culto, 'totalesPorCategoria' => $totalesPorCategoria, 'transferenciasOnly' => true, 'categories' => $categories]);
        return $pdf->download('recuento_transferencias_' . $culto->fecha->format('Y-m-d') . '_' . $culto->tipo_culto . '.pdf');
    }
}

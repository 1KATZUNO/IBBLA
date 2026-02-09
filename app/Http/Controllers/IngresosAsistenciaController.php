<?php

namespace App\Http\Controllers;

use App\Models\Culto;
use App\Models\Asistencia;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class IngresosAsistenciaController extends Controller
{
    public function index()
    {
        // Totales de la semana
        $inicioSemana = Carbon::now()->startOfWeek();
        $finSemana = Carbon::now()->endOfWeek();

        $cultosSemanales = Culto::whereBetween('fecha', [$inicioSemana, $finSemana])
            ->with(['totales', 'asistencia'])
            ->orderBy('fecha', 'desc')
            ->get();

        $totalSemanal = $cultosSemanales->sum(fn($c) => $c->totales ? $c->totales->total_general : 0);

        // Distribución por categorías (no se usa en la nueva vista, pero la dejamos por compatibilidad)
        $categorias = [
            'diezmo' => $cultosSemanales->sum(fn($c) => $c->totales ? $c->totales->total_diezmo : 0),
            'misiones' => $cultosSemanales->sum(fn($c) => $c->totales ? $c->totales->total_misiones : 0),
            'seminario' => $cultosSemanales->sum(fn($c) => $c->totales ? $c->totales->total_seminario : 0),
            'campa' => $cultosSemanales->sum(fn($c) => $c->totales ? $c->totales->total_campa : 0),
            'prestamo' => $cultosSemanales->sum(fn($c) => $c->totales ? $c->totales->total_prestamo : 0),
            'construccion' => $cultosSemanales->sum(fn($c) => $c->totales ? $c->totales->total_construccion : 0),
            'micro' => $cultosSemanales->sum(fn($c) => $c->totales ? $c->totales->total_micro : 0),
            'suelto' => $cultosSemanales->sum(fn($c) => $c->totales ? $c->totales->total_suelto : 0),
        ];

        return view('ingresos-asistencia.index', compact('cultosSemanales', 'totalSemanal', 'categorias'));
    }

    public function asistencia(Request $request)
    {
        $query = Culto::with('asistencia')->orderBy('fecha', 'desc');

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
                $t = $culto->totales; // puede ser null
                if ($t) {
                    $registros[] = [
                        'culto_id' => $culto->id,
                        'fecha' => $culto->fecha->format('d/m/Y'),
                        'tipo' => ucfirst($culto->tipo_culto),
                        'diezmo' => $t->total_diezmo ?? 0,
                        'ofrenda_especial' => $t->total_ofrenda_especial ?? 0,
                        'misiones' => $t->total_misiones ?? 0,
                        'seminario' => $t->total_seminario ?? 0,
                        'campa' => $t->total_campa ?? 0,
                        'construccion' => $t->total_construccion ?? 0,
                        'prestamo' => $t->total_prestamo ?? 0,
                        'micro' => $t->total_micro ?? 0,
                        'suelto' => $t->total_suelto ?? 0,
                        'total' => $t->total_general ?? 0,
                    ];
                } else {
                    // Calcular en vivo desde sobres y suelto para cultos sin totales
                    $sumCat = function ($categoria) use ($culto) {
                        return $culto->sobres->flatMap->detalles->where('categoria', $categoria)->sum('monto');
                    };
                    $suelto = $culto->ofrendasSueltas->sum('monto');
                    $total = $culto->sobres->flatMap->detalles->sum('monto') + $suelto;
                    $registros[] = [
                        'culto_id' => $culto->id,
                        'fecha' => $culto->fecha->format('d/m/Y'),
                        'tipo' => ucfirst($culto->tipo_culto),
                        'diezmo' => $sumCat('diezmo'),
                        'ofrenda_especial' => $sumCat('ofrenda_especial'),
                        'misiones' => $sumCat('misiones'),
                        'seminario' => $sumCat('seminario'),
                        'campa' => $sumCat('campa'),
                        'construccion' => $sumCat('construccion'),
                        'prestamo' => $sumCat('prestamo'),
                        'micro' => $sumCat('micro'),
                        'suelto' => $suelto,
                        'total' => $total,
                    ];
                }
            }
        } elseif ($tipoReporte == 'semana') {
            $semanas = $cultos->groupBy(function($culto) {
                return $culto->fecha->startOfWeek()->format('d/m/Y');
            });

            foreach ($semanas as $semana => $cultosSeamana) {
                $registros[] = [
                    'fecha' => 'Semana del ' . $semana,
                    'tipo' => 'Semanal',
                    'diezmo' => $cultosSeamana->sum('totales.total_diezmo'),
                    'ofrenda_especial' => $cultosSeamana->sum('totales.total_ofrenda_especial'),
                    'misiones' => $cultosSeamana->sum('totales.total_misiones'),
                    'seminario' => $cultosSeamana->sum('totales.total_seminario'),
                    'campa' => $cultosSeamana->sum('totales.total_campa'),
                    'construccion' => $cultosSeamana->sum('totales.total_construccion'),
                    'prestamo' => $cultosSeamana->sum('totales.total_prestamo'),
                    'micro' => $cultosSeamana->sum('totales.total_micro'),
                    'suelto' => $cultosSeamana->sum('totales.total_suelto'),
                    'total' => $cultosSeamana->sum('totales.total_general'),
                ];
            }
        } elseif ($tipoReporte == 'mes') {
            $meses = $cultos->groupBy(function($culto) {
                return $culto->fecha->format('Y-m');
            });

            foreach ($meses as $mes => $cultosMes) {
                $fecha = Carbon::parse($mes . '-01');
                $registros[] = [
                    'fecha' => $fecha->locale('es')->translatedFormat('F Y'),
                    'tipo' => 'Mensual',
                    'diezmo' => $cultosMes->sum('totales.total_diezmo'),
                    'ofrenda_especial' => $cultosMes->sum('totales.total_ofrenda_especial'),
                    'misiones' => $cultosMes->sum('totales.total_misiones'),
                    'seminario' => $cultosMes->sum('totales.total_seminario'),
                    'campa' => $cultosMes->sum('totales.total_campa'),
                    'construccion' => $cultosMes->sum('totales.total_construccion'),
                    'prestamo' => $cultosMes->sum('totales.total_prestamo'),
                    'micro' => $cultosMes->sum('totales.total_micro'),
                    'suelto' => $cultosMes->sum('totales.total_suelto'),
                    'total' => $cultosMes->sum('totales.total_general'),
                ];
            }
        }

        return view('ingresos-asistencia.ingresos', compact('registros'));
    }

    public function pdfAsistencia(Request $request)
    {
        $query = Culto::with('asistencia')->orderBy('fecha', 'asc');

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
        $culto->load('asistencia');
        $pdf = Pdf::loadView('pdfs.asistencia-culto', compact('culto'));
        $nombreArchivo = 'asistencia_' . $culto->fecha->locale('es')->isoFormat('dddd_D-M-Y');
        return $pdf->download($nombreArchivo . '.pdf');
    }

    public function pdfAsistenciaMes(Request $request)
    {
        $mes = $request->get('mes');
        $año = $request->get('año');

        $cultos = Culto::with('asistencia')
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
                $t = $culto->totales; // puede ser null
                $registros[] = [
                    'fecha' => $culto->fecha->format('d/m/Y'),
                    'tipo' => ucfirst($culto->tipo_culto),
                    'diezmo' => $t->total_diezmo ?? 0,
                    'ofrenda_especial' => $t->total_ofrenda_especial ?? 0,
                    'misiones' => $t->total_misiones ?? 0,
                    'seminario' => $t->total_seminario ?? 0,
                    'campa' => $t->total_campa ?? 0,
                    'construccion' => $t->total_construccion ?? 0,
                    'prestamo' => $t->total_prestamo ?? 0,
                    'micro' => $t->total_micro ?? 0,
                    'suelto' => $t->total_suelto ?? 0,
                    'total' => $t->total_general ?? 0,
                ];
            }
        } elseif ($tipoReporte == 'semana') {
            $semanas = $cultos->groupBy(function($culto) {
                return $culto->fecha->startOfWeek()->format('d/m/Y');
            });

            foreach ($semanas as $semana => $cultosSeamana) {
                $registros[] = [
                    'fecha' => 'Semana del ' . $semana,
                    'tipo' => 'Semanal',
                    'diezmo' => $cultosSeamana->sum('totales.total_diezmo'),
                    'ofrenda_especial' => $cultosSeamana->sum('totales.total_ofrenda_especial'),
                    'misiones' => $cultosSeamana->sum('totales.total_misiones'),
                    'seminario' => $cultosSeamana->sum('totales.total_seminario'),
                    'campa' => $cultosSeamana->sum('totales.total_campa'),
                    'construccion' => $cultosSeamana->sum('totales.total_construccion'),
                    'prestamo' => $cultosSeamana->sum('totales.total_prestamo'),
                    'micro' => $cultosSeamana->sum('totales.total_micro'),
                    'suelto' => $cultosSeamana->sum('totales.total_suelto'),
                    'total' => $cultosSeamana->sum('totales.total_general'),
                ];
            }
        } elseif ($tipoReporte == 'mes') {
            $meses = $cultos->groupBy(function($culto) {
                return $culto->fecha->format('Y-m');
            });

            foreach ($meses as $mes => $cultosMes) {
                $fecha = Carbon::parse($mes . '-01');
                $registros[] = [
                    'fecha' => $fecha->locale('es')->translatedFormat('F Y'),
                    'tipo' => 'Mensual',
                    'diezmo' => $cultosMes->sum('totales.total_diezmo'),
                    'ofrenda_especial' => $cultosMes->sum('totales.total_ofrenda_especial'),
                    'misiones' => $cultosMes->sum('totales.total_misiones'),
                    'seminario' => $cultosMes->sum('totales.total_seminario'),
                    'campa' => $cultosMes->sum('totales.total_campa'),
                    'construccion' => $cultosMes->sum('totales.total_construccion'),
                    'prestamo' => $cultosMes->sum('totales.total_prestamo'),
                    'micro' => $cultosMes->sum('totales.total_micro'),
                    'suelto' => $cultosMes->sum('totales.total_suelto'),
                    'total' => $cultosMes->sum('totales.total_general'),
                ];
            }
        }

        $pdf = Pdf::loadView('pdfs.ingresos', compact('registros', 'tipoReporte'));
        return $pdf->download('ingresos_' . $tipoReporte . '_' . now()->format('Y-m-d') . '.pdf');
    }

    public function pdfIngresosTransferencias(Request $request)
    {
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

        $sumCategorias = function ($sobres, $categoria) {
            return $sobres->flatMap->detalles->where('categoria', $categoria)->sum('monto');
        };

        if ($tipoReporte == 'culto') {
            foreach ($cultos as $culto) {
                $sobres = $culto->sobres ?? collect();
                $registros[] = [
                    'fecha' => $culto->fecha->format('d/m/Y'),
                    'tipo' => ucfirst($culto->tipo_culto),
                    'diezmo' => $sumCategorias($sobres, 'diezmo'),
                    'ofrenda_especial' => $sumCategorias($sobres, 'ofrenda_especial'),
                    'misiones' => $sumCategorias($sobres, 'misiones'),
                    'seminario' => $sumCategorias($sobres, 'seminario'),
                    'campa' => $sumCategorias($sobres, 'campa'),
                    'construccion' => $sumCategorias($sobres, 'construccion'),
                    'prestamo' => $sumCategorias($sobres, 'prestamo'),
                    'micro' => $sumCategorias($sobres, 'micro'),
                    'suelto' => 0,
                    'total' => $sobres->flatMap->detalles->sum('monto'),
                ];
            }
        } elseif ($tipoReporte == 'semana') {
            $semanas = $cultos->groupBy(function($culto) { return $culto->fecha->startOfWeek()->format('d/m/Y'); });
            foreach ($semanas as $semana => $cultosSemana) {
                $sobresSemana = $cultosSemana->flatMap->sobres;
                $registros[] = [
                    'fecha' => 'Semana del ' . $semana,
                    'tipo' => 'Semanal',
                    'diezmo' => $sumCategorias($sobresSemana, 'diezmo'),
                    'ofrenda_especial' => $sumCategorias($sobresSemana, 'ofrenda_especial'),
                    'misiones' => $sumCategorias($sobresSemana, 'misiones'),
                    'seminario' => $sumCategorias($sobresSemana, 'seminario'),
                    'campa' => $sumCategorias($sobresSemana, 'campa'),
                    'construccion' => $sumCategorias($sobresSemana, 'construccion'),
                    'prestamo' => $sumCategorias($sobresSemana, 'prestamo'),
                    'micro' => $sumCategorias($sobresSemana, 'micro'),
                    'suelto' => 0,
                    'total' => $sobresSemana->flatMap->detalles->sum('monto'),
                ];
            }
        } elseif ($tipoReporte == 'mes') {
            $meses = $cultos->groupBy(function($culto) { return $culto->fecha->format('Y-m'); });
            foreach ($meses as $mes => $cultosMes) {
                $fecha = Carbon::parse($mes . '-01');
                $sobresMes = $cultosMes->flatMap->sobres;
                $registros[] = [
                    'fecha' => $fecha->locale('es')->translatedFormat('F Y'),
                    'tipo' => 'Mensual',
                    'diezmo' => $sumCategorias($sobresMes, 'diezmo'),
                    'ofrenda_especial' => $sumCategorias($sobresMes, 'ofrenda_especial'),
                    'misiones' => $sumCategorias($sobresMes, 'misiones'),
                    'seminario' => $sumCategorias($sobresMes, 'seminario'),
                    'campa' => $sumCategorias($sobresMes, 'campa'),
                    'construccion' => $sumCategorias($sobresMes, 'construccion'),
                    'prestamo' => $sumCategorias($sobresMes, 'prestamo'),
                    'micro' => $sumCategorias($sobresMes, 'micro'),
                    'suelto' => 0,
                    'total' => $sobresMes->flatMap->detalles->sum('monto'),
                ];
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

        $pdf = Pdf::loadView('pdfs.ingresos', ['registros' => $registros, 'tipoReporte' => $tipoReporte, 'soloTransferencias' => true, 'tesorerosPorFecha' => $tesorerosPorFecha]);
        return $pdf->download('ingresos_transferencias_' . $tipoReporte . '_' . now()->format('Y-m-d') . '.pdf');
    }

    public function pdfRecuentoIndividual(Culto $culto)
    {
        $culto->load(['sobres.persona', 'sobres.detalles', 'ofrendasSueltas', 'totales']);

        // Preparar datos de sobres agrupados por categoría
        $totalesPorCategoria = [
            'diezmo' => 0,
            'ofrenda_especial' => 0,
            'misiones' => 0,
            'seminario' => 0,
            'campa' => 0,
            'prestamo' => 0,
            'construccion' => 0,
            'micro' => 0,
        ];

        foreach ($culto->sobres as $sobre) {
            foreach ($sobre->detalles as $detalle) {
                if (isset($totalesPorCategoria[$detalle->categoria])) {
                    $totalesPorCategoria[$detalle->categoria] += $detalle->monto;
                }
            }
        }

        $pdf = Pdf::loadView('pdfs.recuento-individual', compact('culto', 'totalesPorCategoria'));
        return $pdf->download('recuento_' . $culto->fecha->format('Y-m-d') . '_' . $culto->tipo_culto . '.pdf');
    }

    public function pdfRecuentoTransferencias(Culto $culto)
    {
        $culto->load(['sobres' => function ($q) { $q->where('metodo_pago', 'transferencia'); }, 'sobres.persona', 'sobres.detalles', 'totales']);

        $totalesPorCategoria = [
            'diezmo' => 0,
            'ofrenda_especial' => 0,
            'misiones' => 0,
            'seminario' => 0,
            'campa' => 0,
            'prestamo' => 0,
            'construccion' => 0,
            'micro' => 0,
        ];

        foreach ($culto->sobres as $sobre) {
            foreach ($sobre->detalles as $detalle) {
                if (isset($totalesPorCategoria[$detalle->categoria])) {
                    $totalesPorCategoria[$detalle->categoria] += $detalle->monto;
                }
            }
        }

        $pdf = Pdf::loadView('pdfs.recuento-individual', ['culto' => $culto, 'totalesPorCategoria' => $totalesPorCategoria, 'transferenciasOnly' => true]);
        return $pdf->download('recuento_transferencias_' . $culto->fecha->format('Y-m-d') . '_' . $culto->tipo_culto . '.pdf');
    }
}

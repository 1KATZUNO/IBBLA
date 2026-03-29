<?php

namespace App\Http\Controllers;

use App\Models\Culto;
use App\Models\Persona;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Obtener el mes y año desde la request o usar el actual
        $mes = $request->input('mes', Carbon::now()->month);
        $año = $request->input('año', Carbon::now()->year);

        $inicioMes = Carbon::createFromDate($año, $mes, 1)->startOfMonth();
        $finMes = Carbon::createFromDate($año, $mes, 1)->endOfMonth();

        // Load tenant categories
        $categories = tenant_categories();
        $categoriaSlugs = $categories->pluck('slug')->toArray();

        // Últimos 10 cultos para el gráfico de barras
        $cultosRecientes = Culto::with('totales')
            ->orderBy('fecha', 'desc')
            ->take(10)
            ->get()
            ->reverse()
            ->values();

        // Totales del mes seleccionado - dynamic per category
        $cultosMes = Culto::whereBetween('fecha', [$inicioMes, $finMes])
            ->with('totales')
            ->get();

        $totalesMes = ['total_general' => 0, 'total_suelto' => 0];
        foreach ($categoriaSlugs as $slug) {
            $totalesMes[$slug] = 0;
        }

        foreach ($cultosMes as $culto) {
            if ($culto->totales) {
                $totalesMes['total_general'] += $culto->totales->total_general;
                $totalesMes['total_suelto'] += $culto->totales->total_suelto;
                foreach ($categoriaSlugs as $slug) {
                    $totalesMes[$slug] += $culto->totales->getCategoryTotal($slug);
                }
            }
        }

        // Distribución por categorías (mismo mes) - dynamic
        $distribucion = [];
        foreach ($categories as $cat) {
            $distribucion[$cat->slug] = $totalesMes[$cat->slug] ?? 0;
        }
        $distribucion['suelto'] = $totalesMes['total_suelto'];

        // Asistencia (últimos 10 cultos)
        $asistencias = Culto::with('asistencia')
            ->orderBy('fecha', 'desc')
            ->take(10)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($culto) {
                return [
                    'fecha' => $culto->fecha->format('d/m'),
                    'total' => $culto->asistencia ? $culto->asistencia->total_asistencia : 0,
                ];
            });

        // Promesas cumplidas vs pendientes - use dynamic exclusion
        $categoriasExcluidas = $categories->where('excluir_de_promesas', true)->pluck('slug')->map(fn ($s) => strtolower($s))->toArray();
        $personas = Persona::with(['promesas', 'sobres.detalles'])->get();

        $promesasStatus = [
            'cumplidas' => 0,
            'pendientes' => 0,
        ];

        foreach ($personas as $persona) {
            foreach ($persona->promesas as $promesa) {
                if (in_array(strtolower($promesa->categoria), $categoriasExcluidas)) {
                    continue;
                }
                $montoPagado = $persona->sobres()
                    ->whereHas('detalles', function ($query) use ($promesa) {
                        $query->where('categoria', $promesa->categoria);
                    })
                    ->whereHas('culto', function ($query) use ($mes, $año) {
                        $query->whereMonth('fecha', $mes)
                              ->whereYear('fecha', $año);
                    })
                    ->get()
                    ->sum(function ($sobre) use ($promesa) {
                        return $sobre->detalles()
                            ->where('categoria', $promesa->categoria)
                            ->sum('monto');
                    });

                if ($montoPagado >= $promesa->monto) {
                    $promesasStatus['cumplidas']++;
                } else {
                    $promesasStatus['pendientes']++;
                }
            }
        }

        // === COMPARATIVO: Mes anterior ===
        $mesAnterior = Carbon::createFromDate($año, $mes, 1)->subMonth();
        $cultosMesAnterior = Culto::whereBetween('fecha', [
            $mesAnterior->copy()->startOfMonth(),
            $mesAnterior->copy()->endOfMonth(),
        ])->with('totales')->get();

        $totalMesAnterior = $cultosMesAnterior->sum(fn ($c) => $c->totales ? $c->totales->total_general : 0);
        $totalMesActual = $totalesMes['total_general'];
        $comparativo = [
            'mes_anterior_total' => $totalMesAnterior,
            'mes_anterior_nombre' => $mesAnterior->locale('es')->translatedFormat('F Y'),
            'diferencia' => $totalMesActual - $totalMesAnterior,
            'porcentaje' => $totalMesAnterior > 0 ? round((($totalMesActual - $totalMesAnterior) / $totalMesAnterior) * 100, 1) : 0,
        ];

        $asistMesAnterior = Culto::with('asistencia')
            ->whereBetween('fecha', [$mesAnterior->copy()->startOfMonth(), $mesAnterior->copy()->endOfMonth()])
            ->get();
        $promedioAsistActual = Culto::with('asistencia')
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->get()
            ->filter(fn ($c) => $c->asistencia)
            ->avg(fn ($c) => $c->asistencia->total_asistencia) ?? 0;
        $promedioAsistAnterior = $asistMesAnterior
            ->filter(fn ($c) => $c->asistencia)
            ->avg(fn ($c) => $c->asistencia->total_asistencia) ?? 0;

        $comparativo['asistencia_actual'] = round($promedioAsistActual);
        $comparativo['asistencia_anterior'] = round($promedioAsistAnterior);
        $comparativo['asistencia_diff'] = $promedioAsistAnterior > 0
            ? round((($promedioAsistActual - $promedioAsistAnterior) / $promedioAsistAnterior) * 100, 1) : 0;

        // === TENDENCIA 12 MESES ===
        $tendencia12 = collect();
        for ($i = 11; $i >= 0; $i--) {
            $fechaMes = Carbon::createFromDate($año, $mes, 1)->subMonths($i);
            $cultosM = Culto::whereBetween('fecha', [
                $fechaMes->copy()->startOfMonth(),
                $fechaMes->copy()->endOfMonth(),
            ])->with(['totales', 'asistencia'])->get();

            $tendencia12->push([
                'label' => $fechaMes->locale('es')->translatedFormat('M y'),
                'ingresos' => $cultosM->sum(fn ($c) => $c->totales ? $c->totales->total_general : 0),
                'asistencia' => $cultosM->filter(fn ($c) => $c->asistencia)->avg(fn ($c) => $c->asistencia->total_asistencia) ?? 0,
            ]);
        }

        // === ALERTAS INTELIGENTES ===
        $alertas = collect();

        if ($comparativo['porcentaje'] < -15 && $totalMesAnterior > 0) {
            $alertas->push([
                'tipo' => 'warning',
                'icono' => 'trending-down',
                'mensaje' => 'Los ingresos bajaron '.abs($comparativo['porcentaje']).'% respecto a '.$comparativo['mes_anterior_nombre'],
            ]);
        }

        if ($comparativo['asistencia_diff'] < -10 && $promedioAsistAnterior > 0) {
            $alertas->push([
                'tipo' => 'warning',
                'icono' => 'users-down',
                'mensaje' => 'La asistencia promedio bajó '.abs($comparativo['asistencia_diff']).'% vs mes anterior',
            ]);
        }

        $totalPromesas = $promesasStatus['cumplidas'] + $promesasStatus['pendientes'];
        if ($totalPromesas > 0 && ($promesasStatus['pendientes'] / $totalPromesas) > 0.5) {
            $alertas->push([
                'tipo' => 'danger',
                'icono' => 'alert',
                'mensaje' => $promesasStatus['pendientes'].' de '.$totalPromesas.' promesas aún pendientes este mes',
            ]);
        }

        $fechaLimite = Carbon::now()->subWeeks(4);
        $cultosRecientesIds = Culto::where('fecha', '>=', $fechaLimite)->pluck('id');
        if ($cultosRecientesIds->count() > 0) {
            $personasActivas = Persona::where('activo', true)->count();
            $personasConSobre = \App\Models\Sobre::whereIn('culto_id', $cultosRecientesIds)
                ->whereNotNull('persona_id')
                ->distinct('persona_id')
                ->count('persona_id');
            $sinActividad = $personasActivas - $personasConSobre;
            if ($sinActividad > 3) {
                $alertas->push([
                    'tipo' => 'info',
                    'icono' => 'users',
                    'mensaje' => $sinActividad.' miembros activos sin registrar sobres en las últimas 4 semanas',
                ]);
            }
        }

        if ($comparativo['porcentaje'] > 15 && $totalMesAnterior > 0) {
            $alertas->push([
                'tipo' => 'success',
                'icono' => 'trending-up',
                'mensaje' => 'Los ingresos subieron '.$comparativo['porcentaje'].'% respecto a '.$comparativo['mes_anterior_nombre'],
            ]);
        }

        return view('dashboard', compact(
            'cultosRecientes',
            'totalesMes',
            'distribucion',
            'categories',
            'asistencias',
            'promesasStatus',
            'mes',
            'año',
            'inicioMes',
            'finMes',
            'comparativo',
            'tendencia12',
            'alertas'
        ));
    }
}

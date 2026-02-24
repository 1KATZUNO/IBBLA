<?php

namespace App\Http\Controllers;

use App\Models\Culto;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
        $categoriasExcluidas = $categories->where('excluir_de_promesas', true)->pluck('slug')->map(fn($s) => strtolower($s))->toArray();
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
                    ->whereMonth('created_at', Carbon::now()->month)
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
            'finMes'
        ));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Compromiso;
use App\Models\Persona;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard específico de Promesas — prometido vs dado (Fase 4).
 * Visible solo para admin y tesorero. Muestra:
 *  - KPIs: total prometido, total dado, saldo, % cumplimiento.
 *  - Gráfico de barras agrupadas: prometido vs dado por mes del año seleccionado.
 *  - Tabla top 10 personas con mayor saldo a favor / a deber.
 */
class DashboardPromesasController extends Controller
{
    public function index(Request $request): View
    {
        $año = (int) $request->input('año', Carbon::now()->year);

        // KPIs anuales
        $compromisos = Compromiso::where('año', $año)->get();
        $prometido = (float) $compromisos->sum('monto_prometido');
        $dado = (float) $compromisos->sum('monto_dado');
        $saldo = $dado - $prometido;
        $cumplimiento = $prometido > 0 ? round(($dado / $prometido) * 100, 1) : 0;

        // Serie mensual: prometido vs dado por mes
        $serie = collect(range(1, 12))->map(function ($mes) use ($año) {
            $row = Compromiso::where('año', $año)->where('mes', $mes)->get();

            return [
                'mes' => Carbon::createFromDate($año, $mes, 1)->locale('es')->translatedFormat('M'),
                'prometido' => (float) $row->sum('monto_prometido'),
                'dado' => (float) $row->sum('monto_dado'),
            ];
        });

        // Top contribuyentes (por monto dado del año)
        $topContribuyentes = Persona::with(['promesas'])
            ->withSum(['compromisos as dado_total' => function ($q) use ($año) {
                $q->where('año', $año);
            }], 'monto_dado')
            ->withSum(['compromisos as prometido_total' => function ($q) use ($año) {
                $q->where('año', $año);
            }], 'monto_prometido')
            ->orderByDesc('dado_total')
            ->limit(10)
            ->get()
            ->map(function ($p) {
                $prometido = (float) ($p->prometido_total ?? 0);
                $dado = (float) ($p->dado_total ?? 0);

                return [
                    'nombre' => $p->nombre,
                    'prometido' => $prometido,
                    'dado' => $dado,
                    'saldo' => $dado - $prometido,
                    'pct' => $prometido > 0 ? round(($dado / $prometido) * 100, 1) : 0,
                ];
            });

        // Años disponibles para el selector
        $añosDisponibles = Compromiso::selectRaw('DISTINCT año')->orderByDesc('año')->pluck('año');

        return view('dashboards.promesas', [
            'año' => $año,
            'añosDisponibles' => $añosDisponibles,
            'prometido' => $prometido,
            'dado' => $dado,
            'saldo' => $saldo,
            'cumplimiento' => $cumplimiento,
            'serie' => $serie,
            'topContribuyentes' => $topContribuyentes,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\ClaseAsistencia;
use App\Models\Culto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard específico de Asistencia — por clase (Fase 4).
 * Visible solo para admin y tesorero. Muestra:
 *  - KPIs: total asistencia del año, promedio por culto, niños, capilla, transmisión.
 *  - Gráfico de línea: tendencia mensual del total de asistencia.
 *  - Gráfico de barras: asistencia agregada por clase.
 *  - Tabla por clase con totales.
 */
class DashboardAsistenciaController extends Controller
{
    public function index(Request $request): View
    {
        $año = (int) $request->input('año', Carbon::now()->year);

        $cultos = Culto::with(['asistencia.detallesClases.claseAsistencia', 'asistencia.registrosExtra.tipo'])
            ->whereYear('fecha', $año)
            ->orderBy('fecha', 'asc')
            ->get();

        $cultosConAsistencia = $cultos->filter(fn ($c) => $c->asistencia);

        // KPIs
        $totalAño = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->total_asistencia);
        $promedio = $cultosConAsistencia->isNotEmpty()
            ? round($totalAño / $cultosConAsistencia->count(), 1)
            : 0;
        $capilla = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->getTotalCapilla());
        $ninos = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->getTotalNinos());
        $transmision = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->getTotalRegistrosExtraAsistencia());
        $visitas = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->getTotalVisitas());
        $salvos = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->getTotalSalvos());
        $bautismos = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->getTotalBautismos());

        // Tendencia mensual
        $serieMensual = collect(range(1, 12))->map(function ($mes) use ($cultosConAsistencia, $año) {
            $cultosMes = $cultosConAsistencia->filter(fn ($c) => $c->fecha->month === $mes);
            $total = $cultosMes->sum(fn ($c) => $c->asistencia->total_asistencia);

            return [
                'mes' => Carbon::createFromDate($año, $mes, 1)->locale('es')->translatedFormat('M'),
                'total' => $total,
                'cultos' => $cultosMes->count(),
            ];
        });

        // Asistencia por clase
        $clases = ClaseAsistencia::activas()->ordenadas()->get();
        $porClase = $clases->map(function ($clase) use ($cultosConAsistencia) {
            $total = $cultosConAsistencia->sum(function ($c) use ($clase) {
                $detalle = $c->asistencia->detalleClase($clase->slug);

                return $detalle ? $detalle->getTotalAlumnos() : 0;
            });

            return [
                'nombre' => $clase->nombre,
                'slug' => $clase->slug,
                'color' => $clase->color,
                'total' => $total,
            ];
        })->filter(fn ($c) => $c['total'] > 0)->values();

        // Años disponibles
        $añosDisponibles = Culto::selectRaw('YEAR(fecha) as año')->groupBy('año')->orderByDesc('año')->pluck('año');

        return view('dashboards.asistencia', [
            'año' => $año,
            'añosDisponibles' => $añosDisponibles,
            'totalAño' => $totalAño,
            'promedio' => $promedio,
            'capilla' => $capilla,
            'ninos' => $ninos,
            'transmision' => $transmision,
            'visitas' => $visitas,
            'salvos' => $salvos,
            'bautismos' => $bautismos,
            'serieMensual' => $serieMensual,
            'porClase' => $porClase,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ClaseAsistencia;
use App\Models\Culto;
use App\Support\RangoFechas;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard específico de Asistencia — por clase (Fase 4).
 * Visible para admin, tesorero y asistente (decisión 5, QE-012), con su Excel. Muestra, para el rango de fechas elegido:
 *  - KPIs: total de asistencia, promedio por culto, niños, capilla, transmisión, visitas...
 *  - Gráfico de línea: tendencia de la asistencia (mensual; por culto si el rango es corto).
 *  - Gráfico de barras: asistencia agregada por clase.
 *  - Tabla por clase con totales.
 *
 * El rango sale de la URL (App\Support\RangoFechas): año + mes (acceso rápido; por defecto el
 * año en curso, que es lo que este dashboard siempre mostró) o fecha_inicio / fecha_fin.
 */
class DashboardAsistenciaController extends Controller
{
    public function index(Request $request): View
    {
        $rango = RangoFechas::desdeRequest($request, RangoFechas::MODO_ANIO);

        $cultos = Culto::with(['asistencia.detallesClases.claseAsistencia', 'asistencia.registrosExtra.tipo'])
            ->whereBetween('fecha', [$rango->inicio->toDateString(), $rango->fin->toDateString()])
            ->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $cultosConAsistencia = $cultos->filter(fn ($c) => $c->asistencia);

        // KPIs
        $totalPeriodo = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->total_asistencia);
        $promedio = $cultosConAsistencia->isNotEmpty()
            ? round($totalPeriodo / $cultosConAsistencia->count(), 1)
            : 0;
        $capilla = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->getTotalCapilla());
        $ninos = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->getTotalNinos());
        $transmision = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->getTotalRegistrosExtraAsistencia());
        $visitas = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->getTotalVisitas());
        $salvos = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->getTotalSalvos());
        $bautismos = $cultosConAsistencia->sum(fn ($c) => $c->asistencia->getTotalBautismos());

        // Tendencia: una gráfica mensual de uno o dos puntos no dice nada, así que un rango
        // de hasta dos meses se muestra culto por culto.
        if ($rango->cantidadMeses() <= 2) {
            $serie = [
                'modo' => 'culto',
                'titulo' => 'Asistencia por culto',
                'filas' => $cultosConAsistencia->map(fn ($c) => [
                    'etiqueta' => $c->fecha->format('d/m'),
                    'total' => $c->asistencia->total_asistencia,
                    'cultos' => 1,
                ])->values()->all(),
            ];
        } else {
            $formatoMes = $rango->inicio->year === $rango->fin->year ? 'M' : 'M y';
            $serie = [
                'modo' => 'mes',
                'titulo' => 'Tendencia de asistencia mensual',
                'filas' => $rango->mesesTocados()->map(function ($mes) use ($cultosConAsistencia, $formatoMes) {
                    $cultosMes = $cultosConAsistencia->filter(fn ($c) => $c->fecha->format('Y-m') === $mes->format('Y-m'));

                    return [
                        'etiqueta' => $mes->locale('es')->translatedFormat($formatoMes),
                        'total' => $cultosMes->sum(fn ($c) => $c->asistencia->total_asistencia),
                        'cultos' => $cultosMes->count(),
                    ];
                })->values()->all(),
            ];
        }

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
        $añosDisponibles = $rango->aniosParaSelector(
            Culto::selectRaw('YEAR(fecha) as año')->distinct()->pluck('año')
        );

        return view('dashboards.asistencia', [
            'rango' => $rango,
            'cultosEnRango' => $cultosConAsistencia->count(),
            'añosDisponibles' => $añosDisponibles,
            'totalPeriodo' => $totalPeriodo,
            'promedio' => $promedio,
            'capilla' => $capilla,
            'ninos' => $ninos,
            'transmision' => $transmision,
            'visitas' => $visitas,
            'salvos' => $salvos,
            'bautismos' => $bautismos,
            'serie' => $serie,
            'porClase' => $porClase,
            'entrada' => RangoFechas::entrada($request),
            // El Excel de asistencia ya acepta cualquier rango (fecha_inicio / fecha_fin).
            'excelUrl' => route('ingresos-asistencia.excel-asistencia', $rango->parametros()),
            'excelNota' => null,
        ]);
    }
}

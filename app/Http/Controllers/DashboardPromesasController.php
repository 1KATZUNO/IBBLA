<?php

namespace App\Http\Controllers;

use App\Models\Compromiso;
use App\Models\Persona;
use App\Support\RangoFechas;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard específico de Promesas — prometido vs dado (Fase 4).
 * Visible solo para admin y tesorero. Muestra, para el rango elegido:
 *  - KPIs: total prometido, total dado, saldo, % cumplimiento.
 *  - Gráfico de barras agrupadas: prometido vs dado por mes.
 *  - Tabla top 10 personas por monto dado.
 *
 * Los Compromisos se calculan por mes (tabla compromisos: año + mes), así que no se pueden
 * partir por días: un rango como 15/09 - 20/10 incluye los meses completos de septiembre y
 * octubre, y la pantalla lo avisa. Por defecto se muestra el año en curso, como siempre.
 */
class DashboardPromesasController extends Controller
{
    public function index(Request $request): View
    {
        $rango = RangoFechas::desdeRequest($request, RangoFechas::MODO_ANIO);
        $meses = $rango->ampliadoAMeses();

        // Índice año * 12 + mes: permite pedir "del mes A al mes B" aunque cruce de año.
        $desde = $meses->inicio->year * 12 + $meses->inicio->month;
        $hasta = $meses->fin->year * 12 + $meses->fin->month;
        $enRango = fn ($q) => $q->whereRaw('(`año` * 12 + `mes`) between ? and ?', [$desde, $hasta]);

        // Compromiso no tiene alcance por iglesia propio; se limita por su persona (Persona sí lo tiene).
        $compromisos = Compromiso::whereHas('persona')
            ->tap($enRango)
            ->get(['año', 'mes', 'monto_prometido_crc', 'monto_dado_crc']);

        // KPIs del período
        $prometido = (float) $compromisos->sum('monto_prometido_crc');
        $dado = (float) $compromisos->sum('monto_dado_crc');
        $saldo = $dado - $prometido;
        $cumplimiento = $prometido > 0 ? round(($dado / $prometido) * 100, 1) : 0;

        // Serie mensual: prometido vs dado por cada mes del rango
        $serie = $meses->mesesTocados()->map(function ($mes) use ($compromisos, $meses) {
            $delMes = $compromisos->where('año', $mes->year)->where('mes', $mes->month);

            return [
                'mes' => $mes->locale('es')->translatedFormat($meses->inicio->year === $meses->fin->year ? 'M' : 'M y'),
                'prometido' => (float) $delMes->sum('monto_prometido_crc'),
                'dado' => (float) $delMes->sum('monto_dado_crc'),
            ];
        });

        // Top contribuyentes (por monto dado en el período)
        $topContribuyentes = Persona::with(['promesas'])
            ->withSum(['compromisos as dado_total' => $enRango], 'monto_dado_crc')
            ->withSum(['compromisos as prometido_total' => $enRango], 'monto_prometido_crc')
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
        $añosDisponibles = $rango->aniosParaSelector(
            Compromiso::selectRaw('DISTINCT año')->pluck('año')
        );

        // El Excel de promesas solo sabe de "un año" o "un mes de un año".
        $excelUrl = null;
        $excelNota = null;
        if ($meses->esMesCompleto()) {
            $excelUrl = route('ingresos-asistencia.excel-promesas', ['año' => $meses->inicio->year, 'mes' => $meses->inicio->month]);
        } elseif ($meses->esAnioCompleto()) {
            $excelUrl = route('ingresos-asistencia.excel-promesas', ['año' => $meses->inicio->year]);
        } else {
            $excelNota = 'El Excel de promesas se descarga por mes o por año completo.';
        }

        return view('dashboards.promesas', [
            'rango' => $rango,
            'rangoMeses' => $meses,
            'añosDisponibles' => $añosDisponibles,
            'prometido' => $prometido,
            'dado' => $dado,
            'saldo' => $saldo,
            'cumplimiento' => $cumplimiento,
            'serie' => $serie,
            'topContribuyentes' => $topContribuyentes,
            'entrada' => RangoFechas::entrada($request),
            'excelUrl' => $excelUrl,
            'excelNota' => $excelNota,
        ]);
    }
}

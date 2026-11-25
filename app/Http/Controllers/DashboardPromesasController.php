<?php

namespace App\Http\Controllers;

use App\Services\Reportes\PeriodoInvalido;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Reportes\ResumenPromesas;
use App\Services\Reportes\ResumenPromesasService;
use App\Support\RangoFechas;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard de Promesas — prometido vs dado (QE-008). Visible solo para admin y tesorero.
 *
 * Las cifras salen del motor unico (ResumenPromesasService), con la regla de los reportes:
 *  - Prometido = meses COMPLETOS del rango antes de hoy (= "le correspondía" del Cumplimiento de
 *    promesas para el mismo corte).
 *  - El mes en curso se muestra aparte, rotulado "en curso, todavía no se cobra": no entra en el
 *    saldo ni en el porcentaje.
 *  - Dado = lo que las personas con promesa dieron en las categorias que prometieron, hasta hoy.
 *
 * El rango se amplia a meses completos (un rango 15/09 - 20/10 toma septiembre y octubre) y la
 * pantalla lo avisa. Por defecto, el anio en curso. Ya no depende de la tabla compromisos.
 */
class DashboardPromesasController extends Controller
{
    public function index(Request $request, ResumenPromesasService $servicio): View
    {
        $rango = RangoFechas::desdeRequest($request, RangoFechas::MODO_ANIO);
        $meses = $rango->ampliadoAMeses();

        try {
            $periodo = PeriodoReporte::deFechas($meses->inicio->toDateString(), $meses->fin->toDateString());
            $resumen = $servicio->generar($periodo);
        } catch (PeriodoInvalido) {
            // Mas de 24 meses o fechas fuera de rango: se muestra vacio con el motivo.
            $periodo = PeriodoReporte::deFechas($meses->inicio->toDateString(), $meses->inicio->endOfMonth()->toDateString());
            $resumen = ResumenPromesas::vacio($periodo);
        }

        $serie = collect($resumen->meses())->map(fn (array $m) => [
            'mes' => $m['mes']->inicio()->locale('es')->translatedFormat($meses->inicio->year === $meses->fin->year ? 'M' : 'M y')
                .($m['en_curso'] ? ' (en curso)' : ''),
            'prometido' => $m['prometido'],
            'dado' => $m['dado'],
            'en_curso' => $m['en_curso'],
        ]);

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

        $añoActual = (int) now()->year;

        return view('dashboards.promesas', [
            'rango' => $rango,
            'rangoMeses' => $meses,
            'añosDisponibles' => $rango->aniosParaSelector(range($añoActual - 2, $añoActual)),
            'resumen' => $resumen,
            'prometido' => $resumen->prometido(),
            'dado' => $resumen->dado(),
            'saldo' => $resumen->saldo(),
            'cumplimiento' => $resumen->porcentaje(),
            'serie' => $serie,
            'topContribuyentes' => collect($resumen->top(10)),
            'entrada' => RangoFechas::entrada($request),
            'excelUrl' => $excelUrl,
            'excelNota' => $excelNota,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Culto;
use App\Models\Persona;
use App\Models\Sobre;
use App\Services\Reportes\CumplimientoPromesasService;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\PeriodoReporte;
use App\Support\RangoFechas;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Dashboard general. Todo lo que muestra (KPIs, comparativo, gráficos y alertas) se calcula
 * sobre un rango de fechas: mes + año (acceso rápido, por defecto el mes actual) o un rango
 * libre fecha_inicio / fecha_fin. Ver App\Support\RangoFechas.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $rango = RangoFechas::desdeRequest($request, RangoFechas::MODO_MES);
        $anterior = $rango->anterior();

        $categories = tenant_categories();
        $categoriaSlugs = $categories->pluck('slug')->toArray();

        $cultos = $this->cultosEntre($rango);
        $cultosAnterior = $this->cultosEntre($anterior);

        // Totales del período - dinámicos por categoría
        $totalesPeriodo = $this->sumarTotales($cultos, $categoriaSlugs);

        // Distribución por categorías (mismo período)
        $distribucion = [];
        foreach ($categories as $cat) {
            $distribucion[$cat->slug] = $totalesPeriodo[$cat->slug] ?? 0;
        }
        $distribucion['suelto'] = $totalesPeriodo['total_suelto'];

        // Ingresos y asistencia de cada culto del período (gráficos de barras y de línea)
        $formatoFecha = $rango->inicio->year === $rango->fin->year ? 'd/m' : 'd/m/y';
        $graficoCultos = [
            'labels' => $cultos->map(fn ($c) => $c->fecha->format($formatoFecha))->values()->all(),
            'ingresos' => $cultos->map(fn ($c) => (float) ($c->totales->total_general ?? 0))->values()->all(),
            'asistencia' => $cultos->map(fn ($c) => (int) ($c->asistencia->total_asistencia ?? 0))->values()->all(),
        ];

        // Personas al dia / pendientes con su promesa, del motor de reportes, al ultimo mes completo
        $promesasStatus = $this->estadoPromesas($rango);

        // === COMPARATIVO: período anterior ===
        // Con el período en curso (el día 5 de noviembre) se compara contra los mismos días del
        // período anterior (1 al 5 de octubre), no contra octubre completo (QA-043).
        $enCurso = $this->enCurso($rango);
        $anteriorComparable = $enCurso ? $this->anteriorMismosDias($rango, $anterior, $enCurso) : $anterior;
        $cultosComparables = $enCurso ? $cultos->filter(fn ($c) => $c->fecha->lte($enCurso->fin)) : $cultos;
        $totalAnterior = round($this->cultosEntre($anteriorComparable)->sum(fn ($c) => (float) ($c->totales->total_general ?? 0)), 2);
        $totalActual = round($cultosComparables->sum(fn ($c) => (float) ($c->totales->total_general ?? 0)), 2);
        $comparativo = [
            'periodo_anterior_total' => $totalAnterior,
            'periodo_anterior_nombre' => $anteriorComparable->etiqueta(),
            'diferencia' => $totalActual - $totalAnterior,
            'porcentaje' => $totalAnterior > 0 ? round((($totalActual - $totalAnterior) / $totalAnterior) * 100, 1) : 0,
            // "mes en curso (5 días)": el período todavía no termina
            'en_curso' => $enCurso ? ($rango->esMesCompleto() ? 'mes' : 'período').' en curso ('.$enCurso->dias().' '.($enCurso->dias() === 1 ? 'día' : 'días').')' : null,
        ];

        $promedioAsistActual = $this->promedioAsistencia($cultos);
        $promedioAsistAnterior = $this->promedioAsistencia($cultosAnterior);

        $comparativo['asistencia_actual'] = round($promedioAsistActual);
        $comparativo['asistencia_anterior'] = round($promedioAsistAnterior);
        $comparativo['asistencia_diff'] = $promedioAsistAnterior > 0
            ? round((($promedioAsistActual - $promedioAsistAnterior) / $promedioAsistAnterior) * 100, 1) : 0;

        // === TENDENCIA MENSUAL (al menos 12 meses, siempre incluye el rango) ===
        $tendencia = $this->tendenciaMensual($rango);

        // === ALERTAS INTELIGENTES ===
        $alertas = $this->alertas($rango, $anterior, $anteriorComparable, $enCurso, $comparativo, $promesasStatus, $totalAnterior, $promedioAsistAnterior);

        $añosDisponibles = $rango->aniosParaSelector(
            Culto::selectRaw('YEAR(fecha) as año')->distinct()->pluck('año')
        );

        return view('dashboard', [
            'rango' => $rango,
            'cultosEnRango' => $cultos->count(),
            'totalesPeriodo' => $totalesPeriodo,
            'distribucion' => $distribucion,
            'categories' => $categories,
            'graficoCultos' => $graficoCultos,
            'promesasStatus' => $promesasStatus,
            'comparativo' => $comparativo,
            'tendencia' => $tendencia,
            'alertas' => $alertas,
            'añosDisponibles' => $añosDisponibles,
            'entrada' => RangoFechas::entrada($request),
            'excelUrl' => null,
            'excelNota' => null,
        ]);
    }

    /**
     * Cultos del rango con sus totales y asistencia, en orden cronológico.
     *
     * @return Collection<int, Culto>
     */
    private function cultosEntre(RangoFechas $rango): Collection
    {
        return Culto::with(['totales', 'asistencia'])
            ->whereBetween('fecha', [$rango->inicio->toDateString(), $rango->fin->toDateString()])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    /**
     * Suma los totales de los cultos: total general, suelto y uno por cada categoría.
     *
     * @return array<string, float>
     */
    private function sumarTotales(Collection $cultos, array $categoriaSlugs): array
    {
        $totales = ['total_general' => 0, 'total_suelto' => 0];
        foreach ($categoriaSlugs as $slug) {
            $totales[$slug] = 0;
        }

        foreach ($cultos as $culto) {
            if (! $culto->totales) {
                continue;
            }
            $totales['total_general'] += $culto->totales->total_general;
            $totales['total_suelto'] += $culto->totales->total_suelto;
            foreach ($categoriaSlugs as $slug) {
                $totales[$slug] += $culto->totales->getCategoryTotal($slug);
            }
        }

        // Los montos son decimales de 2 posiciones: se redondea para no arrastrar ruido de coma flotante.
        return array_map(fn ($monto) => round((float) $monto, 2), $totales);
    }

    /** Promedio de asistencia por culto (solo cultos con asistencia registrada). */
    private function promedioAsistencia(Collection $cultos): float
    {
        return (float) ($cultos->filter(fn ($c) => $c->asistencia)
            ->avg(fn ($c) => $c->asistencia->total_asistencia) ?? 0);
    }

    /**
     * Ingresos y asistencia promedio por mes. La ventana dura al menos 12 meses y termina en
     * el mes del fin del rango; si el rango es más largo, empieza en su primer mes. Así un rango
     * corto sigue mostrando contexto y uno largo nunca queda cortado.
     *
     * @return Collection<int, array{label: string, ingresos: float, asistencia: float, enRango: bool}>
     */
    private function tendenciaMensual(RangoFechas $rango): Collection
    {
        $ultimoMes = $rango->fin->startOfMonth();
        $primerMes = $rango->inicio->startOfMonth()->min($ultimoMes->subMonthsNoOverflow(11));

        $porMes = Culto::with(['totales', 'asistencia'])
            ->whereBetween('fecha', [$primerMes->toDateString(), $ultimoMes->endOfMonth()->toDateString()])
            ->get()
            ->groupBy(fn ($c) => $c->fecha->format('Y-m'));

        $serie = collect();
        for ($mes = $primerMes; $mes->lte($ultimoMes); $mes = $mes->addMonthNoOverflow()) {
            $cultosMes = $porMes->get($mes->format('Y-m'), collect());

            $serie->push([
                'label' => $mes->locale('es')->translatedFormat('M y'),
                'ingresos' => round($cultosMes->sum(fn ($c) => (float) ($c->totales->total_general ?? 0)), 2),
                'asistencia' => $this->promedioAsistencia($cultosMes),
                'enRango' => $mes->endOfMonth()->gte($rango->inicio) && $mes->lte($rango->fin),
            ]);
        }

        return $serie;
    }

    /**
     * Si el rango todavia no termina (incluye hoy), el tramo ya transcurrido: del inicio a hoy.
     * Null si el rango ya termino o todavia no empieza.
     */
    private function enCurso(RangoFechas $rango): ?RangoFechas
    {
        $hoy = CarbonImmutable::today();
        if ($rango->fin->lte($hoy) || $rango->inicio->gt($hoy)) {
            return null;
        }

        return new RangoFechas($rango->inicio, $hoy, RangoFechas::MODO_PERSONALIZADO);
    }

    /**
     * Los mismos dias del periodo anterior: para un mes (o meses) en curso, del 1 al mismo dia del
     * mes anterior (1 al 5 de octubre contra 1 al 5 de noviembre); para un rango libre, la misma
     * cantidad de dias justo antes.
     */
    private function anteriorMismosDias(RangoFechas $rango, RangoFechas $anterior, RangoFechas $enCurso): RangoFechas
    {
        if (! $rango->esMesesCompletos()) {
            return $enCurso->anterior();
        }

        $fin = $anterior->inicio->addDays($enCurso->dias() - 1)->min($anterior->fin);

        return new RangoFechas($anterior->inicio, $fin, RangoFechas::MODO_PERSONALIZADO);
    }

    /**
     * Personas al dia y pendientes con su promesa, del reporte de cumplimiento (motor unico de
     * reportes, QA-043). El mes en curso no se cobra, asi que se evalua al ultimo mes completo:
     * los meses del rango hasta el fin del mes anterior al de hoy. "Al dia" es por acumulado de esos
     * meses, la misma regla que el reporte de cumplimiento.
     *
     * @return array{cumplidas: int, pendientes: int, corte: CarbonImmutable, periodo: string}
     */
    private function estadoPromesas(RangoFechas $rango): array
    {
        $meses = $rango->ampliadoAMeses();
        $ultimoCompleto = CarbonImmutable::today()->startOfMonth()->subDay();
        $fin = $meses->fin->min($ultimoCompleto);
        // El motor acepta hasta 24 meses: un rango mas largo se evalua en sus ultimos 24
        $inicio = $meses->inicio->min($fin->startOfMonth())
            ->max($fin->startOfMonth()->subMonthsNoOverflow(PeriodoReporte::MAX_MESES - 1));
        $periodo = PeriodoReporte::deFechas($inicio, $fin);

        $resumen = app(CumplimientoPromesasService::class)
            ->generar($periodo, FiltroPersonas::todas())
            ->resumen();

        return [
            'cumplidas' => $resumen['al_dia'],
            'pendientes' => $resumen['pendientes'],
            'corte' => $fin,
            'periodo' => $periodo->etiqueta(),
        ];
    }

    /**
     * @return Collection<int, array{tipo: string, icono: string, mensaje: string}>
     */
    private function alertas(RangoFechas $rango, RangoFechas $anterior, RangoFechas $anteriorComparable, ?RangoFechas $enCurso, array $comparativo, array $promesasStatus, float $totalAnterior, float $promedioAsistAnterior): Collection
    {
        $alertas = collect();

        $esMes = $rango->esMesCompleto();
        if ($enCurso) {
            // Periodo sin terminar: se compara con los mismos dias del anterior y se dice asi
            $respectoA = $anterior->esMesCompleto()
                ? 'respecto a los mismos días de '.$anterior->etiqueta().' ('.$anteriorComparable->etiqueta().'; '.$comparativo['en_curso'].')'
                : 'respecto a los mismos días del período anterior ('.$anteriorComparable->etiqueta().')';
        } else {
            $respectoA = $anterior->esMesCompleto()
                ? 'respecto a '.$anterior->etiqueta()
                : 'respecto al período anterior ('.$anterior->etiqueta().')';
        }
        $vsAnterior = $esMes ? 'mes anterior' : 'período anterior';

        if ($comparativo['porcentaje'] < -15 && $totalAnterior > 0) {
            $alertas->push([
                'tipo' => 'warning',
                'icono' => 'trending-down',
                'mensaje' => 'Los ingresos bajaron '.abs($comparativo['porcentaje']).'% '.$respectoA,
            ]);
        }

        if ($comparativo['asistencia_diff'] < -10 && $promedioAsistAnterior > 0) {
            $alertas->push([
                'tipo' => 'warning',
                'icono' => 'users-down',
                'mensaje' => 'La asistencia promedio bajó '.abs($comparativo['asistencia_diff']).'% vs '.$vsAnterior,
            ]);
        }

        $totalPromesas = $promesasStatus['cumplidas'] + $promesasStatus['pendientes'];
        if ($totalPromesas > 0 && ($promesasStatus['pendientes'] / $totalPromesas) > 0.5) {
            $alertas->push([
                'tipo' => 'danger',
                'icono' => 'alert',
                'mensaje' => $promesasStatus['pendientes'].' de '.$totalPromesas.' personas con promesa están pendientes al '.$promesasStatus['corte']->format('d/m/Y').' (último mes completo)',
            ]);
        }

        // Miembros activos sin sobres: las 4 semanas que terminan al cierre del período
        // (o hoy, si el período todavía no termina).
        $hoy = CarbonImmutable::instance(now());
        $cierre = $rango->fin->min($hoy);
        $cultosRecientesIds = Culto::whereBetween('fecha', [$cierre->subWeeks(4)->toDateString(), $cierre->toDateString()])->pluck('id');
        if ($cultosRecientesIds->count() > 0) {
            $personasActivas = Persona::where('activo', true)->count();
            $personasConSobre = Sobre::whereIn('culto_id', $cultosRecientesIds)
                ->whereNotNull('persona_id')
                ->distinct('persona_id')
                ->count('persona_id');
            $sinActividad = $personasActivas - $personasConSobre;
            if ($sinActividad > 3) {
                $alertas->push([
                    'tipo' => 'info',
                    'icono' => 'users',
                    'mensaje' => $sinActividad.' miembros activos sin registrar sobres '.(
                        $cierre->isSameDay($hoy)
                            ? 'en las últimas 4 semanas'
                            : 'en las 4 semanas previas al '.$cierre->format('d/m/Y')
                    ),
                ]);
            }
        }

        if ($comparativo['porcentaje'] > 15 && $totalAnterior > 0) {
            $alertas->push([
                'tipo' => 'success',
                'icono' => 'trending-up',
                'mensaje' => 'Los ingresos subieron '.$comparativo['porcentaje'].'% '.$respectoA,
            ]);
        }

        return $alertas;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ClaseAsistencia;
use App\Models\Persona;
use App\Models\Promesa;
use App\Models\SobreDetalle;
use App\Models\TipoCambio;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PromesasReporteController extends Controller
{
    public function index(Request $request)
    {
        $año = $request->get('año', date('Y'));
        $mes = $request->get('mes', date('m'));
        $categoria = $request->get('categoria', null);

        // Obtener años disponibles (solo año actual y anteriores)
        $añoActual = date('Y');
        $añosDisponibles = range($añoActual - 2, $añoActual);

        // Categorías dinámicas del tenant (para el dropdown de filtro)
        $categoriasDisponibles = tenant_categories(['excluir_de_promesas' => false]);

        // Si mes = 'todos', calcular total de todo el año
        if ($mes === 'todos') {
            $totales = $this->calcularTotalesAnuales($año, $categoria);
        } else {
            // Calcular totales para el mes específico
            $totales = $this->calcularTotales($año, $mes, $categoria);
        }

        return view('ingresos-asistencia.promesas', compact('totales', 'añosDisponibles', 'año', 'mes', 'categoria', 'categoriasDisponibles'));
    }

    public function pdfPromesas(Request $request)
    {
        $año = $request->get('año', date('Y'));
        $mes = $request->get('mes', date('m'));
        $categoria = $request->get('categoria', null);

        $totales = $this->calcularTotales($año, $mes, $categoria);

        $pdf = Pdf::loadView('pdfs.promesas', compact('totales', 'año', 'mes', 'categoria'));

        return $pdf->download('reporte_promesas_'.$año.'_'.$mes.'.pdf');
    }

    public function pdfAnual(Request $request)
    {
        $año = $request->get('año', date('Y'));
        $categoria = $request->get('categoria', null);

        // Calcular totales de todo el año (mes a mes)
        $totalesPorMes = [];
        $grandTotal = [
            'prometido' => 0,
            'dado' => 0,
            'faltante' => 0,
            'profit' => 0,
        ];

        for ($mes = 1; $mes <= 12; $mes++) {
            $totalesMes = $this->calcularTotales($año, $mes, $categoria);

            $totalesPorMes[] = [
                'mes' => Carbon::create($año, $mes, 1)->locale('es')->translatedFormat('F'),
                'totales' => $totalesMes['grand_total'],
            ];

            $grandTotal['prometido'] += $totalesMes['grand_total']['prometido'];
            $grandTotal['dado'] += $totalesMes['grand_total']['dado'];
            $grandTotal['faltante'] += $totalesMes['grand_total']['faltante'];
            $grandTotal['profit'] += $totalesMes['grand_total']['profit'];
        }

        $pdf = Pdf::loadView('pdfs.promesas-anual', compact('totalesPorMes', 'grandTotal', 'año', 'categoria'));

        return $pdf->download('reporte_promesas_anual_'.$año.'.pdf');
    }

    public function porClase(Request $request)
    {
        $año = $request->get('año', date('Y'));
        $mes = $request->get('mes', date('m'));
        $claseId = $request->get('clase_id', null);

        $añoActual = date('Y');
        $añosDisponibles = range($añoActual - 2, $añoActual);
        $clasesDisponibles = ClaseAsistencia::activas()->ordenadas()->get();
        $categoriasDisponibles = tenant_categories(['excluir_de_promesas' => false]);

        $totales = null;
        $claseNombre = 'Capilla (Adultos)';

        if ($claseId !== null) {
            // Filtrar personas por clase
            if ($claseId === 'capilla') {
                // Capilla = personas sin clase asignada (via pivot)
                $personaIds = Persona::where('activo', true)
                    ->whereDoesntHave('clasesAsistencia')
                    ->pluck('id')
                    ->toArray();
                $claseNombre = 'Capilla (Adultos)';
            } else {
                $clase = ClaseAsistencia::find($claseId);
                if ($clase) {
                    $personaIds = $clase->personas()
                        ->where('activo', true)
                        ->pluck('personas.id')
                        ->toArray();
                    $claseNombre = $clase->nombre;
                } else {
                    $personaIds = [];
                }
            }

            if ($mes === 'todos') {
                $totales = $this->calcularTotalesPorClase($año, null, $personaIds);
            } else {
                $totales = $this->calcularTotalesPorClase($año, $mes, $personaIds);
            }
        }

        return view('ingresos-asistencia.promesas-por-clase', compact(
            'totales', 'añosDisponibles', 'año', 'mes', 'claseId',
            'clasesDisponibles', 'categoriasDisponibles', 'claseNombre'
        ));
    }

    private function calcularTotalesPorClase($año, $mes, array $personaIds)
    {
        $personas = Persona::whereIn('id', $personaIds)->with('promesas')->get();

        $totalesPorCategoria = [];
        $grandTotal = ['prometido' => 0, 'dado' => 0, 'faltante' => 0, 'profit' => 0];

        $categoriasExcluidas = tenant_categories(['excluir_de_promesas' => true])
            ->pluck('slug')->map(fn ($s) => strtolower($s))->toArray();

        // Prometido
        foreach ($personas as $persona) {
            foreach ($persona->promesas as $promesa) {
                if (in_array(strtolower($promesa->categoria), $categoriasExcluidas)) {
                    continue;
                }

                $cat = $promesa->categoria;
                if (! isset($totalesPorCategoria[$cat])) {
                    $totalesPorCategoria[$cat] = [
                        'categoria' => ucfirst($cat),
                        'total_prometido' => 0,
                        'total_dado' => 0,
                        'faltante' => 0,
                        'profit' => 0,
                    ];
                }

                if ($mes) {
                    $montoPrometido = $this->calcularMontoPrometidoMes($promesa, $año, $mes);
                } else {
                    $montoPrometido = 0;
                    for ($m = 1; $m <= 12; $m++) {
                        $montoPrometido += $this->calcularMontoPrometidoMes($promesa, $año, $m);
                    }
                }
                $totalesPorCategoria[$cat]['total_prometido'] += $montoPrometido;
            }
        }

        // Dado (solo sobres de personas en esta clase)
        $categoriasPromesa = tenant_categories(['excluir_de_promesas' => false])->pluck('slug')->toArray();

        foreach ($categoriasPromesa as $cat) {
            $query = SobreDetalle::whereHas('sobre', function ($q) use ($año, $mes, $personaIds) {
                $q->whereIn('persona_id', $personaIds)
                    ->whereHas('culto', function ($cq) use ($año, $mes) {
                        $cq->whereYear('fecha', $año);
                        if ($mes) {
                            $cq->whereMonth('fecha', $mes);
                        }
                    });
            })->where('categoria', $cat);

            $montoDado = $query->sum('monto');

            if ($montoDado > 0 && ! isset($totalesPorCategoria[$cat])) {
                $totalesPorCategoria[$cat] = [
                    'categoria' => ucfirst($cat),
                    'total_prometido' => 0,
                    'total_dado' => 0,
                    'faltante' => 0,
                    'profit' => 0,
                ];
            }

            if (isset($totalesPorCategoria[$cat])) {
                $totalesPorCategoria[$cat]['total_dado'] = $montoDado;
            }
        }

        // Faltante / Profit
        foreach ($totalesPorCategoria as $cat => $datos) {
            if (in_array(strtolower($cat), $categoriasExcluidas)) {
                unset($totalesPorCategoria[$cat]);

                continue;
            }
            $saldo = $datos['total_dado'] - $datos['total_prometido'];
            $totalesPorCategoria[$cat]['faltante'] = $saldo < 0 ? abs($saldo) : 0;
            $totalesPorCategoria[$cat]['profit'] = $saldo >= 0 ? $saldo : 0;

            $grandTotal['prometido'] += $datos['total_prometido'];
            $grandTotal['dado'] += $datos['total_dado'];
            $grandTotal['faltante'] += $totalesPorCategoria[$cat]['faltante'];
            $grandTotal['profit'] += $totalesPorCategoria[$cat]['profit'];
        }

        return [
            'categorias' => array_values($totalesPorCategoria),
            'grand_total' => $grandTotal,
            'total_personas' => count($personaIds),
        ];
    }

    private function calcularTotales($año, $mes, $categoria = null)
    {
        // Obtener todas las personas activas con promesas
        $personas = Persona::where('activo', true)->with('promesas')->get();

        $totalesPorCategoria = [];
        $grandTotal = [
            'prometido' => 0,
            'dado' => 0,
            'faltante' => 0,
            'profit' => 0,
        ];

        // PASO 1: Calcular montos prometidos por categoría
        foreach ($personas as $persona) {
            foreach ($persona->promesas as $promesa) {
                // Excluir categorías marcadas como excluir_de_promesas
                $catLower = strtolower($promesa->categoria);
                $categoriasExcluidas = tenant_categories(['excluir_de_promesas' => true])->pluck('slug')->map(fn ($s) => strtolower($s))->toArray();
                if (in_array($catLower, $categoriasExcluidas)) {
                    continue;
                }
                // Filtrar por categoría si se especificó
                if ($categoria && $promesa->categoria != $categoria) {
                    continue;
                }

                $cat = $promesa->categoria;

                if (! isset($totalesPorCategoria[$cat])) {
                    $totalesPorCategoria[$cat] = [
                        'categoria' => ucfirst($cat),
                        'total_prometido' => 0,
                        'total_dado' => 0,
                        'faltante' => 0,
                        'profit' => 0,
                    ];
                }

                // Calcular monto prometido para ESTE MES específico
                $montoPrometidoMes = $this->calcularMontoPrometidoMes($promesa, $año, $mes);
                $totalesPorCategoria[$cat]['total_prometido'] += $montoPrometidoMes;
            }
        }

        // PASO 2: Calcular TODOS los montos dados en el mes (incluyendo anónimos)
        // Convertir USD a CRC para comparación uniforme
        $categoriasPromesa = tenant_categories(['excluir_de_promesas' => false])->pluck('slug')->toArray();
        $categorias = $categoria ? [$categoria] : $categoriasPromesa;
        // Sanear cuando piden una categoría excluida
        $categoriasExcluidasSlugs = tenant_categories(['excluir_de_promesas' => true])->pluck('slug')->map(fn ($s) => strtolower($s))->toArray();
        if ($categoria && in_array(strtolower($categoria), $categoriasExcluidasSlugs)) {
            $categorias = [];
            $totalesPorCategoria = [];
        }

        $tipoCambioActual = TipoCambio::hoy();
        $tcVenta = $tipoCambioActual ? (float) $tipoCambioActual->venta : 0;

        foreach ($categorias as $cat) {
            // Sobres CRC
            $montoDadoCrc = SobreDetalle::whereHas('sobre', function ($query) use ($año, $mes) {
                $query->where('moneda', '!=', 'USD')
                    ->whereHas('culto', function ($q) use ($año, $mes) {
                        $q->whereYear('fecha', $año)->whereMonth('fecha', $mes);
                    });
            })
                ->where('categoria', $cat)
                ->sum('monto');

            // Sobres USD - convertir cada uno con su tipo de cambio
            $detallesUsd = SobreDetalle::whereHas('sobre', function ($query) use ($año, $mes) {
                $query->where('moneda', 'USD')
                    ->whereHas('culto', function ($q) use ($año, $mes) {
                        $q->whereYear('fecha', $año)->whereMonth('fecha', $mes);
                    });
            })
                ->where('categoria', $cat)
                ->with('sobre')
                ->get();

            $montoDadoUsdConvertido = 0;
            foreach ($detallesUsd as $detalle) {
                $tc = (float) ($detalle->sobre->tipo_cambio_venta ?? $tcVenta);
                $montoDadoUsdConvertido += $tc > 0 ? round((float) $detalle->monto * $tc, 2) : (float) $detalle->monto;
            }

            $montoDadoTotal = $montoDadoCrc + $montoDadoUsdConvertido;

            // Si hay dinero dado pero no hay promesas en esta categoría, crear el registro
            if ($montoDadoTotal > 0 && ! isset($totalesPorCategoria[$cat])) {
                $totalesPorCategoria[$cat] = [
                    'categoria' => ucfirst($cat),
                    'total_prometido' => 0,
                    'total_dado' => 0,
                    'faltante' => 0,
                    'profit' => 0,
                ];
            }

            // Actualizar el total dado (en CRC)
            if (isset($totalesPorCategoria[$cat])) {
                $totalesPorCategoria[$cat]['total_dado'] = $montoDadoTotal;
            }
        }

        // PASO 3: Calcular faltante y profit POR CATEGORÍA
        foreach ($totalesPorCategoria as $cat => $datos) {
            $catKey = strtolower($cat);
            if (in_array($catKey, $categoriasExcluidasSlugs)) {
                unset($totalesPorCategoria[$cat]);

                continue;
            }
            $saldo = $datos['total_dado'] - $datos['total_prometido'];

            if ($saldo < 0) {
                // Faltante (debe)
                $totalesPorCategoria[$cat]['faltante'] = abs($saldo);
                $totalesPorCategoria[$cat]['profit'] = 0;
            } else {
                // Profit (dio de más o igual)
                $totalesPorCategoria[$cat]['profit'] = $saldo;
                $totalesPorCategoria[$cat]['faltante'] = 0;
            }

            $grandTotal['prometido'] += $datos['total_prometido'];
            $grandTotal['dado'] += $datos['total_dado'];
            $grandTotal['faltante'] += $totalesPorCategoria[$cat]['faltante'];
            $grandTotal['profit'] += $totalesPorCategoria[$cat]['profit'];
        }

        return [
            'categorias' => array_values($totalesPorCategoria),
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Calcula el monto prometido en un mes específico según la frecuencia.
     * Retorna en CRC (convierte si la promesa es en USD).
     */
    private function calcularMontoPrometidoMes($promesa, $año, $mes): float
    {
        $fechaMes = Carbon::create($año, $mes, 1);
        $montoBase = (float) $promesa->monto;

        switch ($promesa->frecuencia) {
            case 'semanal':
                $domingos = 0;
                $fecha = $fechaMes->copy()->startOfMonth();
                $finMes = $fechaMes->copy()->endOfMonth();

                while ($fecha->lte($finMes)) {
                    if ($fecha->dayOfWeek === Carbon::SUNDAY) {
                        $domingos++;
                    }
                    $fecha->addDay();
                }

                $montoTotal = $montoBase * $domingos;
                break;

            case 'quincenal':
                $montoTotal = $montoBase * 2;
                break;

            case 'mensual':
            default:
                $montoTotal = $montoBase;
        }

        // Si la promesa es en USD, convertir a CRC
        if (($promesa->moneda ?? 'CRC') === 'USD') {
            $tipoCambio = TipoCambio::hoy();
            if ($tipoCambio) {
                $montoTotal = round($montoTotal * (float) $tipoCambio->venta, 2);
            }
        }

        return $montoTotal;
    }

    /**
     * Calcula totales de todo el año (suma de todos los meses)
     */
    private function calcularTotalesAnuales($año, $categoria = null)
    {
        $totalesPorCategoria = [];
        $grandTotal = [
            'prometido' => 0,
            'dado' => 0,
            'faltante' => 0,
            'profit' => 0,
        ];

        // Sumar todos los meses del año
        for ($mes = 1; $mes <= 12; $mes++) {
            $totalesMes = $this->calcularTotales($año, $mes, $categoria);

            // Sumar por categoría
            foreach ($totalesMes['categorias'] as $catData) {
                $cat = strtolower(str_replace(' ', '_', $catData['categoria']));

                if (! isset($totalesPorCategoria[$cat])) {
                    $totalesPorCategoria[$cat] = [
                        'categoria' => $catData['categoria'],
                        'total_prometido' => 0,
                        'total_dado' => 0,
                        'faltante' => 0,
                        'profit' => 0,
                    ];
                }

                $totalesPorCategoria[$cat]['total_prometido'] += $catData['total_prometido'];
                $totalesPorCategoria[$cat]['total_dado'] += $catData['total_dado'];
                $totalesPorCategoria[$cat]['faltante'] += $catData['faltante'];
                $totalesPorCategoria[$cat]['profit'] += $catData['profit'];
            }

            // Sumar totales generales
            $grandTotal['prometido'] += $totalesMes['grand_total']['prometido'];
            $grandTotal['dado'] += $totalesMes['grand_total']['dado'];
            $grandTotal['faltante'] += $totalesMes['grand_total']['faltante'];
            $grandTotal['profit'] += $totalesMes['grand_total']['profit'];
        }

        return [
            'categorias' => array_values($totalesPorCategoria),
            'grand_total' => $grandTotal,
        ];
    }
}

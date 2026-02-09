<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Promesa;
use App\Models\SobreDetalle;
use App\Models\Compromiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

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

        // Si mes = 'todos', calcular total de todo el año
        if ($mes === 'todos') {
            $totales = $this->calcularTotalesAnuales($año, $categoria);
        } else {
            // Calcular totales para el mes específico
            $totales = $this->calcularTotales($año, $mes, $categoria);
        }

        return view('ingresos-asistencia.promesas', compact('totales', 'añosDisponibles', 'año', 'mes', 'categoria'));
    }

    public function pdfPromesas(Request $request)
    {
        $año = $request->get('año', date('Y'));
        $mes = $request->get('mes', date('m'));
        $categoria = $request->get('categoria', null);

        $totales = $this->calcularTotales($año, $mes, $categoria);

        $pdf = Pdf::loadView('pdfs.promesas', compact('totales', 'año', 'mes', 'categoria'));
        return $pdf->download('reporte_promesas_' . $año . '_' . $mes . '.pdf');
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
        return $pdf->download('reporte_promesas_anual_' . $año . '.pdf');
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
                // Excluir diezmo y ofrenda_especial de promesas/compromisos
                $catLower = strtolower($promesa->categoria);
                if (in_array($catLower, ['diezmo', 'ofrenda_especial'])) {
                    continue;
                }
                // Filtrar por categoría si se especificó
                if ($categoria && $promesa->categoria != $categoria) {
                    continue;
                }

                $cat = $promesa->categoria;
                
                if (!isset($totalesPorCategoria[$cat])) {
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
        // Excluir diezmo y ofrenda_especial del cálculo de promesas
        $categorias = $categoria ? [$categoria] : ['misiones', 'seminario', 'campa', 'construccion', 'prestamo', 'micro'];
        // Sanear cuando piden una categoría excluida
        if ($categoria && in_array(strtolower($categoria), ['diezmo', 'ofrenda_especial'])) {
            $categorias = [];
        }
        // Si se solicitó específicamente la categoría 'diezmo', devolver sin datos en promesas
        if ($categoria && strtolower($categoria) === 'diezmo') {
            $totalesPorCategoria = [];
        }
        
        foreach ($categorias as $cat) {
            // Obtener TODOS los sobres del mes en esta categoría (con o sin persona)
            $montoDadoTotal = SobreDetalle::whereHas('sobre', function($query) use ($año, $mes) {
                    $query->whereYear('created_at', $año)
                          ->whereMonth('created_at', $mes);
                })
                ->where('categoria', $cat)
                ->sum('monto');

            // Si hay dinero dado pero no hay promesas en esta categoría, crear el registro
            if ($montoDadoTotal > 0 && !isset($totalesPorCategoria[$cat])) {
                $totalesPorCategoria[$cat] = [
                    'categoria' => ucfirst($cat),
                    'total_prometido' => 0,
                    'total_dado' => 0,
                    'faltante' => 0,
                    'profit' => 0,
                ];
            }

            // Actualizar el total dado
            if (isset($totalesPorCategoria[$cat])) {
                $totalesPorCategoria[$cat]['total_dado'] = $montoDadoTotal;
            }
        }

        // PASO 3: Calcular faltante y profit POR CATEGORÍA
        foreach ($totalesPorCategoria as $cat => $datos) {
            $catKey = strtolower($cat);
            if (in_array($catKey, ['diezmo', 'ofrenda_especial'])) {
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
     * Calcula el monto prometido en un mes específico según la frecuencia
     */
    private function calcularMontoPrometidoMes($promesa, $año, $mes): float
    {
        $fechaMes = Carbon::create($año, $mes, 1);
        
        switch ($promesa->frecuencia) {
            case 'semanal':
                // Contar domingos en el mes
                $domingos = 0;
                $fecha = $fechaMes->copy()->startOfMonth();
                $finMes = $fechaMes->copy()->endOfMonth();
                
                while ($fecha->lte($finMes)) {
                    if ($fecha->dayOfWeek === Carbon::SUNDAY) {
                        $domingos++;
                    }
                    $fecha->addDay();
                }
                
                return $promesa->monto * $domingos;
                
            case 'quincenal':
                return $promesa->monto * 2;
                
            case 'mensual':
            default:
                return $promesa->monto;
        }
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
                
                if (!isset($totalesPorCategoria[$cat])) {
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

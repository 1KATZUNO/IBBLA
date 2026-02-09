<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Compromiso;
use App\Models\SobreDetalle;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CompromisoController extends Controller
{
    /**
     * Muestra el estado de compromisos de una persona
     */
    public function show(Persona $persona)
    {
        $año = request('año', Carbon::now()->year);
        $mes = request('mes', Carbon::now()->month);

        // Obtener o calcular compromisos para el mes seleccionado
        $compromisos = $this->calcularCompromisos($persona, $año, $mes);

        // Obtener historial de compromisos solo desde la fecha de creación de la persona
        $fechaCreacion = Carbon::parse($persona->created_at);
        $historial = Compromiso::where('persona_id', $persona->id)
            ->where(function($query) use ($fechaCreacion) {
                $query->where('año', '>', $fechaCreacion->year)
                    ->orWhere(function($q) use ($fechaCreacion) {
                        $q->where('año', '=', $fechaCreacion->year)
                          ->where('mes', '>=', $fechaCreacion->month);
                    });
            })
            ->orderBy('año', 'desc')
            ->orderBy('mes', 'desc')
            ->get()
            ->groupBy(function($item) {
                return $item->año . '-' . str_pad($item->mes, 2, '0', STR_PAD_LEFT);
            });

        // Calcular resumen total
        $resumenTotal = [
            'total_prometido' => $compromisos->sum('monto_prometido'),
            'total_dado' => $compromisos->sum('monto_dado'),
            'saldo_total' => $compromisos->sum('saldo_actual'),
        ];

        return view('compromisos.show', compact('persona', 'compromisos', 'año', 'mes', 'historial', 'resumenTotal'));
    }

    /**
     * Calcula los compromisos de una persona para un mes específico
     * SIN arrastrar saldos - cada mes es independiente
     */
    private function calcularCompromisos(Persona $persona, int $año, int $mes)
    {
        $persona->load('promesas');

        $compromisos = collect();

        foreach ($persona->promesas as $promesa) {
            // Verificar si ya existe el registro de compromiso
            $compromiso = Compromiso::firstOrCreate(
                [
                    'persona_id' => $persona->id,
                    'categoria' => $promesa->categoria,
                    'año' => $año,
                    'mes' => $mes,
                ],
                [
                    'monto_prometido' => $this->calcularMontoPrometido($promesa, $año, $mes),
                    'monto_dado' => 0,
                    'saldo_anterior' => 0,
                    'saldo_actual' => 0,
                ]
            );

            // Actualizar monto prometido por si cambió la frecuencia
            $compromiso->monto_prometido = $this->calcularMontoPrometido($promesa, $año, $mes);

            // Ya no se arrastra saldo del mes anterior - cada mes es independiente
            $compromiso->saldo_anterior = 0;

            // Calcular lo que ha dado en este mes
            $compromiso->monto_dado = $this->calcularMontoDado($persona, $promesa->categoria, $año, $mes);

            // Diferencia del mes: Dado - Esperado (sin arrastrar)
            $compromiso->saldo_actual = $compromiso->monto_dado - $compromiso->monto_prometido;
            $compromiso->save();

            $compromisos->push($compromiso);
        }

        return $compromisos;
    }

    /**
     * Calcula el monto prometido según la frecuencia
     */
    private function calcularMontoPrometido($promesa, int $año, int $mes): float
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
     * Calcula lo que la persona ha dado en un mes específico
     */
    private function calcularMontoDado(Persona $persona, string $categoria, int $año, int $mes): float
    {
        return SobreDetalle::whereHas('sobre', function($query) use ($persona, $año, $mes) {
                $query->where('persona_id', $persona->id)
                      ->whereYear('created_at', $año)
                      ->whereMonth('created_at', $mes);
            })
            ->where('categoria', $categoria)
            ->sum('monto');
    }

    /**
     * Recalcula todos los compromisos de todas las personas
     */
    public function recalcular()
    {
        $personas = Persona::with('promesas')->where('activo', true)->get();
        $añoActual = Carbon::now()->year;
        $mesActual = Carbon::now()->month;

        foreach ($personas as $persona) {
            $this->calcularCompromisos($persona, $añoActual, $mesActual);
        }

        return redirect()->back()->with('success', 'Compromisos recalculados correctamente.');
    }
}

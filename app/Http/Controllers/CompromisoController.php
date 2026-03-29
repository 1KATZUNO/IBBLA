<?php

namespace App\Http\Controllers;

use App\Models\Compromiso;
use App\Models\Persona;
use App\Models\SobreDetalle;
use App\Models\TipoCambio;
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
            ->where(function ($query) use ($fechaCreacion) {
                $query->where('año', '>', $fechaCreacion->year)
                    ->orWhere(function ($q) use ($fechaCreacion) {
                        $q->where('año', '=', $fechaCreacion->year)
                            ->where('mes', '>=', $fechaCreacion->month);
                    });
            })
            ->orderBy('año', 'desc')
            ->orderBy('mes', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->año.'-'.str_pad($item->mes, 2, '0', STR_PAD_LEFT);
            });

        // Calcular resumen total (siempre en CRC)
        $resumenTotal = [
            'total_prometido' => $compromisos->sum('monto_prometido_crc'),
            'total_dado' => $compromisos->sum('monto_dado_crc'),
            'saldo_total' => $compromisos->sum('saldo_actual'),
        ];

        // Tipo de cambio actual para referencia
        $tipoCambio = TipoCambio::hoy();

        return view('compromisos.show', compact('persona', 'compromisos', 'año', 'mes', 'historial', 'resumenTotal', 'tipoCambio'));
    }

    /**
     * Calcula los compromisos de una persona para un mes específico
     * SIN arrastrar saldos - cada mes es independiente
     * Comparación SIEMPRE en CRC
     */
    private function calcularCompromisos(Persona $persona, int $año, int $mes)
    {
        $persona->load('promesas');
        $tipoCambio = TipoCambio::hoy();
        $tcVenta = $tipoCambio ? (float) $tipoCambio->venta : 0;

        $compromisos = collect();

        foreach ($persona->promesas as $promesa) {
            $montoPrometido = $this->calcularMontoPrometido($promesa, $año, $mes);
            $esUsd = $promesa->moneda === 'USD';

            // Convertir prometido a CRC si es USD
            $montoPrometidoCrc = $esUsd && $tcVenta > 0
                ? round($montoPrometido * $tcVenta, 2)
                : $montoPrometido;

            // Calcular dado: suma por moneda
            $montoDadoCrc = $this->calcularMontoDadoCrc($persona, $promesa->categoria, $año, $mes, $tcVenta);

            // Verificar si ya existe el registro de compromiso
            $compromiso = Compromiso::firstOrCreate(
                [
                    'persona_id' => $persona->id,
                    'categoria' => $promesa->categoria,
                    'año' => $año,
                    'mes' => $mes,
                ],
                [
                    'monto_prometido' => $montoPrometido,
                    'monto_dado' => 0,
                    'saldo_anterior' => 0,
                    'saldo_actual' => 0,
                    'moneda_promesa' => $promesa->moneda ?? 'CRC',
                    'monto_prometido_crc' => $montoPrometidoCrc,
                    'monto_dado_crc' => $montoDadoCrc,
                    'tipo_cambio_usado' => $tcVenta > 0 ? $tcVenta : null,
                ]
            );

            // Actualizar valores
            $compromiso->monto_prometido = $montoPrometido;
            $compromiso->moneda_promesa = $promesa->moneda ?? 'CRC';
            $compromiso->monto_prometido_crc = $montoPrometidoCrc;
            $compromiso->saldo_anterior = 0;

            // Calcular lo dado (en moneda original y en CRC)
            $compromiso->monto_dado = $this->calcularMontoDado($persona, $promesa->categoria, $año, $mes);
            $compromiso->monto_dado_crc = $montoDadoCrc;
            $compromiso->tipo_cambio_usado = $tcVenta > 0 ? $tcVenta : null;

            // Saldo en CRC: dado_crc - prometido_crc
            $compromiso->saldo_actual = $compromiso->monto_dado_crc - $compromiso->monto_prometido_crc;
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
     * Calcula lo que la persona ha dado en un mes específico (moneda original)
     */
    private function calcularMontoDado(Persona $persona, string $categoria, int $año, int $mes): float
    {
        return SobreDetalle::whereHas('sobre', function ($query) use ($persona, $año, $mes) {
            $query->where('persona_id', $persona->id)
                ->whereHas('culto', function ($q) use ($año, $mes) {
                    $q->whereYear('fecha', $año)
                      ->whereMonth('fecha', $mes);
                });
        })
            ->where('categoria', $categoria)
            ->sum('monto');
    }

    /**
     * Calcula lo dado convertido a CRC (sumando CRC directo + USD convertido)
     */
    private function calcularMontoDadoCrc(Persona $persona, string $categoria, int $año, int $mes, float $tcVenta): float
    {
        // Dado en CRC (sobres CRC)
        $dadoCrc = SobreDetalle::whereHas('sobre', function ($query) use ($persona, $año, $mes) {
            $query->where('persona_id', $persona->id)
                ->where('moneda', 'CRC')
                ->whereHas('culto', function ($q) use ($año, $mes) {
                    $q->whereYear('fecha', $año)
                      ->whereMonth('fecha', $mes);
                });
        })
            ->where('categoria', $categoria)
            ->sum('monto');

        // Dado en USD (sobres USD) - convertir con tipo de cambio del sobre o actual
        $detallesUsd = SobreDetalle::whereHas('sobre', function ($query) use ($persona, $año, $mes) {
            $query->where('persona_id', $persona->id)
                ->where('moneda', 'USD')
                ->whereHas('culto', function ($q) use ($año, $mes) {
                    $q->whereYear('fecha', $año)
                      ->whereMonth('fecha', $mes);
                });
        })
            ->where('categoria', $categoria)
            ->with('sobre')
            ->get();

        $dadoUsdConvertido = 0;
        foreach ($detallesUsd as $detalle) {
            $tc = (float) ($detalle->sobre->tipo_cambio_venta ?? $tcVenta);
            $dadoUsdConvertido += $tc > 0 ? round((float) $detalle->monto * $tc, 2) : (float) $detalle->monto;
        }

        return $dadoCrc + $dadoUsdConvertido;
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

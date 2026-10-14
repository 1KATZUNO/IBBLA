<?php

namespace App\Http\Controllers;

use App\Models\Compromiso;
use App\Models\Persona;
use App\Models\TipoCambio;
use App\Services\Reportes\CuentaPersona;
use App\Services\Reportes\MotorReportes;
use App\Services\Reportes\PeriodoReporte;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CompromisoController extends Controller
{
    public function __construct(private readonly MotorReportes $motor) {}

    /**
     * Muestra el estado de compromisos de una persona
     */
    public function show(Persona $persona)
    {
        $año = (int) request('año', Carbon::now()->year);
        $mes = (int) request('mes', Carbon::now()->month);

        // Obtener o calcular compromisos para el mes seleccionado
        $compromisos = $this->sincronizarMes(collect([$persona]), $año, $mes)[$persona->id] ?? collect();

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
     * Guarda en la tabla compromisos el mes indicado de cada persona y los devuelve.
     *
     * Cada mes es independiente (sin arrastrar saldos) y la comparación es en colones. Los montos
     * en colones salen del motor único de reportes, el mismo del estado de cuenta y del Reporte
     * General: una fila por categoría de compromiso con promesa (si hay dos promesas de la misma
     * categoría vale la última, antes salían dos filas y el resumen las sumaba dos veces), lo
     * prometido según la frecuencia y lo dado con los dólares convertidos.
     *
     * @param  iterable<Persona>  $personas
     * @return array<int, Collection<int, Compromiso>> id de persona => sus compromisos del mes
     */
    private function sincronizarMes(iterable $personas, int $año, int $mes): array
    {
        // Con detalle: de ahí sale lo dado "en la moneda del sobre" que guarda la columna monto_dado.
        $resultado = $this->motor->calcular(PeriodoReporte::deMeses($año, $mes, $mes), $personas, conDetalle: true);
        $delMes = $resultado->periodo->meses()[0];
        $tipoCambio = $resultado->tipoCambioHoy > 0 ? $resultado->tipoCambioHoy : null;

        $porPersona = [];
        foreach ($resultado->cuentas() as $cuenta) {
            $compromisos = collect();

            foreach ($cuenta->promesas() as $categoria => $promesa) {
                $prometidoCrc = $cuenta->esperado($delMes->clave, $categoria);
                $dadoCrc = $cuenta->dado($delMes->clave, [$categoria]);

                $compromiso = Compromiso::firstOrNew([
                    'persona_id' => $cuenta->id,
                    'categoria' => $categoria,
                    'año' => $año,
                    'mes' => $mes,
                ]);
                $compromiso->fill([
                    'monto_prometido' => $promesa->montoDelMes($delMes->domingos),
                    'monto_dado' => $this->dadoSinConvertir($cuenta, $categoria),
                    'saldo_anterior' => 0,
                    'moneda_promesa' => $promesa->moneda,
                    'monto_prometido_crc' => $prometidoCrc,
                    'monto_dado_crc' => $dadoCrc,
                    'tipo_cambio_usado' => $tipoCambio,
                    // Saldo en CRC: dado_crc - prometido_crc
                    'saldo_actual' => $dadoCrc - $prometidoCrc,
                ]);
                $compromiso->save();

                $compromisos->push($compromiso);
            }

            $porPersona[$cuenta->id] = $compromisos;
        }

        return $porPersona;
    }

    /**
     * Suma de los montos tal como se anotaron en los sobres (sin convertir dólares): es lo que
     * siempre guardó la columna monto_dado y lo que leen el Dashboard de promesas y Servidores.
     */
    private function dadoSinConvertir(CuentaPersona $cuenta, string $categoria): float
    {
        $centimos = 0;
        foreach ($cuenta->detalle as $renglon) {
            if ($renglon['categoria'] === $categoria) {
                $centimos += (int) round($renglon['monto'] * 100);
            }
        }

        return $centimos / 100;
    }

    /**
     * Recalcula los compromisos del mes en curso de todas las personas activas
     */
    public function recalcular()
    {
        $personas = Persona::where('activo', true)->get(['id', 'nombre', 'activo']);

        $this->sincronizarMes($personas, Carbon::now()->year, Carbon::now()->month);

        return redirect()->back()->with('success', 'Compromisos recalculados correctamente.');
    }
}

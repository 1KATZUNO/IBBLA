<?php

namespace App\Services;

use App\Models\Culto;
use App\Models\TipoCambio;
use App\Models\TotalesCulto;

class CalculoTotalesCultoService
{
    // Legacy column mapping for dual-write backward compatibility
    private const LEGACY_COLUMNS = [
        'diezmo' => 'total_diezmo',
        'ofrenda_especial' => 'total_ofrenda_especial',
        'misiones' => 'total_misiones',
        'seminario' => 'total_seminario',
        'campa' => 'total_campa',
        'prestamo' => 'total_prestamo',
        'construccion' => 'total_construccion',
        'micro' => 'total_micro',
    ];

    public function recalcular(Culto $culto): TotalesCulto
    {
        $sobres = $culto->sobres()->with('detalles')->get();
        $ofrendasSueltas = $culto->ofrendasSueltas;
        $egresos = $culto->egresos ?? collect();

        // Obtener tipo de cambio: congelado del culto si está cerrado, o el actual
        $tipoCambioVenta = $culto->cerrado && $culto->tipo_cambio_venta
            ? (float) $culto->tipo_cambio_venta
            : $this->obtenerTipoCambioVenta();

        // Separar totales por moneda
        $categoriaTotalesCrc = [];
        $categoriaTotalesUsd = [];
        $cantidadTransferencias = 0;

        foreach ($sobres as $sobre) {
            if ($sobre->metodo_pago === 'transferencia') {
                $cantidadTransferencias++;
            }

            $esUsd = $sobre->moneda === 'USD';

            foreach ($sobre->detalles as $detalle) {
                $slug = strtolower($detalle->categoria);

                if ($esUsd) {
                    $categoriaTotalesUsd[$slug] = ($categoriaTotalesUsd[$slug] ?? 0) + (float) $detalle->monto;
                } else {
                    $categoriaTotalesCrc[$slug] = ($categoriaTotalesCrc[$slug] ?? 0) + (float) $detalle->monto;
                }
            }
        }

        // Ofrenda suelta separada por moneda
        $totalSueltoCrc = 0;
        $totalSueltoUsd = 0;
        foreach ($ofrendasSueltas as $ofrenda) {
            if ($ofrenda->moneda === 'USD') {
                $totalSueltoUsd += (float) $ofrenda->monto;
            } else {
                $totalSueltoCrc += (float) $ofrenda->monto;
            }
        }

        // Egresos separados por moneda
        $totalEgresosCrc = 0;
        $totalEgresosUsd = 0;
        foreach ($egresos as $egreso) {
            if ($egreso->moneda === 'USD') {
                $totalEgresosUsd += (float) $egreso->monto;
            } else {
                $totalEgresosCrc += (float) $egreso->monto;
            }
        }

        // Calcular totales USD
        $sumaUsd = array_sum($categoriaTotalesUsd) + $totalSueltoUsd;
        $totalGeneralUsd = $sumaUsd - $totalEgresosUsd;

        // Convertir USD a CRC
        $totalGeneralCrcConvertido = $tipoCambioVenta > 0
            ? round($totalGeneralUsd * $tipoCambioVenta, 2)
            : 0;

        // Totales combinados en CRC (CRC puro + USD convertido por categoría)
        $categoriaTotalesCombinados = $categoriaTotalesCrc;
        if ($tipoCambioVenta > 0) {
            foreach ($categoriaTotalesUsd as $slug => $montoUsd) {
                $convertido = round($montoUsd * $tipoCambioVenta, 2);
                $categoriaTotalesCombinados[$slug] = ($categoriaTotalesCombinados[$slug] ?? 0) + $convertido;
            }
        }

        // Total general combinado en CRC
        $totalSueltoCombinado = $totalSueltoCrc + ($tipoCambioVenta > 0 ? round($totalSueltoUsd * $tipoCambioVenta, 2) : 0);
        $totalEgresosCombinado = $totalEgresosCrc + ($tipoCambioVenta > 0 ? round($totalEgresosUsd * $tipoCambioVenta, 2) : 0);
        $sumaIngresosCombinado = array_sum($categoriaTotalesCombinados) + $totalSueltoCombinado;
        $totalGeneral = $sumaIngresosCombinado - $totalEgresosCombinado;

        // Build the data array
        $totales = [
            'total_suelto' => $totalSueltoCombinado,
            'total_egresos' => $totalEgresosCombinado,
            'total_general' => $totalGeneral,
            'cantidad_sobres' => $sobres->count(),
            'cantidad_transferencias' => $cantidadTransferencias,
            'totales_por_categoria' => $categoriaTotalesCombinados,
            // Campos USD
            'tipo_cambio_venta' => $tipoCambioVenta > 0 ? $tipoCambioVenta : null,
            'totales_usd' => ! empty($categoriaTotalesUsd) || $totalSueltoUsd > 0 || $totalEgresosUsd > 0
                ? [
                    'categorias' => $categoriaTotalesUsd,
                    'suelto' => $totalSueltoUsd,
                    'egresos' => $totalEgresosUsd,
                    'total' => $totalGeneralUsd,
                ]
                : null,
            'total_general_usd' => $totalGeneralUsd,
            'total_general_crc_convertido' => $totalGeneralCrcConvertido,
        ];

        // Dual-write: also populate legacy columns for backward compat
        foreach (self::LEGACY_COLUMNS as $slug => $column) {
            $totales[$column] = $categoriaTotalesCombinados[$slug] ?? 0;
        }

        return $culto->totales()->updateOrCreate(
            ['culto_id' => $culto->id],
            $totales
        );
    }

    /**
     * Obtiene el tipo de cambio venta actual.
     */
    private function obtenerTipoCambioVenta(): float
    {
        $tipoCambio = TipoCambio::hoy();

        return $tipoCambio ? (float) $tipoCambio->venta : 0;
    }
}

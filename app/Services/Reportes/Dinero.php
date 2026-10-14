<?php

namespace App\Services\Reportes;

/**
 * Montos de los reportes: el motor suma en centimos enteros para que todas las salidas (pantalla,
 * PDF, Excel) cuadren al centimo, y convierte dolares a colones con una sola regla.
 */
final class Dinero
{
    public const SIMBOLO = '₡';

    /** Colones (float, string decimal de la BD...) a centimos enteros. */
    public static function centimos(float|int|string|null $colones): int
    {
        return (int) round(((float) $colones) * 100);
    }

    public static function colones(int $centimos): float
    {
        return $centimos / 100;
    }

    /**
     * Un monto en colones, en centimos. Los dolares se convierten con el tipo de cambio del sobre
     * y, si no lo guardo, con el de hoy; sin ningun tipo de cambio queda el monto tal cual (como
     * hacian el informe individual y Compromisos). Se redondea a 2 decimales por cada monto.
     */
    public static function aColones(float|string $monto, ?string $moneda, float|string|null $tipoCambio, float $tipoCambioHoy): int
    {
        $monto = (float) $monto;
        if ($moneda !== 'USD') {
            return self::centimos($monto);
        }

        $tc = (float) $tipoCambio > 0 ? (float) $tipoCambio : $tipoCambioHoy;

        return self::centimos($tc > 0 ? round($monto * $tc, 2) : $monto);
    }

    /**
     * "₡1,234", "-₡1,234" y, con signo, "+₡1,234". Mismo formato de miles que el resto de la app
     * (coma para miles, punto para decimales).
     */
    public static function formato(float $colones, int $decimales = 0, bool $conSigno = false): string
    {
        $redondeado = round($colones, $decimales);
        $texto = self::SIMBOLO.number_format(abs($redondeado), $decimales);

        if ($redondeado < 0) {
            return '-'.$texto;
        }

        return $conSigno && $redondeado > 0 ? '+'.$texto : $texto;
    }
}

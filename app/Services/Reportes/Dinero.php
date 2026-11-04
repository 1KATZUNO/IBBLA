<?php

namespace App\Services\Reportes;

/**
 * Montos de los reportes: el motor suma en centimos enteros para que todas las salidas (pantalla,
 * PDF, Excel) cuadren al centimo. La conversion de dolares es de App\Support\Moneda\ConversorUsd.
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

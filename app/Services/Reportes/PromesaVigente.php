<?php

namespace App\Services\Reportes;

use App\Support\Moneda\ConversorUsd;

/**
 * Lo que una persona prometio en una categoria: TODAS sus promesas vigentes de esa categoria (una
 * persona puede tener, por ejemplo, ₡10.000 cada domingo y ademas $20 mensuales para seminario).
 * Lo esperado de un mes es la suma de cada promesa con su frecuencia (semanal x domingos del mes,
 * quincenal x 2, mensual x 1) y su moneda (dolares con la regla del ConversorUsd).
 */
final class PromesaVigente
{
    /** Moneda de la categoria cuando sus promesas mezclan colones y dolares. */
    public const MIXTA = 'MIX';

    /** @var list<array{monto: float, moneda: string, frecuencia: string}> */
    public readonly array $partes;

    /** Suma de los montos si todas las promesas son de la misma moneda; si se mezclan, la de colones convertida no aplica (ver montoDelMes). */
    public readonly float $monto;

    /** CRC, USD o MIX si se mezclan. */
    public readonly string $moneda;

    /**
     * @param  list<array{monto: float|int|string, moneda?: string|null, frecuencia: string}>  $partes
     */
    public function __construct(public readonly string $categoria, array $partes)
    {
        $limpias = [];
        foreach ($partes as $parte) {
            $limpias[] = [
                'monto' => (float) $parte['monto'],
                'moneda' => ($parte['moneda'] ?? null) ?: 'CRC',
                'frecuencia' => (string) $parte['frecuencia'],
            ];
        }
        $this->partes = $limpias;

        $monedas = array_values(array_unique(array_column($limpias, 'moneda')));
        $this->moneda = count($monedas) === 1 ? $monedas[0] : self::MIXTA;
        $this->monto = array_sum(array_column($limpias, 'monto'));
    }

    /** Una sola promesa (atajo para pruebas y codigo viejo). */
    public static function una(string $categoria, float $monto, string $moneda, string $frecuencia): self
    {
        return new self($categoria, [['monto' => $monto, 'moneda' => $moneda, 'frecuencia' => $frecuencia]]);
    }

    /** Si alguna de sus promesas tiene monto. */
    public function tieneMonto(): bool
    {
        foreach ($this->partes as $parte) {
            if ($parte['monto'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Monto de un mes en la moneda de la promesa (suma de las partes por su frecuencia). Si las
     * promesas mezclan colones y dolares no hay una sola moneda: null (usar esperadoCentimos).
     */
    public function montoDelMes(int $domingos): ?float
    {
        if ($this->moneda === self::MIXTA) {
            return null;
        }

        $total = 0.0;
        foreach ($this->partes as $parte) {
            $total += $parte['monto'] * self::factor($parte['frecuencia'], $domingos);
        }

        return $total;
    }

    /** Lo esperado de un mes, en centimos de colon: cada promesa convertida por su lado (dolares al fin de ese mes). */
    public function esperadoCentimos(MesPeriodo $mes, ConversorUsd $conversor): int
    {
        $total = 0;
        foreach ($this->partes as $parte) {
            $total += $conversor->centimosPromesa($parte['monto'] * self::factor($parte['frecuencia'], $mes->domingos), $parte['moneda'], $mes->anio, $mes->mes);
        }

        return $total;
    }

    public function esUsd(): bool
    {
        return $this->moneda === 'USD';
    }

    /** Si alguna de sus promesas es en dolares. */
    public function tieneUsd(): bool
    {
        return in_array('USD', array_column($this->partes, 'moneda'), true);
    }

    /** "₡10,000.00 mensual", "₡2,000.00 cada domingo + $20.00 mensual". */
    public function descripcion(): string
    {
        return implode(' + ', array_map(function (array $parte) {
            $simbolo = $parte['moneda'] === 'USD' ? '$' : Dinero::SIMBOLO;
            $frecuencia = match ($parte['frecuencia']) {
                'semanal' => 'cada domingo',
                'quincenal' => 'cada quincena',
                default => 'mensual',
            };

            return $simbolo.number_format($parte['monto'], 2).' '.$frecuencia;
        }, $this->partes));
    }

    private static function factor(string $frecuencia, int $domingos): int
    {
        return match ($frecuencia) {
            'semanal' => $domingos,
            'quincenal' => 2,
            default => 1,
        };
    }
}

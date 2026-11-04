<?php

namespace App\Services\Reportes;

use App\Support\Moneda\ConversorUsd;

/**
 * La promesa que vale para una persona en una categoria.
 *
 * Si hay varias promesas de la misma categoria vale la ultima (la de id mayor): asi lo hacia el
 * Reporte General y es lo que deja el formulario de la persona, que guarda una por categoria.
 */
final class PromesaVigente
{
    public function __construct(
        public readonly string $categoria,
        public readonly float $monto,
        public readonly string $moneda,
        public readonly string $frecuencia,
    ) {}

    /** Monto de un mes en la moneda de la promesa: semanal x domingos, quincenal x 2, mensual x 1. */
    public function montoDelMes(int $domingos): float
    {
        return $this->monto * $this->factor($domingos);
    }

    /** Lo esperado de un mes, en centimos de colon (los dolares, al tipo de cambio del fin de ese mes). */
    public function esperadoCentimos(MesPeriodo $mes, ConversorUsd $conversor): int
    {
        return $conversor->centimosPromesa($this->montoDelMes($mes->domingos), $this->moneda, $mes->anio, $mes->mes);
    }

    public function esUsd(): bool
    {
        return $this->moneda === 'USD';
    }

    /** "₡10,000.00 mensual", "₡2,000.00 cada domingo", "$5.00 cada quincena". */
    public function descripcion(): string
    {
        $simbolo = $this->esUsd() ? '$' : Dinero::SIMBOLO;
        $frecuencia = match ($this->frecuencia) {
            'semanal' => 'cada domingo',
            'quincenal' => 'cada quincena',
            default => 'mensual',
        };

        return $simbolo.number_format($this->monto, 2).' '.$frecuencia;
    }

    private function factor(int $domingos): int
    {
        return match ($this->frecuencia) {
            'semanal' => $domingos,
            'quincenal' => 2,
            default => 1,
        };
    }
}

<?php

namespace App\Services\Reportes;

/**
 * Estado acumulado de una persona en un periodo: lo que le correspondia (promesas de los meses que
 * se cobran) contra lo que ha dado en las categorias de compromiso.
 *
 * AL DÍA si lo dado alcanza lo que correspondia (con tolerancia de 1 colon), aunque algun mes no
 * haya dado (repuso despues). Nunca se decide mes a mes.
 */
final class EstadoCuenta
{
    public const AL_DIA = 'AL DÍA';

    public const PENDIENTE = 'PENDIENTE';

    public const SIN_PROMESA = 'SIN PROMESA';

    /** Tolerancia para AL DÍA: 1 colon (redondeos de los dolares). */
    public const TOLERANCIA_CENTIMOS = 100;

    public function __construct(
        public readonly int $leCorrespondeCentimos,
        public readonly int $haDadoCentimos,
        public readonly bool $tienePromesa,
    ) {}

    public function leCorresponde(): float
    {
        return Dinero::colones($this->leCorrespondeCentimos);
    }

    public function haDado(): float
    {
        return Dinero::colones($this->haDadoCentimos);
    }

    public function diferenciaCentimos(): int
    {
        return $this->haDadoCentimos - $this->leCorrespondeCentimos;
    }

    public function diferencia(): float
    {
        return Dinero::colones($this->diferenciaCentimos());
    }

    public function alDia(): bool
    {
        return $this->haDadoCentimos + self::TOLERANCIA_CENTIMOS >= $this->leCorrespondeCentimos;
    }

    /** AL DÍA, PENDIENTE o SIN PROMESA. */
    public function etiqueta(): string
    {
        if (! $this->tienePromesa) {
            return self::SIN_PROMESA;
        }

        return $this->alDia() ? self::AL_DIA : self::PENDIENTE;
    }
}

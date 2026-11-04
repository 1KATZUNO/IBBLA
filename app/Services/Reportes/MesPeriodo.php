<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;

/**
 * Un mes calendario dentro de un periodo de reporte.
 *
 * `cobrable` dice si lo esperado de las promesas de ese mes se cobra: solo cuando el mes cae
 * entero dentro del periodo (ver PeriodoReporte).
 */
final class MesPeriodo
{
    public readonly string $clave;

    public readonly string $nombre;

    public function __construct(
        public readonly int $anio,
        public readonly int $mes,
        public readonly int $domingos,
        public readonly bool $cobrable,
    ) {
        $this->clave = sprintf('%04d-%02d', $anio, $mes);
        $this->nombre = PeriodoReporte::NOMBRES_MESES[$mes];
    }

    /** "Enero" o, si el periodo abarca varios anios, "Enero 2026". */
    public function etiqueta(bool $conAnio = false): string
    {
        return $conAnio ? $this->nombre.' '.$this->anio : $this->nombre;
    }

    public function inicio(): CarbonImmutable
    {
        return CarbonImmutable::create($this->anio, $this->mes, 1)->startOfDay();
    }

    public function fin(): CarbonImmutable
    {
        return $this->inicio()->endOfMonth()->startOfDay();
    }
}

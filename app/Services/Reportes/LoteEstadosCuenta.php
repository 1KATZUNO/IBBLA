<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;

/**
 * Los estados de cuenta de un grupo: una entrada por persona con las cifras del informe individual
 * (ver InformePersonaService::datosDeCuenta) y el resultado del motor del que salieron.
 */
final class LoteEstadosCuenta
{
    public readonly CarbonImmutable $generadoEn;

    /**
     * @param  list<array<string, mixed>>  $estados  en el orden de las personas (por nombre)
     * @param  int  $totalPersonas  cuantas cumplian el filtro (puede ser mas que las incluidas si se paso el tope)
     */
    public function __construct(
        public readonly PeriodoReporte $periodo,
        public readonly FiltroPersonas $filtro,
        public readonly ResultadoMotor $resultado,
        public readonly array $estados,
        public readonly int $totalPersonas,
    ) {
        $this->generadoEn = CarbonImmutable::now();
    }

    public function cantidad(): int
    {
        return count($this->estados);
    }

    public function recortado(): bool
    {
        return $this->totalPersonas > $this->cantidad();
    }

    /**
     * Explicacion de lo esperado segun los meses que se cobran en el periodo, para la nota de cada
     * estado de cuenta.
     */
    public function explicacionEsperado(): string
    {
        $cobrados = $this->periodo->textoMesesCobrables();
        $enCurso = $this->periodo->mesEnCurso();

        if ($cobrados === '') {
            return '«Le corresponde» todavía es cero: no hay meses completos en el periodo.';
        }

        return '«Le corresponde» suma lo prometido de '.$cobrados
            .($enCurso ? ' ('.mb_strtolower($enCurso->nombre).' está en curso y no se cobra)' : '')
            .'. La diferencia compara lo dado en las categorías en que cada persona tiene promesa (el diezmo y lo demás no cuentan) con lo que le corresponde.';
    }
}

<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;

/**
 * Una persona en el reporte de cumplimiento de promesas. Montos en colones (por dentro, centimos).
 */
final class FilaCumplimiento
{
    /**
     * @param  array<string, array{dado: int, esperado: int}>  $meses  clave de mes => centimos (dado en categorias de compromiso, esperado de sus promesas)
     */
    public function __construct(
        public readonly int $personaId,
        public readonly string $nombre,
        public readonly string $estado,
        public readonly int $leCorrespondeCentimos,
        public readonly int $haDadoCentimos,
        public readonly ?CarbonImmutable $ultimoAporte,
        public readonly string $observacion,
        public readonly string $promesa,
        public readonly array $meses,
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

    public function dadoMes(string $clave): float
    {
        return Dinero::colones($this->meses[$clave]['dado'] ?? 0);
    }

    public function esperadoMes(string $clave): float
    {
        return Dinero::colones($this->meses[$clave]['esperado'] ?? 0);
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoCuenta::PENDIENTE;
    }

    public function estaAlDia(): bool
    {
        return $this->estado === EstadoCuenta::AL_DIA;
    }

    public function tienePromesa(): bool
    {
        return $this->estado !== EstadoCuenta::SIN_PROMESA;
    }

    /** "28/09/2026" o "-" si no ha aportado en el periodo. */
    public function ultimoAporteTexto(): string
    {
        return $this->ultimoAporte?->format('d/m/Y') ?? '-';
    }
}

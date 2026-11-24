<?php

namespace App\Services\Reportes\Cultos;

use App\Models\Culto;
use App\Support\ResumenCulto;

/**
 * Una fila del reporte de cultos: las cifras salen tal cual de ResumenCulto (la misma regla del
 * encabezado del culto y del PDF de recuento que firman los tesoreros), en colones.
 */
final class FilaCulto
{
    /**
     * @param  array<string, float>  $porCategoria  slug => colones
     */
    public function __construct(
        public readonly Culto $culto,
        public readonly string $tipo,
        public readonly int $sobres,
        public readonly int $transferenciasCantidad,
        public readonly float $sobresEfectivo,
        public readonly float $transferencias,
        public readonly float $sueltos,
        public readonly float $egresos,
        public readonly float $efectivo,
        public readonly float $total,
        public readonly array $porCategoria,
        public readonly float $otrasCategorias,
        public readonly bool $firmasCompletas,
        public readonly bool $tieneUsd,
    ) {}

    public static function desde(Culto $culto, ResumenCulto $resumen, bool $firmasCompletas): self
    {
        $sobresTotal = round($resumen->sobresEfectivo + $resumen->sobresTransferencias, 2);
        $otras = round($sobresTotal - array_sum($resumen->porCategoria), 2);

        return new self(
            culto: $culto,
            tipo: $culto->tipo_nombre,
            sobres: $resumen->cantidadSobres,
            transferenciasCantidad: $resumen->cantidadTransferencias,
            sobresEfectivo: $resumen->sobresEfectivo,
            transferencias: $resumen->transferencias(),
            sueltos: $resumen->suelto,
            egresos: $resumen->egresos,
            efectivo: $resumen->efectivo(),
            total: $resumen->total(),
            porCategoria: $resumen->porCategoria,
            otrasCategorias: abs($otras) < 0.005 ? 0.0 : $otras,
            firmasCompletas: $firmasCompletas,
            tieneUsd: $resumen->tieneUsd,
        );
    }

    /** Ingresos antes de egresos: sobres (efectivo y transferencias) + dinero suelto. */
    public function ingresos(): float
    {
        return round($this->sobresEfectivo + $this->transferencias + $this->sueltos, 2);
    }

    public function estado(): string
    {
        return $this->culto->cerrado ? 'Cerrado' : 'Abierto';
    }
}

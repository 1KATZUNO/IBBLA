<?php

namespace App\Support;

use App\Models\Sobre;
use Illuminate\Support\Collection;

/**
 * Numeros de la pantalla de un culto en Recuento: lo que se ve en el encabezado (sobres,
 * efectivo, transferencias, total) y el resumen por categorias. Todo en colones: cada sobre,
 * dinero suelto o egreso en dolares se convierte con el tipo de cambio con que se registro
 * (el mismo criterio que la tabla de sobres), para que el encabezado y el resumen cuadren.
 */
final class ResumenCulto
{
    /**
     * @param  array<int, array{sobre: Sobre, crc: array<string, float>, usd: array<string, float>, subtotal_crc: float}>  $filas
     * @param  array<string, float>  $porCategoria  slug => colones
     */
    private function __construct(
        public readonly int $cantidadSobres,
        public readonly int $cantidadTransferencias,
        public readonly float $sobresEfectivo,
        public readonly float $sobresTransferencias,
        public readonly float $suelto,
        public readonly float $egresos,
        public readonly bool $tieneUsd,
        public readonly array $filas,
        public readonly array $porCategoria,
    ) {}

    /**
     * @param  Collection<int, Sobre>  $sobres  con sus detalles cargados
     * @param  Collection<int, \App\Models\OfrendaSuelta>  $sueltos
     * @param  Collection<int, \App\Models\Egreso>  $egresos
     * @param  iterable<object{slug: string}>  $categorias
     */
    public static function calcular(Collection $sobres, Collection $sueltos, Collection $egresos, iterable $categorias): self
    {
        $slugs = collect($categorias)->pluck('slug')->all();
        $porCategoria = array_fill_keys($slugs, 0.0);
        $filas = [];
        $efectivo = 0.0;
        $transferencias = 0.0;
        $cantidadTransferencias = 0;
        $tieneUsd = false;

        foreach ($sobres as $sobre) {
            $esUsd = $sobre->moneda === 'USD';
            $tc = $esUsd ? (float) ($sobre->tipo_cambio_venta ?? 0) : 1.0;
            $detalles = $sobre->detalles->keyBy('categoria');
            $crc = [];
            $usd = [];

            foreach ($slugs as $slug) {
                $original = (float) ($detalles->get($slug)->monto ?? 0);
                $enColones = $esUsd && $tc > 0 ? round($original * $tc, 2) : $original;
                $crc[$slug] = $enColones;
                $usd[$slug] = $esUsd ? $original : 0.0;
                $porCategoria[$slug] += $enColones;
            }

            $subtotal = (float) $sobre->total_declarado_crc;
            if ($sobre->metodo_pago === 'transferencia') {
                $transferencias += $subtotal;
                $cantidadTransferencias++;
            } else {
                $efectivo += $subtotal;
            }
            $tieneUsd = $tieneUsd || $esUsd;

            $filas[] = ['sobre' => $sobre, 'crc' => $crc, 'usd' => $usd, 'subtotal_crc' => $subtotal];
        }

        $suelto = (float) $sueltos->sum(fn ($o) => $o->monto_crc);
        $totalEgresos = (float) $egresos->sum(fn ($e) => $e->monto_crc);
        $tieneUsd = $tieneUsd
            || $sueltos->contains(fn ($o) => $o->moneda === 'USD')
            || $egresos->contains(fn ($e) => $e->moneda === 'USD');

        return new self(
            cantidadSobres: $sobres->count(),
            cantidadTransferencias: $cantidadTransferencias,
            sobresEfectivo: $efectivo,
            sobresTransferencias: $transferencias,
            suelto: $suelto,
            egresos: $totalEgresos,
            tieneUsd: $tieneUsd,
            filas: $filas,
            porCategoria: $porCategoria,
        );
    }

    /** Efectivo que se entrega: sobres en efectivo + dinero suelto - egresos. */
    public function efectivo(): float
    {
        return $this->sobresEfectivo + $this->suelto - $this->egresos;
    }

    public function transferencias(): float
    {
        return $this->sobresTransferencias;
    }

    public function total(): float
    {
        return $this->efectivo() + $this->transferencias();
    }
}

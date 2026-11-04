<?php

namespace App\Support;

use App\Models\Culto;
use App\Models\Sobre;
use App\Models\TipoCambio;
use Illuminate\Support\Collection;

/**
 * Numeros del recuento de un culto: lo que se ve en el encabezado de la pantalla (sobres, efectivo,
 * transferencias, total), el resumen por categorias y el PDF de recuento que firman los tesoreros.
 * Todo en colones y con UNA sola regla, para que pantalla y PDF den el mismo total (QA-026):
 *
 *  - Cada sobre, dinero suelto o egreso en dolares se convierte con el tipo de cambio guardado en
 *    el registro; si no lo tiene, con el de venta de la fecha del culto; si tampoco, con el de hoy
 *    (regla 2.3 de docs/plan-oct-2026/04-DECISIONES.md).
 *  - Las columnas por categoria y el total de cada fila son colones ya convertidos; el monto en
 *    dolares se muestra aparte, solo como informacion.
 *
 * TODO(equipo 3a): cuando integracion traiga el helper unico de conversion
 * (App\Services\Reportes\Dinero::aColones), usarlo aqui en lugar de self::aColones.
 */
final class ResumenCulto
{
    /**
     * @param  array<int, array{sobre: Sobre, crc: array<string, float>, usd: array<string, float>, subtotal_crc: float, subtotal_usd: float, tipo_cambio: float}>  $filas
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
        public readonly float $tipoCambioRespaldo,
    ) {}

    /**
     * @param  Collection<int, Sobre>  $sobres  con sus detalles cargados
     * @param  Collection<int, \App\Models\OfrendaSuelta>  $sueltos
     * @param  Collection<int, \App\Models\Egreso>  $egresos
     * @param  iterable<object{slug: string}>  $categorias
     * @param  float|null  $tipoCambioRespaldo  para registros en USD sin tipo de cambio propio; si no se
     *                                          pasa, se usa el de la fecha del culto o el de hoy
     */
    public static function calcular(Collection $sobres, Collection $sueltos, Collection $egresos, iterable $categorias, ?float $tipoCambioRespaldo = null): self
    {
        $slugs = collect($categorias)->pluck('slug')->all();
        $porCategoria = array_fill_keys($slugs, 0.0);
        $filas = [];
        $efectivo = 0.0;
        $transferencias = 0.0;
        $cantidadTransferencias = 0;
        $tieneUsd = $sobres->contains(fn ($s) => $s->moneda === 'USD')
            || $sueltos->contains(fn ($o) => $o->moneda === 'USD')
            || $egresos->contains(fn ($e) => $e->moneda === 'USD');

        $respaldo = $tipoCambioRespaldo ?? ($tieneUsd ? self::tipoCambioDelCulto($sobres->first()?->culto ?? $sueltos->first()?->culto ?? $egresos->first()?->culto) : 0.0);

        foreach ($sobres as $sobre) {
            $esUsd = $sobre->moneda === 'USD';
            $tc = $esUsd ? self::tipoCambio($sobre->tipo_cambio_venta, $respaldo) : 1.0;
            $detalles = $sobre->detalles->keyBy('categoria');
            $crc = [];
            $usd = [];
            $sumaOriginal = 0.0;

            foreach ($slugs as $slug) {
                $original = (float) ($detalles->get($slug)->monto ?? 0);
                $sumaOriginal += $original;
                $enColones = self::aColones($original, $esUsd, $tc);
                $crc[$slug] = $enColones;
                $usd[$slug] = $esUsd ? $original : 0.0;
                $porCategoria[$slug] += $enColones;
            }

            // El total de la fila es la suma de sus columnas (mas lo que no tenga categoria conocida)
            $resto = round((float) $sobre->total_declarado - $sumaOriginal, 2);
            $subtotal = round(array_sum($crc) + ($resto > 0 ? self::aColones($resto, $esUsd, $tc) : 0), 2);

            if ($sobre->metodo_pago === 'transferencia') {
                $transferencias += $subtotal;
                $cantidadTransferencias++;
            } else {
                $efectivo += $subtotal;
            }

            $filas[] = [
                'sobre' => $sobre,
                'crc' => $crc,
                'usd' => $usd,
                'subtotal_crc' => $subtotal,
                'subtotal_usd' => $esUsd ? (float) $sobre->total_declarado : 0.0,
                'tipo_cambio' => $esUsd ? $tc : 0.0,
            ];
        }

        $suelto = (float) $sueltos->sum(fn ($o) => self::aColones((float) $o->monto, $o->moneda === 'USD', self::tipoCambio($o->tipo_cambio_venta, $respaldo)));
        $totalEgresos = (float) $egresos->sum(fn ($e) => self::aColones((float) $e->monto, $e->moneda === 'USD', self::tipoCambio($e->tipo_cambio_venta, $respaldo)));

        return new self(
            cantidadSobres: $sobres->count(),
            cantidadTransferencias: $cantidadTransferencias,
            sobresEfectivo: round($efectivo, 2),
            sobresTransferencias: round($transferencias, 2),
            suelto: round($suelto, 2),
            egresos: round($totalEgresos, 2),
            tieneUsd: $tieneUsd,
            filas: $filas,
            porCategoria: array_map(fn ($v) => round($v, 2), $porCategoria),
            tipoCambioRespaldo: $respaldo,
        );
    }

    /** Tipo de cambio de venta de la fecha del culto (o el anterior mas cercano); si no hay, el de hoy. */
    public static function tipoCambioDelCulto(?Culto $culto): float
    {
        $tc = $culto ? TipoCambio::deFecha($culto->fecha) : null;

        return (float) (($tc ?? TipoCambio::hoy())?->venta ?? 0);
    }

    /** Efectivo que se entrega: sobres en efectivo + dinero suelto - egresos. */
    public function efectivo(): float
    {
        return round($this->sobresEfectivo + $this->suelto - $this->egresos, 2);
    }

    public function transferencias(): float
    {
        return $this->sobresTransferencias;
    }

    public function total(): float
    {
        return round($this->efectivo() + $this->transferencias(), 2);
    }

    /** Monto en dolares de todos los sobres en USD (solo informativo, entre parentesis). */
    public function totalUsdSobres(): float
    {
        return round(array_sum(array_column($this->filas, 'subtotal_usd')), 2);
    }

    private static function tipoCambio(mixed $propio, float $respaldo): float
    {
        return (float) $propio > 0 ? (float) $propio : $respaldo;
    }

    /** Sin ningun tipo de cambio el monto queda tal cual (como hacia la app antes); se nota en el PDF. */
    private static function aColones(float $monto, bool $esUsd, float $tc): float
    {
        return $esUsd && $tc > 0 ? round($monto * $tc, 2) : $monto;
    }
}

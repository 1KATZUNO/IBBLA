<?php

namespace App\Services\Reportes\Cultos;

use Illuminate\Support\Collection;

/**
 * Resultado del reporte de cultos: una fila por culto y los totales del rango. Pantalla, PDF y
 * Excel leen este mismo objeto, asi que dicen lo mismo.
 */
final class ReporteCultos
{
    /** @var array<string, int|float>|null */
    private ?array $totales = null;

    /**
     * @param  list<FilaCulto>  $filas  de la fecha mas antigua a la mas reciente
     * @param  Collection<int, object>  $categorias  categorias activas (slug, nombre)
     */
    public function __construct(
        public readonly FiltroCultos $filtro,
        public readonly array $filas,
        public readonly Collection $categorias,
    ) {}

    public function vacio(): bool
    {
        return $this->filas === [];
    }

    /**
     * Totales del rango: suma de las filas en centimos, para no arrastrar redondeos.
     *
     * @return array{cultos: int, cerrados: int, abiertos: int, sin_firmas: int, sobres: int, transferencias_cantidad: int, sobres_efectivo: float, transferencias: float, sueltos: float, egresos: float, efectivo: float, ingresos: float, total: float, otras_categorias: float}
     */
    public function totales(): array
    {
        if ($this->totales !== null) {
            return $this->totales;
        }

        $suma = fn (callable $campo) => array_sum(array_map(fn (FilaCulto $f) => (int) round($campo($f) * 100), $this->filas)) / 100;
        $cuenta = fn (callable $condicion) => count(array_filter($this->filas, $condicion));

        return $this->totales = [
            'cultos' => count($this->filas),
            'cerrados' => $cuenta(fn (FilaCulto $f) => (bool) $f->culto->cerrado),
            'abiertos' => $cuenta(fn (FilaCulto $f) => ! $f->culto->cerrado),
            'sin_firmas' => $cuenta(fn (FilaCulto $f) => ! $f->firmasCompletas),
            'sobres' => array_sum(array_map(fn (FilaCulto $f) => $f->sobres, $this->filas)),
            'transferencias_cantidad' => array_sum(array_map(fn (FilaCulto $f) => $f->transferenciasCantidad, $this->filas)),
            'sobres_efectivo' => $suma(fn (FilaCulto $f) => $f->sobresEfectivo),
            'transferencias' => $suma(fn (FilaCulto $f) => $f->transferencias),
            'sueltos' => $suma(fn (FilaCulto $f) => $f->sueltos),
            'egresos' => $suma(fn (FilaCulto $f) => $f->egresos),
            'efectivo' => $suma(fn (FilaCulto $f) => $f->efectivo),
            'ingresos' => $suma(fn (FilaCulto $f) => $f->ingresos()),
            'total' => $suma(fn (FilaCulto $f) => $f->total),
            'otras_categorias' => $suma(fn (FilaCulto $f) => $f->otrasCategorias),
        ];
    }

    /**
     * Ingresos por categoria en el rango (colones), con "Otras categorías" (las que la iglesia ya no
     * tiene activas) y "Dinero suelto" al final: la suma cuadra con los ingresos del rango.
     *
     * @return list<array{slug: string, nombre: string, monto: float}>
     */
    public function porCategoria(): array
    {
        $lineas = [];
        foreach ($this->categorias as $categoria) {
            $centimos = array_sum(array_map(fn (FilaCulto $f) => (int) round(($f->porCategoria[$categoria->slug] ?? 0) * 100), $this->filas));
            $lineas[] = ['slug' => $categoria->slug, 'nombre' => $categoria->nombre, 'monto' => $centimos / 100];
        }
        $totales = $this->totales();
        if (abs($totales['otras_categorias']) >= 0.005) {
            $lineas[] = ['slug' => '_otras', 'nombre' => 'Otras categorías (ya no activas)', 'monto' => $totales['otras_categorias']];
        }
        $lineas[] = ['slug' => '_suelto', 'nombre' => 'Dinero suelto', 'monto' => $totales['sueltos']];

        return $lineas;
    }

    /**
     * Ingresos por metodo de pago (colones). El dinero suelto se entrega en efectivo con el recuento.
     *
     * @return list<array{clave: string, nombre: string, cantidad: ?int, monto: float}>
     */
    public function porMetodo(): array
    {
        $t = $this->totales();

        return [
            ['clave' => 'efectivo', 'nombre' => 'Sobres en efectivo', 'cantidad' => $t['sobres'] - $t['transferencias_cantidad'], 'monto' => $t['sobres_efectivo']],
            ['clave' => 'transferencia', 'nombre' => 'Sobres por transferencia', 'cantidad' => $t['transferencias_cantidad'], 'monto' => $t['transferencias']],
            ['clave' => 'suelto', 'nombre' => 'Dinero suelto (efectivo)', 'cantidad' => null, 'monto' => $t['sueltos']],
        ];
    }
}

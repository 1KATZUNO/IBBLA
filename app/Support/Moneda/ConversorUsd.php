<?php

namespace App\Support\Moneda;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Conversion de dolares a colones con UNA sola regla para toda la app (regla 2.3 de
 * docs/plan-oct-2026/04-DECISIONES.md y docs/plan-oct-2026/REGLAS-CALCULO.md).
 *
 *  - Sobre en USD: tipo de cambio guardado en el sobre (tipo_cambio_venta); si no tiene, el de venta
 *    vigente a la fecha del culto (el de ese dia o el ultimo anterior); si tampoco hay, el de hoy.
 *  - Promesa en USD: para cada mes, el de venta vigente al ultimo dia de ese mes; para el mes en
 *    curso (o uno futuro), el de hoy.
 *  - Sin ningun tipo de cambio cargado el monto queda tal cual (no hay con que convertir).
 *  - Se redondea a 2 decimales por monto convertido: para que una tabla y su total cuadren, convertir
 *    cada detalle y sumar los convertidos (no convertir el total aparte).
 *
 * Uso (tambien desde el recuento y sus PDF):
 *
 *     $conversor = app(ConversorUsd::class);   // lee la tabla tipo_cambios una sola vez
 *     $colones = $conversor->sobreAColones($detalle->monto, $sobre->moneda, $sobre->tipo_cambio_venta, $culto->fecha);
 *     $tc = $conversor->tipoCambioDeSobre($sobre->tipo_cambio_venta, $culto->fecha);  // para mostrarlo
 *
 * Una instancia guarda en memoria la tabla de tipos de cambio (unas cientos de filas): crear una
 * por peticion o por reporte, no una por sobre.
 */
final class ConversorUsd
{
    /** @var list<string>|null fechas (AAAA-MM-DD) en orden ascendente */
    private ?array $fechas = null;

    /** @var list<float> venta de cada fecha, mismo indice que $fechas */
    private array $ventas = [];

    private ?float $hoy = null;

    /**
     * @param  array<string, float|string>|null  $tabla  fecha => venta (para pruebas); null = la tabla tipo_cambios
     */
    public function __construct(?array $tabla = null)
    {
        if ($tabla !== null) {
            $this->cargar($tabla);
        }
    }

    // ------------------------------------------------------------------
    // Tipos de cambio
    // ------------------------------------------------------------------

    /** Venta de hoy: la de hoy o la ultima anterior; si solo hay posteriores, la mas reciente. 0 si no hay ninguno. */
    public function tipoCambioHoy(): float
    {
        if ($this->hoy === null) {
            $this->leer();
            $this->hoy = $this->buscar(CarbonImmutable::today()->toDateString())
                ?? ($this->ventas !== [] ? $this->ventas[count($this->ventas) - 1] : 0.0);
        }

        return $this->hoy;
    }

    /** Venta vigente en una fecha (la de ese dia o la ultima anterior); si no hay ninguna anterior, la de hoy. */
    public function tipoCambioAl(DateTimeInterface|string|null $fecha): float
    {
        if ($fecha === null) {
            return $this->tipoCambioHoy();
        }

        $this->leer();

        return $this->buscar(self::dia($fecha)) ?? $this->tipoCambioHoy();
    }

    /** El tipo de cambio con que se convierte un sobre en dolares (regla 2.3). */
    public function tipoCambioDeSobre(float|string|null $tipoCambioDelSobre, DateTimeInterface|string|null $fechaCulto): float
    {
        if ((float) $tipoCambioDelSobre > 0) {
            return (float) $tipoCambioDelSobre;
        }

        return $this->tipoCambioAl($fechaCulto);
    }

    /** El tipo de cambio de una promesa en dolares para un mes: el del ultimo dia del mes, o el de hoy si el mes no ha terminado. */
    public function tipoCambioDePromesa(int $anio, int $mes): float
    {
        $fin = CarbonImmutable::create($anio, $mes, 1)->endOfMonth()->startOfDay();

        return $fin->gte(CarbonImmutable::today()) ? $this->tipoCambioHoy() : $this->tipoCambioAl($fin);
    }

    // ------------------------------------------------------------------
    // Conversiones
    // ------------------------------------------------------------------

    /** Un monto de un sobre en colones (redondeado a 2 decimales). Los colones quedan igual. */
    public function sobreAColones(float|int|string|null $monto, ?string $moneda, float|string|null $tipoCambioDelSobre, DateTimeInterface|string|null $fechaCulto): float
    {
        if ($moneda !== 'USD') {
            return round((float) $monto, 2);
        }

        return self::convertir((float) $monto, $this->tipoCambioDeSobre($tipoCambioDelSobre, $fechaCulto));
    }

    /** Lo mismo que sobreAColones, en centimos enteros (para sumar sin errores de redondeo). */
    public function centimosSobre(float|int|string|null $monto, ?string $moneda, float|string|null $tipoCambioDelSobre, DateTimeInterface|string|null $fechaCulto): int
    {
        return (int) round($this->sobreAColones($monto, $moneda, $tipoCambioDelSobre, $fechaCulto) * 100);
    }

    /** Lo prometido para un mes (ya multiplicado por la frecuencia), en colones. */
    public function promesaAColones(float $montoDelMes, ?string $moneda, int $anio, int $mes): float
    {
        if ($moneda !== 'USD') {
            return round($montoDelMes, 2);
        }

        return self::convertir($montoDelMes, $this->tipoCambioDePromesa($anio, $mes));
    }

    public function centimosPromesa(float $montoDelMes, ?string $moneda, int $anio, int $mes): int
    {
        return (int) round($this->promesaAColones($montoDelMes, $moneda, $anio, $mes) * 100);
    }

    // ------------------------------------------------------------------

    private static function convertir(float $usd, float $tipoCambio): float
    {
        return $tipoCambio > 0 ? round($usd * $tipoCambio, 2) : round($usd, 2);
    }

    private function leer(): void
    {
        if ($this->fechas !== null) {
            return;
        }

        $this->cargar(DB::table('tipo_cambios')->where('venta', '>', 0)->orderBy('fecha')->pluck('venta', 'fecha')->all());
    }

    /** @param  array<string, float|string>  $tabla */
    private function cargar(array $tabla): void
    {
        $porDia = [];
        foreach ($tabla as $fecha => $venta) {
            if ((float) $venta > 0) {
                $porDia[substr((string) $fecha, 0, 10)] = (float) $venta;
            }
        }
        ksort($porDia);
        $this->fechas = array_keys($porDia);
        $this->ventas = array_values($porDia);
        $this->hoy = null;
    }

    /** Venta de la ultima fecha <= $dia (busqueda binaria); null si todas son posteriores. */
    private function buscar(string $dia): ?float
    {
        $bajo = 0;
        $alto = count($this->fechas) - 1;
        $encontrado = null;
        while ($bajo <= $alto) {
            $medio = intdiv($bajo + $alto, 2);
            if ($this->fechas[$medio] <= $dia) {
                $encontrado = $medio;
                $bajo = $medio + 1;
            } else {
                $alto = $medio - 1;
            }
        }

        return $encontrado === null ? null : $this->ventas[$encontrado];
    }

    private static function dia(DateTimeInterface|string $fecha): string
    {
        return $fecha instanceof DateTimeInterface ? $fecha->format('Y-m-d') : substr($fecha, 0, 10);
    }
}

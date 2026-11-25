<?php

namespace App\Services\Reportes\Ingresos;

use App\Services\Reportes\Cultos\FiltroCultos;
use App\Services\Reportes\Cultos\GeneradorReporteCultos;
use App\Services\Reportes\Cultos\ReporteCultos;
use App\Services\Reportes\Dinero;

/**
 * Reporte de ingresos (HU-R11): lo recibido en un rango por categoria y por metodo de pago, contra
 * el periodo anterior de igual duracion (RangoFechas::anterior: los mismos meses antes si el rango
 * son meses completos; si no, los mismos dias justo antes).
 *
 * Las cifras salen del reporte de cultos (ResumenCulto por culto), asi que cuadran al centimo con
 * el recuento de cada culto y con el Reporte de cultos del mismo rango.
 */
final class ReporteIngresos
{
    public function __construct(
        public readonly ReporteCultos $actual,
        public readonly ReporteCultos $anterior,
    ) {}

    public static function generar(FiltroCultos $filtro, ?GeneradorReporteCultos $generador = null): self
    {
        $generador ??= app(GeneradorReporteCultos::class);
        // Los cultos elegidos no aplican aqui: el ingreso es de todo el rango (y de los tipos pedidos)
        $filtro = new FiltroCultos($filtro->rango, $filtro->tipos, [], $filtro->errores, $filtro->recortado);
        $actual = $generador->generar($filtro);

        return new self($actual, $generador->generar($filtro->anterior(), $actual->categorias));
    }

    public function filtro(): FiltroCultos
    {
        return $this->actual->filtro;
    }

    /** "del 01/09/2026 al 30/09/2026" del periodo con el que se compara. */
    public function etiquetaAnterior(): string
    {
        $r = $this->anterior->filtro->rango;

        return 'del '.$r->inicio->format('d/m/Y').' al '.$r->fin->format('d/m/Y');
    }

    /**
     * Lineas por categoria con el comparativo.
     *
     * @return list<array{nombre: string, actual: float, anterior: float, diferencia: float, porcentaje: ?float}>
     */
    public function porCategoria(): array
    {
        $anterior = collect($this->anterior->porCategoria())->keyBy('slug');
        $lineas = [];
        foreach ($this->actual->porCategoria() as $linea) {
            $lineas[] = self::comparar($linea['nombre'], $linea['monto'], (float) ($anterior->get($linea['slug'])['monto'] ?? 0));
            $anterior->forget($linea['slug']);
        }
        // Lo que hubo antes y ahora no (p. ej. "Otras categorías")
        foreach ($anterior as $linea) {
            $lineas[] = self::comparar($linea['nombre'], 0.0, $linea['monto']);
        }

        return $lineas;
    }

    /** @return list<array{nombre: string, cantidad: ?int, cantidad_anterior: ?int, actual: float, anterior: float, diferencia: float, porcentaje: ?float}> */
    public function porMetodo(): array
    {
        $anterior = collect($this->anterior->porMetodo())->keyBy('clave');

        return array_map(function (array $linea) use ($anterior) {
            $antes = $anterior->get($linea['clave']);

            return self::comparar($linea['nombre'], $linea['monto'], (float) $antes['monto']) + [
                'cantidad' => $linea['cantidad'],
                'cantidad_anterior' => $antes['cantidad'],
            ];
        }, $this->actual->porMetodo());
    }

    /** @return array{nombre: string, actual: float, anterior: float, diferencia: float, porcentaje: ?float} */
    public function total(): array
    {
        return self::comparar('Total de ingresos', $this->actual->totales()['ingresos'], $this->anterior->totales()['ingresos']);
    }

    /**
     * Diferencia en monto y en porcentaje. Sin ingresos en el periodo anterior no hay porcentaje
     * (no se divide entre cero): la pantalla muestra "sin datos antes".
     *
     * @return array{nombre: string, actual: float, anterior: float, diferencia: float, porcentaje: ?float}
     */
    public static function comparar(string $nombre, float $actual, float $anterior): array
    {
        $diferencia = Dinero::colones(Dinero::centimos($actual) - Dinero::centimos($anterior));

        return [
            'nombre' => $nombre,
            'actual' => $actual,
            'anterior' => $anterior,
            'diferencia' => $diferencia,
            'porcentaje' => abs($anterior) < 0.005 ? null : round($diferencia / $anterior * 100, 1),
        ];
    }

    /** "+12.5 %", "-3.0 %" o "sin datos antes". */
    public static function textoPorcentaje(?float $porcentaje): string
    {
        if ($porcentaje === null) {
            return 'sin datos antes';
        }

        return ($porcentaje > 0 ? '+' : '').number_format($porcentaje, 1).' %';
    }
}

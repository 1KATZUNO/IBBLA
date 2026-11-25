<?php

namespace App\Services\Reportes\Exportaciones;

use App\Services\Reportes\Cultos\FilaCulto;
use App\Services\Reportes\Cultos\FiltroCultos;
use App\Services\Reportes\Ingresos\ReporteIngresos;
use App\Support\Excel\LibroExcel;

/**
 * Excel del Reporte de ingresos (HU-R11) con la plantilla unica: hoja Resumen (por categoria y por
 * metodo con el comparativo) y hoja Detalle (una fila por culto del periodo).
 */
class IngresosExcel
{
    public function generar(ReporteIngresos $reporte): LibroExcel
    {
        $filtro = $reporte->filtro();
        $libro = new LibroExcel('Reporte de ingresos', $filtro->etiquetaPeriodo(), $filtro->descripcion(FiltroCultos::tiposDisponibles()));
        $actual = $reporte->actual->totales();
        $total = $reporte->total();

        $columnas = [
            ['titulo' => 'Concepto', 'ancho' => 34],
            ['titulo' => 'Este periodo', 'ancho' => 18, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Periodo anterior', 'ancho' => 18, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Diferencia', 'ancho' => 18, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Variación', 'ancho' => 16],
        ];
        $fila = fn (array $l) => [$l['nombre'], $l['actual'], $l['anterior'], $l['diferencia'], ReporteIngresos::textoPorcentaje($l['porcentaje'])];

        $hoja = $libro->hoja('Resumen')
            ->encabezado($reporte->textoComparacion().'. Cifras en colones con la misma regla del recuento de cada culto.', 5)
            ->tarjetas([
                ['etiqueta' => 'Cultos', 'valor' => $actual['cultos'], 'formato' => LibroExcel::ENTERO],
                ['etiqueta' => 'Ingresos', 'valor' => $actual['ingresos'], 'formato' => LibroExcel::MONEDA_DECIMALES],
                ['etiqueta' => 'Periodo anterior', 'valor' => $total['anterior'], 'formato' => LibroExcel::MONEDA_DECIMALES],
                ['etiqueta' => 'Egresos', 'valor' => $actual['egresos'], 'formato' => LibroExcel::MONEDA_DECIMALES],
                ['etiqueta' => 'Neto', 'valor' => $actual['total'], 'formato' => LibroExcel::MONEDA_DECIMALES],
            ]);
        $hoja->texto('Ingresos por categoría', negrita: true);
        $hoja->tabla($columnas, array_map($fila, $reporte->porCategoria()), total: $fila($total), autofiltro: false);
        $hoja->texto('Ingresos por método de pago', negrita: true);
        $hoja->tabla($columnas, array_map($fila, $reporte->porMetodo()), total: $fila($total), autofiltro: false);
        // Dos tablas en la hoja: sin panel fijo (quedaria fijo en la segunda)
        $hoja->delegado()->unfreezePane();

        $libro->hoja('Detalle')->tabla([
            ['titulo' => 'Fecha', 'ancho' => 12, 'formato' => LibroExcel::FECHA],
            ['titulo' => 'Tipo', 'ancho' => 16],
            ['titulo' => 'Sobres', 'ancho' => 9, 'formato' => LibroExcel::ENTERO],
            ['titulo' => 'Sobres en efectivo', 'ancho' => 18, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Transferencias', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Suelto', 'ancho' => 14, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Ingresos', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
        ], array_map(fn (FilaCulto $f) => [
            $f->culto->fecha->toDateTimeImmutable(), $f->tipo, $f->sobres, $f->sobresEfectivo, $f->transferencias, $f->sueltos, $f->ingresos(),
        ], $reporte->actual->filas), total: ['TOTAL', '', $actual['sobres'], $actual['sobres_efectivo'], $actual['transferencias'], $actual['sueltos'], $actual['ingresos']]);

        return $libro;
    }
}

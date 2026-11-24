<?php

namespace App\Services\Reportes\Exportaciones;

use App\Services\Reportes\Cultos\FilaCulto;
use App\Services\Reportes\Cultos\FiltroCultos;
use App\Services\Reportes\Cultos\ReporteCultos;
use App\Support\Excel\LibroExcel;

/**
 * Excel del Reporte de cultos (HU-R10) con la plantilla unica: hoja Resumen (una fila por culto y
 * los desgloses) y hoja Detalle (una fila por culto y categoria, para filtrar y hacer tablas
 * dinamicas). Montos en colones como numeros reales.
 */
class CultosExcel
{
    public function generar(ReporteCultos $reporte): LibroExcel
    {
        $filtro = $reporte->filtro;
        $libro = new LibroExcel('Reporte de cultos', $filtro->etiquetaPeriodo(), $filtro->descripcion(FiltroCultos::tiposDisponibles()));
        $t = $reporte->totales();

        $resumen = $libro->hoja('Resumen')
            ->encabezado('Cifras en colones con la misma regla del recuento de cada culto. Total = efectivo (sobres + suelto - egresos) + transferencias.', 10)
            ->tarjetas([
                ['etiqueta' => 'Cultos', 'valor' => $t['cultos'], 'formato' => LibroExcel::ENTERO],
                ['etiqueta' => 'Sobres', 'valor' => $t['sobres'], 'formato' => LibroExcel::ENTERO],
                ['etiqueta' => 'Ingresos', 'valor' => $t['ingresos'], 'formato' => LibroExcel::MONEDA_DECIMALES],
                ['etiqueta' => 'Egresos', 'valor' => $t['egresos'], 'formato' => LibroExcel::MONEDA_DECIMALES],
                ['etiqueta' => 'Total del rango', 'valor' => $t['total'], 'formato' => LibroExcel::MONEDA_DECIMALES],
                ['etiqueta' => 'Sin firmas completas', 'valor' => $t['sin_firmas'], 'formato' => LibroExcel::ENTERO],
            ]);

        $resumen->tabla([
            ['titulo' => 'Fecha', 'ancho' => 12, 'formato' => LibroExcel::FECHA],
            ['titulo' => 'Tipo', 'ancho' => 16],
            ['titulo' => 'Estado', 'ancho' => 10],
            ['titulo' => 'Sobres', 'ancho' => 9, 'formato' => LibroExcel::ENTERO],
            ['titulo' => 'Efectivo', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Transferencias', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Suelto', 'ancho' => 14, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Egresos', 'ancho' => 14, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Total', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Firmas completas', 'ancho' => 12, 'colores' => ['No' => LibroExcel::ROJO_SUAVE, 'Sí' => LibroExcel::VERDE_SUAVE]],
        ], array_map(fn (FilaCulto $f) => [
            $f->culto->fecha->toDateTimeImmutable(),
            $f->tipo,
            $f->estado(),
            $f->sobres,
            $f->efectivo,
            $f->transferencias,
            $f->sueltos,
            $f->egresos,
            $f->total,
            $f->firmasCompletas ? 'Sí' : 'No',
        ], $reporte->filas), total: ['TOTAL', '', '', $t['sobres'], $t['efectivo'], $t['transferencias'], $t['sueltos'], $t['egresos'], $t['total'], '']);

        self::desgloses($libro->hoja('Por categoría y método'), $reporte);

        // Detalle: una fila por culto y categoria con monto (los ceros se omiten)
        $detalle = [];
        foreach ($reporte->filas as $f) {
            foreach ($reporte->categorias as $categoria) {
                $monto = (float) ($f->porCategoria[$categoria->slug] ?? 0);
                if ($monto != 0.0) {
                    $detalle[] = [$f->culto->fecha->toDateTimeImmutable(), $f->tipo, $categoria->nombre, $monto];
                }
            }
            if ($f->otrasCategorias != 0.0) {
                $detalle[] = [$f->culto->fecha->toDateTimeImmutable(), $f->tipo, 'Otras categorías (ya no activas)', $f->otrasCategorias];
            }
            if ($f->sueltos != 0.0) {
                $detalle[] = [$f->culto->fecha->toDateTimeImmutable(), $f->tipo, 'Dinero suelto', $f->sueltos];
            }
        }
        $libro->hoja('Detalle')->tabla([
            ['titulo' => 'Fecha', 'ancho' => 12, 'formato' => LibroExcel::FECHA],
            ['titulo' => 'Tipo', 'ancho' => 16],
            ['titulo' => 'Categoría', 'ancho' => 30],
            ['titulo' => 'Monto', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
        ], $detalle, total: ['TOTAL', '', '', $t['ingresos']]);

        return $libro;
    }

    /** Tablas de desglose por categoria y por metodo (tambien las usa el Excel de ingresos). */
    public static function desgloses(\App\Support\Excel\HojaExcel $hoja, ReporteCultos $reporte): void
    {
        $t = $reporte->totales();
        $hoja->tabla([
            ['titulo' => 'Categoría', 'ancho' => 30],
            ['titulo' => 'Monto', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
        ], array_map(fn ($l) => [$l['nombre'], $l['monto']], $reporte->porCategoria()), total: ['Total de ingresos', $t['ingresos']], autofiltro: false);
        $hoja->filaVacia();
        $hoja->tabla([
            ['titulo' => 'Método de pago', 'ancho' => 30],
            ['titulo' => 'Sobres', 'ancho' => 16, 'formato' => LibroExcel::ENTERO],
            ['titulo' => 'Monto', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
        ], array_map(fn ($l) => [$l['nombre'], $l['cantidad'], $l['monto']], $reporte->porMetodo()), total: ['Total de ingresos', $t['sobres'], $t['ingresos']], autofiltro: false);
        // Dos tablas en la hoja: sin panel fijo (quedaria fijo en la segunda)
        $hoja->delegado()->unfreezePane();
    }
}

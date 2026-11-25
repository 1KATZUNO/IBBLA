<?php

namespace App\Services\Reportes\Exportaciones;

use App\Services\Reportes\Cultos\FiltroCultos;
use App\Services\Reportes\Dinero;
use App\Services\Reportes\Ingresos\ReporteIngresos;
use App\Support\Pdf\DocumentoPdf;

/**
 * PDF del Reporte de ingresos (HU-R11) con la plantilla unica: resumen, ingresos por categoria y
 * por metodo de pago contra el periodo anterior de igual duracion.
 */
class IngresosPdf
{
    private const COLUMNAS = [
        ['titulo' => 'Concepto', 'ancho' => 4.5],
        ['titulo' => 'Este periodo', 'ancho' => 2.3, 'alinear' => 'derecha'],
        ['titulo' => 'Periodo anterior', 'ancho' => 2.3, 'alinear' => 'derecha'],
        ['titulo' => 'Diferencia', 'ancho' => 2.3, 'alinear' => 'derecha'],
        ['titulo' => 'Variación', 'ancho' => 1.8, 'alinear' => 'derecha'],
    ];

    public function generar(ReporteIngresos $reporte): string
    {
        $filtro = $reporte->filtro();
        $doc = new DocumentoPdf('Reporte de ingresos', $filtro->etiquetaPeriodo(), $filtro->descripcion(FiltroCultos::tiposDisponibles()));
        $doc->encabezado();
        $doc->nota('Se compara con el periodo anterior de igual duración, '.$reporte->etiquetaAnterior().'. Cifras en colones con la misma regla del recuento de cada culto (dólares al tipo de cambio del sobre).');

        $actual = $reporte->actual->totales();
        $total = $reporte->total();
        $doc->tarjetas([
            ['etiqueta' => 'Cultos', 'valor' => (string) $actual['cultos']],
            ['etiqueta' => 'Ingresos', 'valor' => Dinero::formato($actual['ingresos'])],
            ['etiqueta' => 'Periodo anterior', 'valor' => Dinero::formato($total['anterior'])],
            ['etiqueta' => 'Variación', 'valor' => ReporteIngresos::textoPorcentaje($total['porcentaje']), 'color' => self::color($total['diferencia'])],
            ['etiqueta' => 'Egresos', 'valor' => Dinero::formato($actual['egresos'])],
            ['etiqueta' => 'Neto (ingresos - egresos)', 'valor' => Dinero::formato($actual['total'])],
        ]);

        if ($reporte->actual->vacio()) {
            $doc->parrafo('No hay cultos con ingresos en este rango.', 10, DocumentoPdf::GRIS);
        }

        $doc->tituloSeccion('Ingresos por categoría');
        $doc->tabla(self::COLUMNAS, array_map(self::fila(...), $reporte->porCategoria()), total: self::fila($total, true));

        $doc->tituloSeccion('Ingresos por método de pago');
        $doc->tabla(self::COLUMNAS, array_map(self::fila(...), $reporte->porMetodo()), total: self::fila($total, true));

        return $doc->salida();
    }

    /** @param  array{nombre: string, actual: float, anterior: float, diferencia: float, porcentaje: ?float}  $l */
    private static function fila(array $l, bool $negrita = false): array
    {
        return [
            $negrita ? 'Total de ingresos' : $l['nombre'],
            Dinero::formato($l['actual']),
            Dinero::formato($l['anterior']),
            ['texto' => Dinero::formato($l['diferencia'], conSigno: true), 'color' => self::color($l['diferencia'])],
            ['texto' => ReporteIngresos::textoPorcentaje($l['porcentaje']), 'color' => self::color($l['diferencia'])],
        ];
    }

    private static function color(float $diferencia): string
    {
        return $diferencia > 0 ? DocumentoPdf::VERDE : ($diferencia < 0 ? DocumentoPdf::ROJO : DocumentoPdf::TINTA);
    }

    public static function nombreArchivo(ReporteIngresos $reporte, string $extension = 'pdf'): string
    {
        $r = $reporte->filtro()->rango;

        return 'reporte_ingresos_'.$r->inicio->toDateString().'_a_'.$r->fin->toDateString().'.'.$extension;
    }
}

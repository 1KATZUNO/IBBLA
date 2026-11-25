<?php

namespace App\Services\Reportes\Exportaciones;

use App\Services\Reportes\Cultos\FilaCulto;
use App\Services\Reportes\Cultos\FiltroCultos;
use App\Services\Reportes\Cultos\ReporteCultos;
use App\Services\Reportes\Dinero;
use App\Support\Pdf\DocumentoPdf;

/**
 * PDF del Reporte de cultos (HU-R10) con la plantilla unica: resumen del rango, una fila por culto,
 * y el desglose por categoria y por metodo de pago. Carta horizontal para que quepan las columnas.
 */
class CultosPdf
{
    public function generar(ReporteCultos $reporte): string
    {
        $filtro = $reporte->filtro;
        $doc = new DocumentoPdf('Reporte de cultos', $filtro->etiquetaPeriodo(), $filtro->descripcion(FiltroCultos::tiposDisponibles()), 'landscape');
        $doc->encabezado();
        $doc->nota('Cifras en colones con la misma regla del recuento de cada culto: los dólares al tipo de cambio del sobre (o al del día del culto). Total = efectivo (sobres + suelto - egresos) + transferencias.');

        $t = $reporte->totales();
        $doc->tarjetas([
            ['etiqueta' => 'Cultos', 'valor' => (string) $t['cultos']],
            ['etiqueta' => 'Sobres', 'valor' => number_format($t['sobres'])],
            ['etiqueta' => 'Ingresos', 'valor' => Dinero::formato($t['ingresos'])],
            ['etiqueta' => 'Egresos', 'valor' => Dinero::formato($t['egresos'])],
            ['etiqueta' => 'Total del rango', 'valor' => Dinero::formato($t['total'])],
            ['etiqueta' => 'Sin firmas completas', 'valor' => (string) $t['sin_firmas'], 'color' => $t['sin_firmas'] > 0 ? DocumentoPdf::ROJO : DocumentoPdf::VERDE],
        ]);

        if ($reporte->vacio()) {
            $doc->parrafo('No hay cultos en este rango con los filtros elegidos.', 10, DocumentoPdf::GRIS);

            return $doc->salida();
        }

        $doc->tituloSeccion('Cultos ('.$t['cultos'].')');
        $filas = array_map(fn (FilaCulto $f) => [
            $f->culto->fecha->format('d/m/Y'),
            $f->tipo,
            $f->estado(),
            (string) $f->sobres,
            Dinero::formato($f->efectivo),
            Dinero::formato($f->transferencias),
            Dinero::formato($f->sueltos),
            Dinero::formato($f->egresos),
            ['texto' => Dinero::formato($f->total), 'negrita' => true],
            $f->firmasCompletas ? ['texto' => 'Sí', 'color' => DocumentoPdf::VERDE] : ['texto' => 'No', 'color' => DocumentoPdf::ROJO],
        ], $reporte->filas);

        $doc->tabla([
            ['titulo' => 'Fecha', 'ancho' => 1.6],
            ['titulo' => 'Tipo', 'ancho' => 2.0],
            ['titulo' => 'Estado', 'ancho' => 1.3],
            ['titulo' => 'Sobres', 'ancho' => 1.1, 'alinear' => 'derecha'],
            ['titulo' => 'Efectivo', 'ancho' => 2.2, 'alinear' => 'derecha'],
            ['titulo' => 'Transferencias', 'ancho' => 2.2, 'alinear' => 'derecha'],
            ['titulo' => 'Suelto', 'ancho' => 1.8, 'alinear' => 'derecha'],
            ['titulo' => 'Egresos', 'ancho' => 1.8, 'alinear' => 'derecha'],
            ['titulo' => 'Total', 'ancho' => 2.2, 'alinear' => 'derecha'],
            ['titulo' => 'Firmas', 'ancho' => 1.1, 'alinear' => 'centro'],
        ], $filas, total: [
            'Total', '', '', number_format($t['sobres']), Dinero::formato($t['efectivo']), Dinero::formato($t['transferencias']),
            Dinero::formato($t['sueltos']), Dinero::formato($t['egresos']), Dinero::formato($t['total']), '',
        ]);

        self::desgloses($doc, $reporte);

        return $doc->salida();
    }

    /** Desglose por categoria y por metodo de pago (tambien lo usa el PDF de ingresos). */
    public static function desgloses(DocumentoPdf $doc, ReporteCultos $reporte): void
    {
        $t = $reporte->totales();
        $doc->tituloSeccion('Ingresos por categoría');
        $doc->tabla([
            ['titulo' => 'Categoría', 'ancho' => 5],
            ['titulo' => 'Monto', 'ancho' => 2.5, 'alinear' => 'derecha'],
        ], array_map(fn ($l) => [$l['nombre'], Dinero::formato($l['monto'])], $reporte->porCategoria()), total: ['Total de ingresos', Dinero::formato($t['ingresos'])]);

        $doc->tituloSeccion('Ingresos por método de pago');
        $doc->tabla([
            ['titulo' => 'Método', 'ancho' => 4],
            ['titulo' => 'Sobres', 'ancho' => 1, 'alinear' => 'derecha'],
            ['titulo' => 'Monto', 'ancho' => 2.5, 'alinear' => 'derecha'],
        ], array_map(fn ($l) => [$l['nombre'], $l['cantidad'] === null ? '' : number_format($l['cantidad']), Dinero::formato($l['monto'])], $reporte->porMetodo()), total: ['Total de ingresos', number_format($t['sobres']), Dinero::formato($t['ingresos'])]);
    }

    public static function nombreArchivo(ReporteCultos $reporte, string $extension = 'pdf'): string
    {
        $r = $reporte->filtro->rango;

        return 'reporte_cultos_'.$r->inicio->toDateString().'_a_'.$r->fin->toDateString().'.'.$extension;
    }
}

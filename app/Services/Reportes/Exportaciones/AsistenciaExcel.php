<?php

namespace App\Services\Reportes\Exportaciones;

use App\Support\Excel\LibroExcel;
use Illuminate\Support\Collection;

/**
 * Excel de asistencia por culto (rango o mes) con la plantilla unica (HU-R14): hoja Resumen con el
 * encabezado (iglesia, periodo, fecha de generacion) y hoja Asistencia con una fila por culto desde
 * la fila 1 (para los graficos propios de Roy), totales, encabezados fijos y autofiltro. Mismas
 * cifras que el Excel anterior (App\Exports\AsistenciaExport) y que el PDF (AsistenciaPdf::tabla).
 */
class AsistenciaExcel
{
    public function generar(Collection $cultos, iterable $tipos, string $titulo, string $periodo): LibroExcel
    {
        $tabla = AsistenciaPdf::tabla($cultos, $tipos);
        $conAsistencia = $cultos->filter(fn ($c) => $c->asistencia)->values();
        $libro = new LibroExcel($titulo, $periodo);
        $cuantos = count($tabla['filas']);

        $libro->hoja('Resumen')
            ->encabezado(null, 4)
            ->tarjetas([
                ['etiqueta' => 'Cultos con asistencia', 'valor' => $cuantos, 'formato' => LibroExcel::ENTERO],
                ['etiqueta' => 'Asistencia total', 'valor' => $tabla['totales'][0], 'formato' => LibroExcel::ENTERO],
                ['etiqueta' => 'Promedio por culto', 'valor' => $cuantos > 0 ? round($tabla['totales'][0] / $cuantos, 1) : 0, 'formato' => LibroExcel::DECIMAL],
            ]);

        $columnas = [];
        foreach (array_merge($tabla['columnas'], ['Retroactivo']) as $i => $nombre) {
            $columnas[] = match ($i) {
                0 => ['titulo' => $nombre, 'ancho' => 12, 'formato' => LibroExcel::FECHA],
                1 => ['titulo' => $nombre, 'ancho' => 16],
                default => ['titulo' => $nombre, 'ancho' => 11, 'formato' => $nombre === 'Retroactivo' ? null : LibroExcel::ENTERO, 'alinear' => 'centro'],
            };
        }
        $filas = [];
        foreach ($tabla['filas'] as $i => $fila) {
            $culto = $conAsistencia[$i];
            $fila[0] = $culto->fecha->toDateTimeImmutable();
            $fila[] = $culto->asistencia->cargado_retroactivo ? 'Sí' : '';
            $filas[] = $fila;
        }
        $libro->hoja('Asistencia')->tabla($columnas, $filas, total: array_merge(['TOTALES', ''], $tabla['totales'], ['']), columnasFijas: 2);

        return $libro;
    }
}

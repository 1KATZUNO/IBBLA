<?php

namespace App\Services\Reportes\Exportaciones;

use App\Support\Pdf\DocumentoPdf;
use Illuminate\Support\Collection;

/**
 * PDF de asistencia por culto (rango o mes) con la plantilla unica (HU-R14): encabezado con iglesia,
 * periodo y filtros, una fila por culto con asistencia, totales y pie con la fecha de generacion.
 * Las cifras son las mismas del PDF anterior (pdfs/asistencia y pdfs/asistencia-mes): salen de los
 * mismos metodos de Asistencia y de los registros extra.
 */
class AsistenciaPdf
{
    /**
     * Columnas, filas y totales de la tabla (tambien para comparar en los tests).
     *
     * @param  Collection<int, \App\Models\Culto>  $cultos  con asistencia.registrosExtra cargado
     * @param  iterable<\App\Models\RegistroExtraTipo>  $tipos  registros extra activos
     * @return array{columnas: list<string>, filas: list<list<string|int>>, totales: list<int>}
     */
    public static function tabla(Collection $cultos, iterable $tipos): array
    {
        $extras = [];
        foreach ($tipos as $tipo) {
            foreach ($tipo->subcampos as $subcampo) {
                $extras[] = [$tipo, $subcampo, count($tipo->subcampos) === 1 ? $tipo->nombre : ucfirst($subcampo)];
            }
        }

        $filas = [];
        $totales = array_fill(0, 8 + count($extras), 0);
        foreach ($cultos as $culto) {
            $a = $culto->asistencia;
            if (! $a) {
                continue;
            }
            $valores = [
                (int) $a->total_asistencia,
                $a->getTotalHombres(),
                $a->getTotalMujeres(),
                $a->getTotalNinos(),
                $a->getTotalCapilla(),
                $a->getTotalVisitas(),
                $a->getTotalSalvos(),
                $a->getTotalBautismos(),
            ];
            foreach ($extras as [$tipo, $subcampo]) {
                $registro = $a->registrosExtra->firstWhere('registro_extra_tipo_id', $tipo->id);
                $valores[] = $registro ? (int) ($registro->valores[$subcampo] ?? 0) : 0;
            }
            foreach ($valores as $i => $valor) {
                $totales[$i] += (int) $valor;
            }
            $filas[] = array_merge([
                ucfirst($culto->fecha->locale('es')->isoFormat('ddd D MMM YYYY')),
                $culto->tipo_nombre,
            ], $valores);
        }

        return [
            'columnas' => array_merge(['Fecha', 'Tipo', 'Total', 'Hombres', 'Mujeres', 'Niños', 'Capilla', 'Visitas', 'Salvos', 'Bautismos'], array_column($extras, 2)),
            'filas' => $filas,
            'totales' => $totales,
        ];
    }

    /** @param  list<string>  $filtros */
    public function generar(Collection $cultos, iterable $tipos, string $titulo, string $periodo, array $filtros = []): string
    {
        $tabla = self::tabla($cultos, $tipos);
        $doc = new DocumentoPdf($titulo, $periodo, $filtros, 'landscape');
        $doc->encabezado();

        $doc->tarjetas([
            ['etiqueta' => 'Cultos con asistencia', 'valor' => (string) count($tabla['filas'])],
            ['etiqueta' => 'Asistencia total', 'valor' => number_format($tabla['totales'][0])],
            ['etiqueta' => 'Promedio por culto', 'valor' => count($tabla['filas']) > 0 ? number_format($tabla['totales'][0] / count($tabla['filas']), 1) : '0'],
        ]);

        if ($tabla['filas'] === []) {
            $doc->parrafo('No hay asistencia registrada en este periodo.', 10, DocumentoPdf::GRIS);

            return $doc->salida();
        }

        $columnas = [];
        foreach ($tabla['columnas'] as $i => $nombre) {
            $columnas[] = match ($i) {
                0 => ['titulo' => $nombre, 'ancho' => 2.4],
                1 => ['titulo' => $nombre, 'ancho' => 2.0],
                default => ['titulo' => $nombre, 'ancho' => 1.1, 'alinear' => 'centro'],
            };
        }
        $filas = array_map(fn (array $f) => array_map(fn ($v) => is_int($v) ? number_format($v) : $v, $f), $tabla['filas']);
        $doc->tabla($columnas, $filas, total: array_merge(['Totales', ''], array_map(fn ($v) => number_format($v), $tabla['totales'])), tam: count($columnas) > 12 ? 7.0 : 8.0);

        return $doc->salida();
    }
}

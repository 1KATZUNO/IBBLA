<?php

namespace App\Services\Reportes\Exportaciones;

use App\Services\InformePersonaService;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\LoteEstadosCuenta;
use App\Support\Excel\LibroExcel;
use Carbon\CarbonImmutable;

/**
 * Estados de cuenta en Excel (HU-R5), con la plantilla unica:
 *  - Resumen: una fila por persona, lo dado por categoria, total, esperado, dado en compromisos,
 *    diferencia y estado (+ fila de totales).
 *  - Compromisos: una fila por persona y categoria prometida.
 *  - Detalle: un renglon por monto de cada sobre (fecha, culto, sobre, categoria, monto, moneda,
 *    tipo de cambio y colones), para revisar de donde sale cada numero.
 */
class EstadoCuentaExcel
{
    private const COLORES_ESTADO = ['AL DÍA' => LibroExcel::VERDE_SUAVE, 'PENDIENTE' => LibroExcel::ROJO_SUAVE];

    public function generar(LoteEstadosCuenta $lote): LibroExcel
    {
        $filtros = $lote->filtro->grupo === FiltroPersonas::TODAS ? [] : [$lote->filtro->descripcion()];
        $libro = new LibroExcel('Estados de cuenta', $lote->periodo->etiqueta(), $filtros, generadoEn: $lote->generadoEn);

        $this->resumen($libro, $lote);
        $this->compromisos($libro, $lote);
        $this->detalle($libro, $lote);

        return $libro;
    }

    public static function nombreArchivo(LoteEstadosCuenta $lote): string
    {
        return 'estados-de-cuenta_'.$lote->periodo->sufijoArchivo().'.xlsx';
    }

    private function resumen(LibroExcel $libro, LoteEstadosCuenta $lote): void
    {
        // Columnas: las categorias en que alguien del lote dio o prometio, en el orden de la iglesia.
        $categorias = [];
        foreach ($lote->estados as $datos) {
            $categorias += $datos['categorias'];
        }
        $ordenadas = array_intersect_key($lote->resultado->categorias, $categorias) + $categorias;

        $columnas = [['titulo' => 'Nombre', 'ancho' => 34]];
        foreach ($ordenadas as $nombre) {
            $columnas[] = ['titulo' => $nombre, 'ancho' => 14, 'formato' => LibroExcel::MONEDA_DECIMALES];
        }
        array_push($columnas,
            ['titulo' => 'Total contribuido', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Esperado a la fecha', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Dado en compromisos', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Diferencia', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Estado', 'ancho' => 14, 'colores' => self::COLORES_ESTADO],
        );

        $filas = [];
        $totales = array_fill(0, count($columnas) - 2, 0);
        foreach ($lote->estados as $datos) {
            $fila = [$datos['persona']->nombre];
            foreach (array_keys($ordenadas) as $slug) {
                $fila[] = $datos['totales_categoria'][$slug] ?? 0.0;
            }
            array_push($fila, $datos['total_dado']);
            if ($datos['tiene_compromisos']) {
                array_push($fila, $datos['total_esperado'], $datos['dado_en_compromisos'], $datos['diferencia'], $datos['al_dia'] ? 'AL DÍA' : 'PENDIENTE');
            } else {
                array_push($fila, null, null, null, 'SIN PROMESA');
            }
            foreach (array_slice($fila, 1, count($fila) - 2) as $i => $valor) {
                $totales[$i] += (int) round(((float) $valor) * 100);
            }
            $filas[] = $fila;
        }

        $nota = 'Una fila por persona con lo que dio en cada categoría y, si tiene promesas, lo esperado y el estado. '.$lote->explicacionEsperado();
        $libro->hoja('Resumen')
            ->encabezado($nota, columnas: min(count($columnas), 12))
            ->tabla($columnas, $filas, array_merge(['TOTAL'], array_map(fn (int $c) => $c / 100, $totales), ['']), columnasFijas: 1);
    }

    private function compromisos(LibroExcel $libro, LoteEstadosCuenta $lote): void
    {
        $filas = [];
        foreach ($lote->estados as $datos) {
            foreach ($datos['compromisos'] as $c) {
                $filas[] = [
                    $datos['persona']->nombre,
                    $c['nombre'],
                    $c['detalle'],
                    $c['esperado'],
                    $c['dado'],
                    $c['diferencia'],
                    $c['categoria'] === InformePersonaService::OTRAS_CATEGORIAS ? 'SIN PROMESA' : ($c['al_dia'] ? 'AL DÍA' : 'PENDIENTE'),
                ];
            }
        }

        $libro->hoja('Compromisos')->tabla([
            ['titulo' => 'Nombre', 'ancho' => 34],
            ['titulo' => 'Categoría', 'ancho' => 22],
            ['titulo' => 'Compromiso', 'ancho' => 36],
            ['titulo' => 'Esperado a la fecha', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Dado', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Diferencia', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Estado', 'ancho' => 14, 'colores' => self::COLORES_ESTADO],
        ], $filas, columnasFijas: 1);
    }

    private function detalle(LibroExcel $libro, LoteEstadosCuenta $lote): void
    {
        $filas = [];
        foreach ($lote->estados as $datos) {
            $cuenta = $lote->resultado->cuenta($datos['persona']->id);
            foreach ($cuenta?->detalle ?? [] as $renglon) {
                $filas[] = [
                    $datos['persona']->nombre,
                    CarbonImmutable::parse($renglon['fecha']),
                    $this->culto($renglon['culto']),
                    $renglon['sobre'],
                    $lote->resultado->nombreCategoria($renglon['categoria']),
                    $renglon['monto'],
                    $renglon['moneda'],
                    $renglon['tipo_cambio'],
                    $renglon['colones'],
                    $renglon['metodo_pago'] === 'transferencia' ? 'Transferencia' : 'Efectivo',
                ];
            }
        }

        $libro->hoja('Detalle')->tabla([
            ['titulo' => 'Nombre', 'ancho' => 34],
            ['titulo' => 'Fecha', 'ancho' => 12, 'formato' => LibroExcel::FECHA, 'alinear' => 'centro'],
            ['titulo' => 'Culto', 'ancho' => 14],
            ['titulo' => 'Sobre', 'ancho' => 8, 'alinear' => 'centro'],
            ['titulo' => 'Categoría', 'ancho' => 20],
            ['titulo' => 'Monto', 'ancho' => 14, 'formato' => LibroExcel::DECIMAL],
            ['titulo' => 'Moneda', 'ancho' => 9, 'alinear' => 'centro'],
            ['titulo' => 'Tipo de cambio', 'ancho' => 14, 'formato' => LibroExcel::DECIMAL],
            ['titulo' => 'Colones', 'ancho' => 16, 'formato' => LibroExcel::MONEDA_DECIMALES],
            ['titulo' => 'Método', 'ancho' => 14],
        ], $filas, columnasFijas: 1);
    }

    private function culto(string $tipo): string
    {
        return match ($tipo) {
            'domingo' => 'Domingo',
            'domingo_pm' => 'Domingo PM',
            'miércoles', 'miercoles' => 'Miércoles',
            'sábado', 'sabado' => 'Sábado',
            'especial' => 'Especial',
            default => ucfirst($tipo),
        };
    }
}

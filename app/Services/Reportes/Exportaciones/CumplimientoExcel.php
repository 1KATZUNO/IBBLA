<?php

namespace App\Services\Reportes\Exportaciones;

use App\Services\Reportes\EstadoCuenta;
use App\Services\Reportes\FilaCumplimiento;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\ReporteCumplimiento;
use App\Support\Excel\LibroExcel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Excel del cumplimiento de promesas con corte, con las hojas del listado hecho a mano del 12/10:
 *  - Resumen: encabezado, tarjetas y una fila por persona con promesa (+ fila de total).
 *  - Mes a mes: lo dado y lo prometido de cada mes; en amarillo lo prometido de un mes sin aporte.
 *  - Sin promesa: quienes aportan sin promesa registrada.
 * Formato de moneda real, encabezados fijos y autofiltro (plantilla LibroExcel).
 */
class CumplimientoExcel
{
    private const COLORES_ESTADO = [
        EstadoCuenta::AL_DIA => LibroExcel::VERDE_SUAVE,
        EstadoCuenta::PENDIENTE => LibroExcel::ROJO_SUAVE,
    ];

    public function generar(ReporteCumplimiento $reporte): LibroExcel
    {
        $filtros = $reporte->filtro->grupo === FiltroPersonas::TODAS ? [] : [$reporte->filtro->descripcion()];
        $libro = new LibroExcel('Cumplimiento de promesas', 'Corte al '.$reporte->corteTexto(), $filtros, generadoEn: $reporte->generadoEn);

        $this->resumen($libro, $reporte);
        $this->mesAMes($libro, $reporte);
        $this->sinPromesa($libro, $reporte);

        return $libro;
    }

    public static function nombreArchivo(ReporteCumplimiento $reporte): string
    {
        return 'cumplimiento-promesas_'.$reporte->periodo->sufijoArchivo().'.xlsx';
    }

    private function resumen(LibroExcel $libro, ReporteCumplimiento $reporte): void
    {
        $resumen = $reporte->resumen();
        $abreviatura = $reporte->periodo->abreviaturaMesesCobrables();
        $conPromesa = $reporte->conPromesa();

        $filas = [];
        foreach ($conPromesa as $i => $fila) {
            $filas[] = [
                $i + 1,
                $fila->nombre,
                $fila->leCorresponde(),
                $fila->haDado(),
                $fila->diferencia(),
                $fila->estado,
                $fila->ultimoAporte,
                $fila->observacion,
                $fila->promesa,
            ];
        }
        [$correspondia, $dado, $diferencia] = ReporteCumplimiento::totales($conPromesa);

        $libro->hoja('Resumen')
            ->encabezado($reporte->explicacion(), columnas: 9)
            ->tarjetas([
                ['etiqueta' => 'Personas con promesa', 'valor' => $resumen['con_promesa']],
                ['etiqueta' => 'Al día', 'valor' => $resumen['al_dia']],
                ['etiqueta' => 'Pendientes', 'valor' => $resumen['pendientes']],
                ['etiqueta' => 'Total que correspondía ('.$abreviatura.')', 'valor' => $resumen['le_correspondia'], 'formato' => LibroExcel::MONEDA],
                ['etiqueta' => 'Total dado (al corte)', 'valor' => $resumen['ha_dado'], 'formato' => LibroExcel::MONEDA],
                ['etiqueta' => 'Faltante de los pendientes', 'valor' => $resumen['faltante'], 'formato' => LibroExcel::MONEDA],
            ])
            ->tabla([
                ['titulo' => '#', 'ancho' => 5, 'alinear' => 'centro'],
                ['titulo' => 'Nombre', 'ancho' => 34],
                ['titulo' => 'Le correspondía ('.$abreviatura.')', 'ancho' => 20, 'formato' => LibroExcel::MONEDA],
                ['titulo' => 'Ha dado (al corte)', 'ancho' => 18, 'formato' => LibroExcel::MONEDA],
                ['titulo' => 'Diferencia', 'ancho' => 16, 'formato' => LibroExcel::MONEDA],
                ['titulo' => 'Estado', 'ancho' => 14, 'colores' => self::COLORES_ESTADO],
                ['titulo' => 'Último aporte', 'ancho' => 14, 'formato' => LibroExcel::FECHA, 'alinear' => 'centro'],
                ['titulo' => 'Observación', 'ancho' => 44],
                ['titulo' => 'Promesa', 'ancho' => 60],
            ], $filas, ['', 'TOTAL', $correspondia, $dado, $diferencia, '', '', '', '']);
    }

    private function mesAMes(LibroExcel $libro, ReporteCumplimiento $reporte): void
    {
        $meses = $reporte->periodo->meses();
        $varios = $reporte->periodo->abarcaVariosAnios();

        $columnas = [
            ['titulo' => 'Nombre', 'ancho' => 34],
            ['titulo' => 'Estado', 'ancho' => 14, 'colores' => self::COLORES_ESTADO],
        ];
        foreach ($meses as $mes) {
            $columnas[] = ['titulo' => $mes->etiqueta($varios).' dado', 'ancho' => 13, 'formato' => LibroExcel::MONEDA];
        }
        $n = count($meses);
        foreach ($meses as $m => $mes) {
            // Le correspondia algo ese mes y no dio nada: el prometido se resalta en amarillo.
            $prometido = Coordinate::stringFromColumnIndex(3 + $n + $m);
            $dado = Coordinate::stringFromColumnIndex(3 + $m);
            $columnas[] = [
                'titulo' => $mes->etiqueta($varios).' prometido',
                'ancho' => 13,
                'formato' => LibroExcel::MONEDA,
                'resaltar' => ['formula' => "AND({$prometido}{fila}>0,{$dado}{fila}=0)", 'color' => LibroExcel::AMARILLO],
            ];
        }

        $filas = [];
        foreach ($reporte->filas as $fila) {
            $valores = [$fila->nombre, $fila->estado];
            foreach ($meses as $mes) {
                $valores[] = $fila->dadoMes($mes->clave);
            }
            foreach ($meses as $mes) {
                $valores[] = $fila->esperadoMes($mes->clave);
            }
            $filas[] = $valores;
        }

        $libro->hoja('Mes a mes')->tabla($columnas, $filas, columnasFijas: 2);
    }

    private function sinPromesa(LibroExcel $libro, ReporteCumplimiento $reporte): void
    {
        $filas = array_map(fn (FilaCumplimiento $f) => [$f->nombre, $f->haDado(), $f->ultimoAporte], $reporte->sinPromesa());

        $libro->hoja('Sin promesa')->tabla([
            ['titulo' => 'Nombre', 'ancho' => 34],
            ['titulo' => 'Ha dado (al corte)', 'ancho' => 18, 'formato' => LibroExcel::MONEDA],
            ['titulo' => 'Último aporte', 'ancho' => 14, 'formato' => LibroExcel::FECHA, 'alinear' => 'centro'],
        ], $filas);
    }
}

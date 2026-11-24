<?php

namespace App\Exports;

use App\Services\ReporteAsistenciaClasesService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Excel y CSV del reporte de asistencia por clase, con totales y promedios ya calculados.
 *
 * Columnas: Clase | Fecha | Culto | Niños | Niñas | Maestros | Total. Cada clase termina con su fila de
 * TOTAL y su fila de PROMEDIO; si hay más de una clase, al final van el TOTAL GENERAL y el PROMEDIO GENERAL.
 * Los valores salen de ReporteAsistenciaClasesService: son los mismos de la pantalla y el PDF.
 *
 * El CSV está pensado para abrirse en Excel en español: separador «;», UTF-8 con BOM y promedios con coma
 * decimal ("16,67"). Con «,» como separador, Excel en español pega todo en una sola columna.
 * El Excel (.xlsx) lleva un encabezado con el rango y la explicación del promedio; el CSV empieza directo
 * en los títulos de columna para que se pueda importar a otras herramientas.
 */
class AsistenciaPorClaseExport implements FromArray, WithColumnWidths, WithCustomCsvSettings, WithEvents, WithStrictNullComparison, WithTitle
{
    private const ENCABEZADOS = ['Clase', 'Fecha', 'Culto', 'Niños', 'Niñas', 'Maestros', 'Total'];

    /** @var list<list<mixed>> */
    private array $filas = [];

    private int $filaEncabezado = 1;

    /** @var list<int> */
    private array $filasTotal = [];

    /** @var list<int> */
    private array $filasPromedio = [];

    /** @var list<int> */
    private array $filasTitulo = [];

    public function __construct(private readonly array $reporte, private readonly bool $paraCsv = false)
    {
        $this->construir();
    }

    public function array(): array
    {
        return $this->filas;
    }

    public function title(): string
    {
        return 'Asistencia por clase';
    }

    public function columnWidths(): array
    {
        return ['A' => 28, 'B' => 13, 'C' => 24, 'D' => 11, 'E' => 11, 'F' => 11, 'G' => 11];
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'use_bom' => true,
            'output_encoding' => 'UTF-8',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $evento) {
                $hoja = $evento->sheet->getDelegate();
                $ultima = count($this->filas);

                // Títulos del Excel: una línea combinada por fila, en negrita la primera
                foreach ($this->filasTitulo as $fila) {
                    $hoja->mergeCells("A{$fila}:G{$fila}");
                    $hoja->getStyle("A{$fila}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                }
                if ($this->filasTitulo !== []) {
                    $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                    $hoja->getRowDimension(4)->setRowHeight(64);
                }

                $encabezado = $this->filaEncabezado;
                $hoja->getStyle("A{$encabezado}:G{$encabezado}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF3B82F6']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                if ($ultima >= $encabezado) {
                    $hoja->getStyle("A{$encabezado}:G{$ultima}")->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');
                    $hoja->getStyle('D'.($encabezado + 1).":G{$ultima}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $hoja->getStyle('B'.($encabezado + 1).":B{$ultima}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                foreach ($this->filasTotal as $fila) {
                    $hoja->getStyle("A{$fila}:G{$fila}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
                    ]);
                }

                foreach ($this->filasPromedio as $fila) {
                    $hoja->getStyle("A{$fila}:G{$fila}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEF3C7']],
                    ]);
                    // Los promedios son números reales; el formato fija los dos decimales
                    $hoja->getStyle("D{$fila}:G{$fila}")->getNumberFormat()->setFormatCode('0.00');
                }

                $hoja->freezePane('A'.($encabezado + 1));
            },
        ];
    }

    private function construir(): void
    {
        if (! $this->paraCsv) {
            $this->construirTitulo();
        }

        $this->filas[] = self::ENCABEZADOS;
        $this->filaEncabezado = count($this->filas);

        if ($this->reporte['clases'] === []) {
            $this->filas[] = ['Sin asistencia por clase registrada en este rango.'];
        }

        foreach ($this->reporte['clases'] as $clase) {
            foreach ($clase['filas'] as $fila) {
                $this->filas[] = [
                    $clase['nombre'],
                    $fila['fecha']->format('d/m/Y'),
                    $fila['cultos'],
                    $fila['ninos'],
                    $fila['ninas'],
                    $fila['maestros'],
                    $fila['total'],
                ];
            }

            $this->agregarResumen($clase['nombre'], $clase['fechas'], $clase['totales'], $clase['promedios']);
        }

        if (count($this->reporte['clases']) > 1) {
            $this->agregarResumen('TODAS LAS CLASES', $this->reporte['general']['fechas'], $this->reporte['general']['totales'], $this->reporte['general']['promedios'], general: true);
        }
    }

    private function construirTitulo(): void
    {
        $iglesia = tenant()?->nombre ?? 'Sistema de Administración';
        $desde = $this->reporte['desde']->format('d/m/Y');
        $hasta = $this->reporte['hasta']->format('d/m/Y');
        $clase = $this->reporte['clase_nombre'] ?? 'Todas las clases';

        $this->filas[] = ["Asistencia por clase - {$iglesia}"];
        $this->filas[] = ["Rango: del {$desde} al {$hasta}  |  Clase: {$clase}  |  Generado: ".now()->format('d/m/Y H:i')];
        $this->filas[] = [''];
        $this->filas[] = [
            'Cómo se calcula el promedio: suma de la clase en el rango ÷ fechas en que la clase tiene registro. '
            .'Las fechas sin registro no cuentan, así que no bajan el promedio. '
            .'El promedio general es la suma de todas las clases ÷ fechas con al menos una clase registrada.'
            .($this->reporte['incluir_ceros'] ? ' Se cuentan también las fechas con 0 niños, 0 niñas y 0 maestros.' : ' Las fechas con 0 niños, 0 niñas y 0 maestros se tratan como sin registro.'),
        ];
        $this->filas[] = [''];

        $this->filasTitulo = [1, 2, 4];
    }

    /**
     * @param  array{ninos: int, ninas: int, maestros: int, total: int}  $totales
     * @param  array{ninos: float, ninas: float, maestros: float, total: float}  $promedios
     */
    private function agregarResumen(string $nombre, int $fechas, array $totales, array $promedios, bool $general = false): void
    {
        $this->filas[] = [
            $nombre,
            ($general ? 'TOTAL GENERAL' : 'TOTAL')." ({$fechas} ".($fechas === 1 ? 'fecha' : 'fechas').')',
            '',
            $totales['ninos'],
            $totales['ninas'],
            $totales['maestros'],
            $totales['total'],
        ];
        $this->filasTotal[] = count($this->filas);

        $this->filas[] = [
            $nombre,
            $general ? 'PROMEDIO GENERAL' : 'PROMEDIO',
            'por fecha con registro',
            $this->valorPromedio($promedios['ninos']),
            $this->valorPromedio($promedios['ninas']),
            $this->valorPromedio($promedios['maestros']),
            $this->valorPromedio($promedios['total']),
        ];
        $this->filasPromedio[] = count($this->filas);
    }

    /**
     * En CSV el promedio va como texto con coma decimal (así lo lee Excel en español);
     * en Excel va como número y el formato de celda pone los decimales.
     */
    private function valorPromedio(float $promedio): float|string
    {
        return $this->paraCsv ? ReporteAsistenciaClasesService::formatearPromedio($promedio) : $promedio;
    }
}

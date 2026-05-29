<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Excel de ingresos (sobres + ofrenda suelta) por culto/semana/mes.
 * Las columnas vienen dinámicamente de tenant_categories(), respetando el
 * mismo orden que el PDF de ingresos.
 */
class IngresosExport implements FromArray, WithHeadings, WithTitle, WithEvents, ShouldAutoSize
{
    public function __construct(
        protected array $registros,
        protected Collection $categories,
        protected string $tipoReporte = 'culto',
        protected ?string $tituloHoja = null,
    ) {}

    public function headings(): array
    {
        $base = ['Fecha', 'Tipo'];
        foreach ($this->categories as $cat) {
            $base[] = $cat->nombre;
        }
        $base[] = 'Suelto';
        $base[] = 'Total';

        return $base;
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->registros as $reg) {
            $row = [$reg['fecha'] ?? '', $reg['tipo'] ?? ''];
            foreach ($this->categories as $cat) {
                $row[] = $reg[$cat->slug] ?? 0;
            }
            $row[] = $reg['suelto'] ?? 0;
            $row[] = $reg['total'] ?? 0;
            $rows[] = $row;
        }

        return $rows;
    }

    public function title(): string
    {
        return $this->tituloHoja ?? 'Ingresos '.ucfirst($this->tipoReporte);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestCol = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                // Header
                $sheet->getStyle("A1:{$highestCol}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF10B981');
                $sheet->getStyle("A1:{$highestCol}1")->getFont()
                    ->setBold(true)
                    ->getColor()->setARGB('FFFFFFFF');

                // Formato moneda en columnas numéricas (de columna C en adelante)
                $colMonedaStart = 3; // A=fecha, B=tipo, C=primera categoría
                $colMonedaEnd = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
                for ($i = $colMonedaStart; $i <= $colMonedaEnd; $i++) {
                    $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getStyle("{$letra}2:{$letra}{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('"₡"#,##0.00');
                }

                // Bordes
                $sheet->getStyle("A1:{$highestCol}{$highestRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFD1D5DB');

                // Totales
                if ($highestRow > 1) {
                    $totalRow = $highestRow + 1;
                    $sheet->setCellValue("A{$totalRow}", 'TOTALES');
                    $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
                    for ($i = $colMonedaStart; $i <= $colMonedaEnd; $i++) {
                        $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                        $sheet->setCellValue("{$letra}{$totalRow}", "=SUM({$letra}2:{$letra}{$highestRow})");
                        $sheet->getStyle("{$letra}{$totalRow}")
                            ->getNumberFormat()
                            ->setFormatCode('"₡"#,##0.00');
                    }
                    $sheet->getStyle("A{$totalRow}:{$highestCol}{$totalRow}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$totalRow}:{$highestCol}{$totalRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFD1FAE5');
                }

                $sheet->freezePane('C2');
            },
        ];
    }
}

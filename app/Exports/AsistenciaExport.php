<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel de asistencias para un mes o rango.
 * Replica las columnas del PDF mensual (asistencia-mes.blade.php).
 * Roy usa esto para hacer gráficos propios y comparativos entre meses.
 */
class AsistenciaExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithEvents, ShouldAutoSize
{
    public function __construct(
        protected Collection $cultos,
        protected Collection $registroExtraTipos,
        protected ?string $tituloHoja = null,
    ) {}

    public function collection(): Collection
    {
        return $this->cultos->filter(fn ($c) => $c->asistencia)->values();
    }

    public function headings(): array
    {
        $base = ['Fecha', 'Tipo Culto', 'Total', 'Hombres', 'Mujeres', 'Niños', 'Capilla', 'Visitas', 'Salvos', 'Bautismos'];
        foreach ($this->registroExtraTipos as $tipo) {
            foreach ($tipo->subcampos as $subcampo) {
                $base[] = count($tipo->subcampos) === 1 ? $tipo->nombre : ucfirst($subcampo);
            }
        }
        $base[] = 'Retroactivo';

        return $base;
    }

    public function map($culto): array
    {
        $a = $culto->asistencia;
        $row = [
            $culto->fecha->format('Y-m-d'),
            ucfirst($culto->tipo_culto),
            $a->total_asistencia,
            $a->getTotalHombres(),
            $a->getTotalMujeres(),
            $a->getTotalNinos(),
            $a->getTotalCapilla(),
            $a->getTotalVisitas(),
            $a->getTotalSalvos(),
            $a->getTotalBautismos(),
        ];

        foreach ($this->registroExtraTipos as $tipo) {
            $registro = $a->registrosExtra->firstWhere('registro_extra_tipo_id', $tipo->id);
            foreach ($tipo->subcampos as $subcampo) {
                $row[] = $registro ? ($registro->valores[$subcampo] ?? 0) : 0;
            }
        }

        $row[] = $a->cargado_retroactivo ? 'Sí' : '';

        return $row;
    }

    public function title(): string
    {
        return $this->tituloHoja ?? 'Asistencia';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestCol = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                // Header style
                $sheet->getStyle("A1:{$highestCol}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF3B82F6');
                $sheet->getStyle("A1:{$highestCol}1")->getFont()
                    ->setBold(true)
                    ->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle("A1:{$highestCol}1")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Borders en toda la tabla
                $sheet->getStyle("A1:{$highestCol}{$highestRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFD1D5DB');

                // Center alignment para columnas numéricas
                $sheet->getStyle("C2:{$highestCol}{$highestRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Fila de totales al final
                if ($highestRow > 1) {
                    $totalRow = $highestRow + 1;
                    $sheet->setCellValue("A{$totalRow}", 'TOTALES');
                    $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
                    // Sumar las columnas numéricas (C en adelante hasta antes de "Retroactivo")
                    $colIdx = 3;
                    $colEnd = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol) - 1;
                    while ($colIdx <= $colEnd) {
                        $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                        $sheet->setCellValue("{$letra}{$totalRow}", "=SUM({$letra}2:{$letra}{$highestRow})");
                        $colIdx++;
                    }
                    $sheet->getStyle("A{$totalRow}:{$highestCol}{$totalRow}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$totalRow}:{$highestCol}{$totalRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFDBEAFE');
                }

                // Freeze pane
                $sheet->freezePane('C2');
            },
        ];
    }
}

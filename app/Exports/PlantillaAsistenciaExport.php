<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Plantilla Excel descargable para que el cliente la rellene con asistencias
 * históricas (enero/febrero 2026). La estructura cubre todos los campos del
 * modelo Asistencia: capilla, salvos/bautismos/visitas por demografía,
 * transmisión, vehículos. Las columnas vacías se interpretan como 0 en el
 * import.
 *
 * El layout incluye:
 *  - Fila 1: instrucciones de uso.
 *  - Fila 2: encabezados con colores por categoría.
 *  - Fila 3: ejemplo de datos.
 *  - Fila 4+: filas en blanco para que el usuario rellene.
 */
class PlantillaAsistenciaExport implements FromArray, WithHeadings, WithTitle, WithEvents, ShouldAutoSize
{
    public const COLUMNAS = [
        // Identificación del culto
        'fecha' => ['label' => 'Fecha (YYYY-MM-DD)', 'grupo' => 'culto', 'ejemplo' => '2026-01-04'],
        'tipo_culto' => ['label' => 'Tipo Culto', 'grupo' => 'culto', 'ejemplo' => 'domingo'],

        // Capilla (solo adultos según pedido de Roy)
        'chapel_adultos_hombres' => ['label' => 'Capilla Adultos H', 'grupo' => 'capilla', 'ejemplo' => 25],
        'chapel_adultos_mujeres' => ['label' => 'Capilla Adultos M', 'grupo' => 'capilla', 'ejemplo' => 32],

        // Niños - se cargan agregados en una clase virtual
        'ninos_total' => ['label' => 'Niños Total', 'grupo' => 'ninos', 'ejemplo' => 18],

        // Salvos (totales por demografía)
        'salvos_adulto_hombre' => ['label' => 'Salvos Adulto H', 'grupo' => 'salvos', 'ejemplo' => 0],
        'salvos_adulto_mujer' => ['label' => 'Salvos Adulto M', 'grupo' => 'salvos', 'ejemplo' => 0],
        'salvos_joven_hombre' => ['label' => 'Salvos Joven H', 'grupo' => 'salvos', 'ejemplo' => 0],
        'salvos_joven_mujer' => ['label' => 'Salvos Joven M', 'grupo' => 'salvos', 'ejemplo' => 1],
        'salvos_nino' => ['label' => 'Salvos Niño', 'grupo' => 'salvos', 'ejemplo' => 0],
        'salvos_nina' => ['label' => 'Salvos Niña', 'grupo' => 'salvos', 'ejemplo' => 0],

        // Bautismos
        'bautismos_adulto_hombre' => ['label' => 'Bautismos Adulto H', 'grupo' => 'bautismos', 'ejemplo' => 0],
        'bautismos_adulto_mujer' => ['label' => 'Bautismos Adulto M', 'grupo' => 'bautismos', 'ejemplo' => 0],
        'bautismos_joven_hombre' => ['label' => 'Bautismos Joven H', 'grupo' => 'bautismos', 'ejemplo' => 0],
        'bautismos_joven_mujer' => ['label' => 'Bautismos Joven M', 'grupo' => 'bautismos', 'ejemplo' => 0],
        'bautismos_nino' => ['label' => 'Bautismos Niño', 'grupo' => 'bautismos', 'ejemplo' => 0],
        'bautismos_nina' => ['label' => 'Bautismos Niña', 'grupo' => 'bautismos', 'ejemplo' => 0],

        // Visitas
        'visitas_adulto_hombre' => ['label' => 'Visitas Adulto H', 'grupo' => 'visitas', 'ejemplo' => 2],
        'visitas_adulto_mujer' => ['label' => 'Visitas Adulto M', 'grupo' => 'visitas', 'ejemplo' => 1],
        'visitas_joven_hombre' => ['label' => 'Visitas Joven H', 'grupo' => 'visitas', 'ejemplo' => 0],
        'visitas_joven_mujer' => ['label' => 'Visitas Joven M', 'grupo' => 'visitas', 'ejemplo' => 0],
        'visitas_nino' => ['label' => 'Visitas Niño', 'grupo' => 'visitas', 'ejemplo' => 0],
        'visitas_nina' => ['label' => 'Visitas Niña', 'grupo' => 'visitas', 'ejemplo' => 0],

        // Registros extra
        'transmision' => ['label' => 'Transmisión (online)', 'grupo' => 'extra', 'ejemplo' => 15],
        'vehiculos_autos' => ['label' => 'Vehículos: Autos', 'grupo' => 'extra', 'ejemplo' => 12],
        'vehiculos_motos' => ['label' => 'Vehículos: Motos', 'grupo' => 'extra', 'ejemplo' => 3],
    ];

    public const COLORES_GRUPO = [
        'culto' => 'FFE5E7EB',     // gris claro
        'capilla' => 'FFDBEAFE',   // azul claro
        'ninos' => 'FFD1FAE5',     // verde claro
        'salvos' => 'FFFEF3C7',    // amarillo claro
        'bautismos' => 'FFE0E7FF', // indigo claro
        'visitas' => 'FFFCE7F3',   // rosa claro
        'extra' => 'FFFFE4E6',     // rojo claro
    ];

    public function headings(): array
    {
        return array_map(fn ($col) => $col['label'], self::COLUMNAS);
    }

    public function array(): array
    {
        // Fila de ejemplo + 20 filas vacías para que el usuario rellene
        $ejemplo = array_map(fn ($col) => $col['ejemplo'], self::COLUMNAS);
        $vacias = array_fill(0, 20, array_fill(0, count(self::COLUMNAS), null));

        return array_merge([$ejemplo], $vacias);
    }

    public function title(): string
    {
        return 'Asistencias';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultimaCol = $sheet->getHighestColumn();

                // Header bold con fondo por grupo
                $headerRange = "A1:{$ultimaCol}1";
                $sheet->getStyle($headerRange)->getFont()->setBold(true);
                $sheet->getStyle($headerRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setWrapText(true);
                $sheet->getRowDimension(1)->setRowHeight(30);

                // Pintar headers por grupo
                $colIdx = 1;
                foreach (self::COLUMNAS as $key => $col) {
                    $color = self::COLORES_GRUPO[$col['grupo']];
                    $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->getStyle("{$letra}1")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($color);
                    $colIdx++;
                }

                // Fila de ejemplo en italic + gris
                $ejemploRange = "A2:{$ultimaCol}2";
                $sheet->getStyle($ejemploRange)->getFont()->setItalic(true)->getColor()->setARGB('FF6B7280');
                $sheet->getStyle("A2")->getComment()->getText()->createTextRun('Fila de ejemplo, bórrela antes de subir el archivo');

                // Bordes en todo
                $totalRows = $sheet->getHighestRow();
                $sheet->getStyle("A1:{$ultimaCol}{$totalRows}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFD1D5DB');

                // Freeze pane (header siempre visible)
                $sheet->freezePane('C2');
            },
        ];
    }
}

<?php

namespace App\Exports;

use App\Models\Compromiso;
use App\Models\Persona;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Excel de promesas con cumplimiento (prometido vs dado, saldo) por persona.
 * Si se da un año, filtra los compromisos a ese año. Si no, agrega todos.
 */
class PromesasExport implements FromArray, WithHeadings, WithTitle, WithEvents, ShouldAutoSize
{
    public function __construct(
        protected ?int $año = null,
        protected ?int $mes = null,
    ) {}

    public function headings(): array
    {
        $cabecera = ['Persona', 'Categoría', 'Promesa Mensual', 'Moneda'];
        if ($this->año) {
            $cabecera[] = "Prometido {$this->año}".($this->mes ? " (mes {$this->mes})" : '');
            $cabecera[] = "Dado {$this->año}".($this->mes ? " (mes {$this->mes})" : '');
            $cabecera[] = 'Saldo';
            $cabecera[] = '% Cumplimiento';
        }

        return $cabecera;
    }

    public function array(): array
    {
        $personas = Persona::with(['promesas', 'user'])
            ->whereHas('promesas')
            ->orderBy('nombre')
            ->get();

        $rows = [];
        foreach ($personas as $persona) {
            foreach ($persona->promesas as $promesa) {
                $row = [
                    $persona->nombre,
                    ucfirst($promesa->categoria ?? ''),
                    (float) $promesa->monto,
                    $promesa->moneda ?? 'CRC',
                ];

                if ($this->año) {
                    $q = Compromiso::where('persona_id', $persona->id)
                        ->where('categoria', $promesa->categoria)
                        ->where('año', $this->año);
                    if ($this->mes) {
                        $q->where('mes', $this->mes);
                    }
                    $comps = $q->get();
                    $prometido = (float) $comps->sum('monto_prometido');
                    $dado = (float) $comps->sum('monto_dado');
                    $saldo = $dado - $prometido;
                    $pct = $prometido > 0 ? round(($dado / $prometido) * 100, 1) : 0;
                    $row[] = $prometido;
                    $row[] = $dado;
                    $row[] = $saldo;
                    $row[] = $pct;
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Promesas'.($this->año ? " {$this->año}" : '');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestCol = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                $sheet->getStyle("A1:{$highestCol}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF8B5CF6');
                $sheet->getStyle("A1:{$highestCol}1")->getFont()
                    ->setBold(true)
                    ->getColor()->setARGB('FFFFFFFF');

                $sheet->getStyle("A1:{$highestCol}{$highestRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFE5E7EB');

                $sheet->freezePane('B2');
            },
        ];
    }
}

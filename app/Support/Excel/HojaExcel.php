<?php

namespace App\Support\Excel;

use Carbon\CarbonInterface;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Una hoja de LibroExcel. Se escribe de arriba hacia abajo: encabezado (opcional), tarjetas de
 * resumen (opcional) y una o mas tablas.
 *
 * Los textos se guardan siempre como texto (un nombre que empiece con "=" no se vuelve formula) y
 * las fechas como fechas de Excel, para que se puedan ordenar y filtrar. Los colores por valor
 * (estado AL DÍA / PENDIENTE) y los resaltados van como formato condicional: una regla por columna
 * en vez de un estilo por celda (con estilos por celda un Excel de 150 personas tardaba segundos), y
 * ademas siguen funcionando si alguien corrige un valor en el Excel.
 */
class HojaExcel
{
    /** Proxima fila libre. */
    private int $fila = 1;

    /** @var array{encabezado: int, primera: int, ultima: int}|null filas de la ultima tabla escrita */
    private ?array $ultimaTabla = null;

    public function __construct(private readonly Worksheet $hoja, private readonly LibroExcel $libro) {}

    public function delegado(): Worksheet
    {
        return $this->hoja;
    }

    public function filaActual(): int
    {
        return $this->fila;
    }

    /** @return array{encabezado: int, primera: int, ultima: int}|null */
    public function ultimaTabla(): ?array
    {
        return $this->ultimaTabla;
    }

    /**
     * Iglesia, titulo, linea con periodo/filtros/fecha de generacion y una nota opcional que ocupa
     * el ancho de la tabla ($columnas).
     */
    public function encabezado(?string $nota = null, int $columnas = 8): self
    {
        $ultima = Coordinate::stringFromColumnIndex(max(1, $columnas));

        $this->texto($this->libro->iglesia, negrita: true, tam: 14);
        $this->texto($this->libro->titulo.($this->libro->periodo !== '' ? ' - '.$this->libro->periodo : ''), negrita: true, tam: 12);
        $this->texto($this->libro->lineaDetalle(), color: '4B5563');

        if ($nota !== null && $nota !== '') {
            $celda = 'A'.$this->fila;
            $this->hoja->setCellValueExplicit($celda, $nota, DataType::TYPE_STRING);
            $this->hoja->mergeCells("A{$this->fila}:{$ultima}{$this->fila}");
            $this->hoja->getStyle($celda)->applyFromArray([
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
                'font' => ['size' => 10, 'color' => ['rgb' => '374151']],
            ]);
            // Alto aproximado: ~14 caracteres por columna de ancho normal.
            $lineas = max(1, (int) ceil(mb_strlen($nota) / max(40, $columnas * 14)));
            $this->hoja->getRowDimension($this->fila)->setRowHeight(15 * $lineas + 4);
            $this->fila++;
        }

        $this->fila++;

        return $this;
    }

    /** Una linea de texto en la columna A. */
    public function texto(string $texto, bool $negrita = false, float $tam = 11, ?string $color = null): self
    {
        $celda = 'A'.$this->fila;
        $this->hoja->setCellValueExplicit($celda, $texto, DataType::TYPE_STRING);
        $this->hoja->getStyle($celda)->applyFromArray([
            'font' => ['bold' => $negrita, 'size' => $tam] + ($color !== null ? ['color' => ['rgb' => $color]] : []),
        ]);
        $this->fila++;

        return $this;
    }

    public function filaVacia(int $cuantas = 1): self
    {
        $this->fila += $cuantas;

        return $this;
    }

    /**
     * Tarjetas de resumen: una fila con las etiquetas (gris, negrita) y otra con los valores.
     *
     * @param  list<array{etiqueta: string, valor: mixed, formato?: string}>  $tarjetas
     */
    public function tarjetas(array $tarjetas): self
    {
        $etiquetas = $this->fila;
        $valores = $this->fila + 1;

        foreach ($tarjetas as $i => $tarjeta) {
            $columna = Coordinate::stringFromColumnIndex($i + 1);
            $this->hoja->setCellValueExplicit($columna.$etiquetas, $tarjeta['etiqueta'], DataType::TYPE_STRING);
            $this->escribir($columna.$valores, $tarjeta['valor']);
            if (isset($tarjeta['formato'])) {
                $this->hoja->getStyle($columna.$valores)->getNumberFormat()->setFormatCode($tarjeta['formato']);
            }
        }

        $ultima = Coordinate::stringFromColumnIndex(max(1, count($tarjetas)));
        $this->hoja->getStyle("A{$etiquetas}:{$ultima}{$etiquetas}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => LibroExcel::GRIS]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $this->hoja->getStyle("A{$valores}:{$ultima}{$valores}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $this->hoja->getStyle("A{$etiquetas}:{$ultima}{$valores}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => LibroExcel::BORDE]]],
        ]);
        $this->hoja->getRowDimension($etiquetas)->setRowHeight(30);

        $this->fila += 3;

        return $this;
    }

    /**
     * Tabla con fila de titulos (fija al desplazarse y con autofiltro), formato por columna y fila
     * de total opcional.
     *
     * Columna: ['titulo' => ..., 'ancho' => 20, 'formato' => LibroExcel::MONEDA, 'alinear' => 'centro',
     *           'ajustar' => true (texto en varias lineas),
     *           'colores' => ['AL DÍA' => LibroExcel::VERDE_SUAVE] (relleno y negrita segun el valor),
     *           'resaltar' => ['formula' => 'AND(M{fila}>0,C{fila}=0)', 'color' => LibroExcel::AMARILLO]]
     * En 'resaltar', {fila} es la fila de cada celda.
     *
     * @param  list<array<string, mixed>>  $columnas
     * @param  iterable<list<mixed>>  $filas
     * @param  list<mixed>|null  $total
     * @param  int  $columnasFijas  columnas que quedan fijas al desplazarse a la derecha (0 = ninguna)
     */
    public function tabla(array $columnas, iterable $filas, ?array $total = null, int $columnasFijas = 0, bool $autofiltro = true): self
    {
        $n = count($columnas);
        $ultima = Coordinate::stringFromColumnIndex($n);
        $encabezado = $this->fila;
        $letras = [];

        foreach ($columnas as $i => $columna) {
            $letras[$i] = Coordinate::stringFromColumnIndex($i + 1);
            $this->hoja->setCellValueExplicit($letras[$i].$encabezado, $columna['titulo'], DataType::TYPE_STRING);
            if (isset($columna['ancho'])) {
                $this->hoja->getColumnDimension($letras[$i])->setWidth($columna['ancho']);
            }
        }
        $this->hoja->getStyle("A{$encabezado}:{$ultima}{$encabezado}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => LibroExcel::AZUL_OSCURO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $this->hoja->getRowDimension($encabezado)->setRowHeight(30);

        $fila = $encabezado;
        $conFechas = [];
        foreach ($filas as $valores) {
            $fila++;
            foreach (array_values($valores) as $i => $valor) {
                if (is_array($valor)) {
                    // Celda con relleno propio: estilo por celda (lento en volumen; para casos sueltos).
                    $this->escribir($letras[$i].$fila, $valor['valor'] ?? null);
                    if (isset($valor['fondo'])) {
                        $this->hoja->getStyle($letras[$i].$fila)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $valor['fondo']]],
                        ]);
                    }

                    continue;
                }
                if ($valor instanceof DateTimeInterface) {
                    $conFechas[$i] = true;
                }
                $this->escribir($letras[$i].$fila, $valor, conFormatoFecha: false);
            }
        }
        $ultimaDatos = $fila;

        if ($total !== null) {
            $fila++;
            foreach (array_values($total) as $i => $valor) {
                $this->escribir($letras[$i].$fila, $valor);
            }
            $this->hoja->getStyle("A{$fila}:{$ultima}{$fila}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => LibroExcel::GRIS]],
            ]);
        }

        // Formato, alineacion y colores por columna, en una sola llamada por columna.
        if ($fila > $encabezado) {
            foreach ($columnas as $i => $columna) {
                $rango = $letras[$i].($encabezado + 1).':'.$letras[$i].$fila;
                $formato = $columna['formato'] ?? (isset($conFechas[$i]) ? LibroExcel::FECHA : null);
                $alinear = $columna['alinear'] ?? (isset($columna['colores']) ? 'centro' : null);

                $estilo = [];
                if ($formato !== null) {
                    $estilo['numberFormat'] = ['formatCode' => $formato];
                }
                if ($alinear !== null || ! empty($columna['ajustar'])) {
                    $estilo['alignment'] = array_filter([
                        'horizontal' => match ($alinear) {
                            'centro' => Alignment::HORIZONTAL_CENTER,
                            'derecha' => Alignment::HORIZONTAL_RIGHT,
                            'izquierda' => Alignment::HORIZONTAL_LEFT,
                            default => null,
                        },
                        'wrapText' => ! empty($columna['ajustar']) ? true : null,
                    ], fn ($v) => $v !== null);
                }
                if ($estilo !== []) {
                    $this->hoja->getStyle($rango)->applyFromArray($estilo);
                }

                if ($ultimaDatos > $encabezado) {
                    $datos = $letras[$i].($encabezado + 1).':'.$letras[$i].$ultimaDatos;
                    $this->formatoCondicional($datos, $columna, $encabezado + 1);
                }
            }
        }

        // Una sola pasada por las celdas (con getBorders()->...->setRGB() serian dos).
        $this->hoja->getStyle("A{$encabezado}:{$ultima}{$fila}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => LibroExcel::BORDE]]],
        ]);

        if ($autofiltro) {
            $this->hoja->setAutoFilter("A{$encabezado}:{$ultima}".max($encabezado, $ultimaDatos));
        }
        $this->hoja->freezePane(Coordinate::stringFromColumnIndex($columnasFijas + 1).($encabezado + 1));

        $this->ultimaTabla = ['encabezado' => $encabezado, 'primera' => $encabezado + 1, 'ultima' => $ultimaDatos];
        $this->fila = $fila + 2;

        return $this;
    }

    /** @param  array<string, mixed>  $columna */
    private function formatoCondicional(string $rango, array $columna, int $primeraFila): void
    {
        $reglas = [];

        foreach ($columna['colores'] ?? [] as $valor => $color) {
            $regla = new Conditional;
            $regla->setConditionType(Conditional::CONDITION_CELLIS)
                ->setOperatorType(Conditional::OPERATOR_EQUAL)
                ->addCondition('"'.str_replace('"', '""', (string) $valor).'"');
            $this->rellenoCondicional($regla, $color);
            $regla->getStyle()->getFont()->setBold(true);
            $reglas[] = $regla;
        }

        if (isset($columna['resaltar']['formula'])) {
            $regla = new Conditional;
            $regla->setConditionType(Conditional::CONDITION_EXPRESSION)
                ->addCondition(str_replace('{fila}', (string) $primeraFila, $columna['resaltar']['formula']));
            $this->rellenoCondicional($regla, $columna['resaltar']['color'] ?? LibroExcel::AMARILLO);
            $reglas[] = $regla;
        }

        if ($reglas !== []) {
            $this->hoja->setConditionalStyles($rango, $reglas);
        }
    }

    /** En formato condicional Excel toma el color de fondo de endColor y LibreOffice de startColor: van los dos. */
    private function rellenoCondicional(Conditional $regla, string $color): void
    {
        $relleno = $regla->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
        $relleno->getStartColor()->setRGB($color);
        $relleno->getEndColor()->setRGB($color);
    }

    private function escribir(string $celda, mixed $valor, bool $conFormatoFecha = true): void
    {
        if ($valor === null || $valor === '') {
            return;
        }

        if ($valor instanceof DateTimeInterface) {
            $fecha = $valor instanceof CarbonInterface ? $valor->copy()->startOfDay() : $valor;
            $this->hoja->setCellValue($celda, FechaExcel::PHPToExcel($fecha));
            if ($conFormatoFecha) {
                $this->hoja->getStyle($celda)->getNumberFormat()->setFormatCode(LibroExcel::FECHA);
            }

            return;
        }

        if (is_int($valor) || is_float($valor)) {
            $this->hoja->setCellValueExplicit($celda, $valor, DataType::TYPE_NUMERIC);

            return;
        }

        if (is_bool($valor)) {
            $this->hoja->setCellValueExplicit($celda, $valor ? 'Sí' : 'No', DataType::TYPE_STRING);

            return;
        }

        $this->hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
    }
}

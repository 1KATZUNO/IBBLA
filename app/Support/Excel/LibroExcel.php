<?php

namespace App\Support\Excel;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plantilla unica de los Excel de reportes (HU-R14): una hoja de resumen con el encabezado de la
 * iglesia, el titulo, el periodo y los filtros, y hojas de detalle que empiezan directo en la fila
 * de titulos de columna. Todas las tablas con formato de moneda real (₡), encabezados fijos,
 * autofiltro y anchos de columna; colores solo para estados.
 *
 * Uso:
 *
 *     $libro = new LibroExcel('Cumplimiento de promesas', 'Corte al 12/10/2026', ['Personas: con promesa']);
 *     $libro->hoja('Resumen')
 *         ->encabezado('Le correspondía = ...', columnas: 8)
 *         ->tarjetas([['etiqueta' => 'Con promesa', 'valor' => 166], ['etiqueta' => 'Dado', 'valor' => 40552097, 'formato' => LibroExcel::MONEDA]])
 *         ->tabla([
 *             ['titulo' => 'Nombre', 'ancho' => 34],
 *             ['titulo' => 'Ha dado', 'ancho' => 18, 'formato' => LibroExcel::MONEDA],
 *             ['titulo' => 'Estado', 'ancho' => 14, 'colores' => ['AL DÍA' => LibroExcel::VERDE_SUAVE, 'PENDIENTE' => LibroExcel::ROJO_SUAVE]],
 *         ], $filas, total: ['TOTAL', 40552097, '']);
 *     $libro->hoja('Detalle')->tabla(...);
 *     return $libro->descargar('cumplimiento_corte_2026-10-12.xlsx');
 *
 * Se escribe con PhpSpreadsheet directo (no con las "concerns" de maatwebsite/excel) para controlar
 * varias hojas con encabezado propio.
 */
class LibroExcel
{
    public const MONEDA = '"₡"#,##0;[Red]-"₡"#,##0';

    public const MONEDA_DECIMALES = '"₡"#,##0.00;[Red]-"₡"#,##0.00';

    public const DOLARES = '"$"#,##0.00';

    public const ENTERO = '#,##0';

    public const DECIMAL = '#,##0.00';

    public const FECHA = 'dd/mm/yyyy';

    public const TEXTO = '@';

    public const AZUL_OSCURO = '1F3A5F';

    public const GRIS = 'F3F4F6';

    public const BORDE = 'D1D5DB';

    public const ROJO_SUAVE = 'FDE2E1';

    public const VERDE_SUAVE = 'DCFCE7';

    public const AMARILLO = 'FEF3C7';

    public readonly CarbonImmutable $generadoEn;

    public readonly string $iglesia;

    private Spreadsheet $libro;

    private bool $sinHojas = true;

    /**
     * @param  list<string>  $filtros
     */
    public function __construct(
        public readonly string $titulo,
        public readonly string $periodo = '',
        public readonly array $filtros = [],
        ?string $iglesia = null,
        ?CarbonInterface $generadoEn = null,
    ) {
        $tenant = tenant();
        $this->iglesia = $iglesia ?? ($tenant ? $tenant->siglas.' - '.$tenant->nombre : 'Sistema de Administración');
        $this->generadoEn = CarbonImmutable::instance($generadoEn ?? now());

        $this->libro = new Spreadsheet;
        $this->libro->getProperties()
            ->setCreator($this->iglesia)
            ->setTitle($titulo)
            ->setSubject($periodo);
        $this->libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
    }

    /** Hoja nueva (la primera reutiliza la hoja vacia que trae el libro). Excel limita el nombre a 31 caracteres. */
    public function hoja(string $nombre): HojaExcel
    {
        if ($this->sinHojas) {
            $hoja = $this->libro->getActiveSheet();
            $this->sinHojas = false;
        } else {
            $hoja = $this->libro->createSheet();
        }
        $hoja->setTitle(mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $nombre), 0, 31));

        return new HojaExcel($hoja, $this);
    }

    public function libro(): Spreadsheet
    {
        return $this->libro;
    }

    /** El .xlsx en memoria (para tests y para meterlo en un ZIP). */
    public function contenido(): string
    {
        $this->libro->setActiveSheetIndex(0);
        $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
        try {
            (new Xlsx($this->libro))->save($archivo);

            return (string) file_get_contents($archivo);
        } finally {
            @unlink($archivo);
        }
    }

    public function descargar(string $nombreArchivo): Response
    {
        $contenido = $this->contenido();

        return response($contenido, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $nombreArchivo, Str::ascii($nombreArchivo)),
            'Content-Length' => strlen($contenido),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /** "Corte al 12/10/2026  |  Personas: con promesa  |  Generado: 13/10/2026 06:30 PM" */
    public function lineaDetalle(): string
    {
        return implode('  |  ', array_filter(array_merge(
            [$this->periodo],
            $this->filtros,
            ['Generado: '.$this->generadoEn->format('d/m/Y h:i A')],
        )));
    }
}

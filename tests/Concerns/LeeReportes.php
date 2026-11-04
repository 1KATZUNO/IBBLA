<?php

namespace Tests\Concerns;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Lectura de las salidas de los reportes en las pruebas: el texto de un PDF (de DomPDF o dibujado
 * sobre su canvas, los dos los escribe CPDF) y un Excel generado.
 */
trait LeeReportes
{
    /**
     * Texto visible del PDF, una linea por cada texto dibujado: descomprime los streams y decodifica
     * los TJ (CPDF escribe el texto en UTF-16BE con ( ) \ escapados).
     */
    protected function textoDelPdf(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
        $texto = '';
        foreach ($streams[1] as $stream) {
            $contenido = @gzuncompress($stream);
            if ($contenido === false || ! str_contains($contenido, 'TJ')) {
                continue;
            }
            preg_match_all('/\[\((.*?)(?<!\\\\)\)\] TJ/s', $contenido, $partes);
            foreach ($partes[1] as $parte) {
                $crudo = preg_replace_callback('/\\\\([nrtbf()\\\\])/', function ($m) {
                    return ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f"][$m[1]] ?? $m[1];
                }, $parte);
                $texto .= mb_convert_encoding($crudo, 'UTF-8', 'UTF-16BE')."\n";
            }
        }

        return $texto;
    }

    /** Cantidad de paginas de un PDF de CPDF. */
    protected function paginasDelPdf(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    protected function leerExcel(string $contenido): Spreadsheet
    {
        $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($archivo, $contenido);

        try {
            return IOFactory::load($archivo);
        } finally {
            @unlink($archivo);
        }
    }
}

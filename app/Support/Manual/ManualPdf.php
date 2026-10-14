<?php

namespace App\Support\Manual;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

/**
 * PDF del manual (completo o de una pagina). DomPDF con DejaVu Sans, que trae el simbolo de colon.
 *
 * El PDF se guarda en storage/app/manual y se reutiliza mientras no cambien las paginas, sus
 * capturas ni la plantilla: solo la primera persona que lo pide espera que se dibuje. Para dejarlo
 * listo antes (por ejemplo al desplegar): php artisan manual:pdf
 */
class ManualPdf
{
    /**
     * Ruta del PDF, dibujandolo si todavia no existe.
     *
     * @param  list<Pagina>  $paginas
     */
    public static function archivo(array $paginas, bool $completo): string
    {
        $tenant = tenant();
        $prefijo = 'manual-'.($tenant?->id ?? 0).'-'.($completo ? 'completo' : $paginas[0]->slug);
        $ruta = storage_path('app/manual/'.$prefijo.'-'.self::huella($paginas).'.pdf');

        if (is_file($ruta)) {
            return $ruta;
        }

        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }

        $paginasHtml = array_map(fn (Pagina $p) => ['pagina' => $p] + Manual::render($p, true), $paginas);

        $pdf = Pdf::loadView('manual.pdf', array_merge(tenant_pdf_data(), [
            'paginasHtml' => $paginasHtml,
            'completo' => $completo,
            'indice' => Manual::paginas(),
            'generado' => now(),
        ]))->setPaper('a4', 'portrait');

        // Pie de pagina con el numero (se dibuja despues de maquetar, cuando ya se sabe el total)
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $fuente = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $lienzo = $dompdf->getCanvas();
        $nombre = tenant()?->nombre ?? 'Sistema de Administracion';
        $lienzo->page_text(42, 815, 'Manual de usuario - '.$nombre, $fuente, 7.5, [0.55, 0.55, 0.55]);
        $lienzo->page_text(486, 815, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 7.5, [0.55, 0.55, 0.55]);

        file_put_contents($ruta, $pdf->output());

        // Versiones anteriores del mismo PDF
        foreach (glob(dirname($ruta).'/'.$prefijo.'-*.pdf') ?: [] as $vieja) {
            if ($vieja !== $ruta) {
                @unlink($vieja);
            }
        }

        return $ruta;
    }

    /** Cambia cuando cambia el contenido, una captura o la plantilla del PDF. */
    private static function huella(array $paginas): string
    {
        $partes = [];
        foreach ($paginas as $p) {
            $partes[] = $p->archivo.filemtime($p->archivo);
        }

        if (is_dir(Manual::directorioImagenes())) {
            foreach (File::allFiles(Manual::directorioImagenes()) as $imagen) {
                $partes[] = $imagen->getPathname().$imagen->getMTime();
            }
        }

        $partes[] = filemtime(resource_path('views/manual/pdf.blade.php'));
        $partes[] = count(Manual::paginas());

        return substr(md5(implode('|', $partes)), 0, 12);
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\Manual\Manual;
use App\Support\Manual\ManualPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Manual de usuario dentro de la app: indice, una pagina por modulo y descarga en PDF.
 * El contenido esta en resources/views/manual/paginas/*.md (ver App\Support\Manual\Manual).
 */
class ManualController extends Controller
{
    public function index(Request $request)
    {
        return view('manual.index', [
            'paginas' => Manual::paginas(),
            'usuario' => $request->user(),
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $pagina = Manual::pagina($slug);
        abort_if($pagina === null, 404);

        $paginas = Manual::paginas();
        $posicion = array_search($pagina, $paginas, true);

        return view('manual.pagina', [
            'pagina' => $pagina,
            'paginas' => $paginas,
            'render' => Manual::render($pagina),
            'anterior' => $posicion > 0 ? $paginas[$posicion - 1] : null,
            'siguiente' => $posicion < count($paginas) - 1 ? $paginas[$posicion + 1] : null,
            'usuario' => $request->user(),
        ]);
    }

    public function pdfPagina(string $slug)
    {
        $pagina = Manual::pagina($slug);
        abort_if($pagina === null, 404);

        return $this->descargar(ManualPdf::archivo([$pagina], false), 'manual-'.$this->siglas().'-'.$pagina->slug.'.pdf');
    }

    public function pdfCompleto()
    {
        abort_if(Manual::paginas() === [], 404);

        return $this->descargar(ManualPdf::archivo(Manual::paginas(), true), 'manual-de-usuario-'.$this->siglas().'.pdf');
    }

    private function descargar(string $ruta, string $nombre)
    {
        return response()->download($ruta, $nombre, ['Content-Type' => 'application/pdf']);
    }

    private function siglas(): string
    {
        return Str::slug(tenant()?->siglas ?? 'ibbla');
    }
}

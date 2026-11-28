<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Manifiesto de la app instalable (PWA) para una instalacion de UNA sola iglesia: el mismo archivo
 * public/manifest.json de siempre, con el nombre, el color y el logo de esa iglesia. Una instalacion con
 * varias iglesias sigue enlazando el archivo estatico (url_manifiesto()); si alguien llega aqui igual,
 * recibe ese mismo contenido.
 */
class ManifiestoController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $manifiesto = json_decode((string) file_get_contents(public_path('manifest.json')), true) ?: [];

        if ($iglesia = iglesia_unica()) {
            $manifiesto['name'] = $iglesia->siglas.' · Administración de la iglesia';
            $manifiesto['short_name'] = $iglesia->siglas;
            $manifiesto['description'] = 'Administración de '.$iglesia->nombre.': sobres, personas, asistencia y reportes';
            $manifiesto['theme_color'] = $iglesia->colors['800'] ?? $manifiesto['theme_color'] ?? '#1e40af';

            $ruta = $iglesia->logo_path ? storage_path('app/public/'.$iglesia->logo_path) : null;
            $medidas = $ruta && is_file($ruta) ? @getimagesize($ruta) : false;
            if ($medidas) {
                $manifiesto['icons'] = [[
                    'src' => Storage::url($iglesia->logo_path),
                    'sizes' => $medidas[0].'x'.$medidas[1],
                    'type' => $medidas['mime'],
                    'purpose' => 'any',
                ]];
            }
        }

        return response()->json($manifiesto, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

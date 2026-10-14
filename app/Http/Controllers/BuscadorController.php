<?php

namespace App\Http\Controllers;

use App\Services\Buscador\BuscadorGlobal;
use Illuminate\Http\Request;

/**
 * Buscador global (barra superior). Responde JSON a la consulta por AJAX de la barra y una pagina
 * completa de resultados cuando se envia el formulario (tambien funciona sin JavaScript).
 */
class BuscadorController extends Controller
{
    public function index(Request $request, BuscadorGlobal $buscador)
    {
        $request->validate([
            'q' => 'nullable|string|max:100',
            'solo' => 'nullable|in:manual',
        ]);

        $q = (string) $request->query('q', '');
        $esJson = $request->expectsJson() || $request->query('formato') === 'json';
        $completa = ! $esJson;

        $resultado = $buscador->buscar($request->user(), $q, $completa ? 15 : 6);

        // El buscador del manual pide solo los temas del manual
        if ($request->query('solo') === 'manual') {
            $resultado['grupos'] = array_values(array_filter($resultado['grupos'], fn ($g) => $g['clave'] === 'manual'));
            $resultado['total'] = array_sum(array_map(fn ($g) => count($g['items']), $resultado['grupos']));
        }

        if ($esJson) {
            return response()->json($resultado);
        }

        return view('buscar.index', ['q' => $resultado['q'], 'resultado' => $resultado]);
    }
}

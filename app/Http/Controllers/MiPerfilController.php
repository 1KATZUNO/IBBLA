<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MiPerfilController extends Controller
{
    /**
     * El miembro ve o descarga su propio estado de cuenta: la persona sale de la sesión, nunca de
     * un id, así nadie puede pedir el de otro. Mismo PDF que el admin manda por WhatsApp.
     */
    public function informe(Request $request, \App\Services\InformePersonaService $informe)
    {
        $persona = Auth::user()->persona;

        if (! $persona) {
            return redirect()->route('mi-perfil.index')
                ->with('error', 'No se encontró información de persona asociada.');
        }

        $pdf = $informe->pdf($persona);
        $nombre = $informe->nombreArchivo($persona);

        return $request->input('accion') === 'descargar' ? $pdf->download($nombre) : $pdf->stream($nombre);
    }

    public function index()
    {
        $user = Auth::user();
        $persona = $user->persona;

        // Cuenta del sistema sin ficha de persona (QA-014): no es un error, se muestra lo que sí tiene (sus avisos).
        if (! $persona) {
            return view('mi-perfil.sin-persona', ['usuario' => $user]);
        }

        // Estado acumulado al corte, las mismas cifras del PDF "Mi estado de cuenta" (motor unico).
        $datos = app(\App\Services\InformePersonaService::class)->datos($persona);

        return view('mi-perfil.index', compact('persona', 'datos'));
    }
}

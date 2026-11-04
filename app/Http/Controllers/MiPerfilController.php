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

    /**
     * Mi perfil del miembro: su estado de compromisos ACUMULADO al dia de hoy, con las mismas cifras
     * del PDF "Mi estado de cuenta" (sale del mismo calculo, InformePersonaService sobre el motor
     * unico). El mes a mes va plegado y solo como detalle: sin "deudas" por mes (regla 2.2). Solo
     * lee, no escribe nada.
     */
    public function index(\App\Services\InformePersonaService $informe)
    {
        $user = Auth::user();
        $persona = $user->persona;

        if (! $persona) {
            return redirect()->route('principal')
                ->with('error', 'No se encontró información de persona asociada.');
        }

        $datos = $informe->datos($persona);

        return view('mi-perfil.index', compact('persona', 'datos'));
    }
}

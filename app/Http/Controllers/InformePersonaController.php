<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Services\InformePersonaService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Estado de cuenta de una persona en PDF, para quien administra (admin y tesorero).
 *
 * Es el mismo informe que viaja por WhatsApp (InformePersonaService), pero servido con sesión:
 * hasta ahora solo se llegaba a él por el enlace firmado del mensaje, así que no había forma de
 * verlo, imprimirlo o descargarlo desde la ficha de la persona.
 */
class InformePersonaController extends Controller
{
    public function show(Request $request, Persona $persona, InformePersonaService $informe): Response
    {
        $validated = $request->validate(['accion' => 'nullable|in:ver,descargar']);

        $pdf = $informe->pdf($persona);
        $nombre = $informe->nombreArchivo($persona);

        return ($validated['accion'] ?? 'ver') === 'descargar' ? $pdf->download($nombre) : $pdf->stream($nombre);
    }
}

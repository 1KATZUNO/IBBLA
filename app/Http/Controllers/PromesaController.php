<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * QE-020: el CRUD viejo de /promesas (sin enlace en el menú) daba 500 y además escribía promesas sin
 * pasar por SincronizadorCompromisos. Hoy las promesas se manejan en la ficha de cada persona
 * (Personas > Editar), así que las direcciones viejas llevan a Personas con una explicación.
 */
class PromesaController extends Controller
{
    public function redirigir(): RedirectResponse
    {
        return redirect()->route('personas.index')
            ->with('info', 'Las promesas se registran y se cambian en la ficha de cada persona: Personas > Editar.');
    }
}

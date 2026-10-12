<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Services\InformePersonaService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Informe individual en PDF por enlace firmado (GET /informe/{persona}).
 *
 * Es público a propósito: el enlace se manda por WhatsApp y quien lo abre no tiene cuenta.
 * La protección es la firma (HMAC con APP_KEY sobre la URL completa, incluido el id de la
 * persona y la fecha de vencimiento) más el límite de peticiones de la ruta: sin firma válida,
 * con la firma alterada o vencido, el middleware «signed» responde 403 antes de llegar aquí.
 */
class InformePublicoController extends Controller
{
    public function __invoke(Request $request, InformePersonaService $informe, string $persona): Response
    {
        // Sin sesión no hay scope por iglesia: el id ya está cubierto por la firma, así que se
        // carga directo. (Con route model binding la persona se buscaría antes de validar la
        // firma y un id inexistente respondería 404 en lugar de 403.)
        $persona = Persona::withoutGlobalScopes()->with('tenant')->findOrFail($persona);

        $iglesia = $persona->tenant;

        // Igual que en el login: una iglesia dada de baja no entrega documentos.
        abort_if($iglesia && ! $iglesia->activo, 404);

        // tenant_pdf_data() y las categorías salen de la iglesia actual. Aquí no hay sesión que la
        // resuelva, así que se fija la de la persona (así el PDF lleva su nombre, logo y categorías).
        app()->instance('current_tenant', $iglesia);

        return $informe->pdf($persona)
            ->stream($informe->nombreArchivo($persona))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En el ambiente QA (APP_ENV=qa, qa.ibbla.eu) pide a los buscadores que no indexen nada.
 * En produccion y local no hace nada. Ver docs/AMBIENTE-QA.md.
 */
class NoIndexEnQa
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        if (app()->environment('qa')) {
            $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $respuesta;
    }
}

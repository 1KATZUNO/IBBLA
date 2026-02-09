<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectMobileApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $isMobileApp = false;

        // Detectar por User-Agent de la app React Native
        $userAgent = $request->header('User-Agent', '');
        if (str_contains($userAgent, 'IBBSCApp')) {
            $isMobileApp = true;
        }

        // Detectar por query param (fallback)
        if ($request->query('mobile') === '1') {
            $isMobileApp = true;
            session(['is_mobile_app' => true]);
        }

        // Persistir en sesion una vez detectado
        if ($isMobileApp) {
            session(['is_mobile_app' => true]);
        }

        // Leer de sesion si ya fue detectado antes
        if (session('is_mobile_app')) {
            $isMobileApp = true;
        }

        view()->share('isMobileApp', $isMobileApp);

        return $next($request);
    }
}

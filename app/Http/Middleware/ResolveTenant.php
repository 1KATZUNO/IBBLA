<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            if ($user->isSuperAdmin()) {
                // Super admin has no tenant
                app()->instance('current_tenant', null);
            } else {
                $tenant = $user->tenant;

                if (! $tenant || ! $tenant->activo) {
                    auth()->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')
                        ->withErrors(['email' => 'Tu iglesia no esta activa. Contacta al administrador.']);
                }

                app()->instance('current_tenant', $tenant);
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            $user = $request->user();
            $routeName = optional($request->route())->getName();

            // QE-013: si la acción ya dejó su registro semántico (cerrar culto, firmas, borrados...),
            // el genérico por ruta sería un duplicado.
            $yaRegistrada = (bool) $request->attributes->get(AuditLog::ATRIBUTO_SEMANTICO);

            // Only log for admin routes or mutations (POST/PUT/PATCH/DELETE)
            if ($user && ! $yaRegistrada && in_array($user->rol, ['admin', 'tesorero'])) {
                AuditLog::$escribiendoGenerico = true;
                AuditLog::create([
                    'user_id' => $user->id ?? null,
                    'user_name' => $user->name ?? ($user->nombre ?? null),
                    'user_email' => $user->email ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 255),
                    'method' => $request->method(),
                    'route' => $routeName ?? $request->path(),
                    'action' => $routeName,
                    'details' => json_encode([
                        'query' => $request->query(),
                        'inputs' => $request->except(['password', 'password_confirmation', '_token']),
                        'status' => $response->getStatusCode(),
                    ]),
                ]);
            }
        } catch (\Throwable $e) {
            // Don't block request on audit errors
        } finally {
            AuditLog::$escribiendoGenerico = false;
        }

        return $response;
    }
}

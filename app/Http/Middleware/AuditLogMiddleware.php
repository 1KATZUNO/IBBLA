<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            $user = $request->user();
            $routeName = optional($request->route())->getName();

            // Only log for admin routes or mutations (POST/PUT/PATCH/DELETE)
            if ($user && in_array($user->rol, ['admin', 'tesorero'])) {
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
        }

        return $response;
    }
}

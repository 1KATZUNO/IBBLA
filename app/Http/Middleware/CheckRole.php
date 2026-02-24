<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * Accepts role slugs (legacy) which are mapped to permission keys.
     * If the user has a TenantRole, check permissions dynamically.
     * Falls back to checking the legacy `rol` column.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect('login');
        }

        $user = auth()->user();

        // Super admin bypasses all role checks
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Map legacy role slugs to permission keys
        $permissionMap = [
            'admin' => 'admin',
            'tesorero' => 'recuento',
            'asistente' => 'asistencia',
            'miembro' => 'mi_perfil',
            'servidor' => 'marcar_asistencia',
            'invitado' => null,
        ];

        // If user has a dynamic TenantRole, check permissions
        if ($user->tenantRole) {
            foreach ($roles as $role) {
                $permission = $permissionMap[$role] ?? $role;
                if ($permission && $user->tenantRole->hasPermission($permission)) {
                    return $next($request);
                }
                // Admin permission grants access to everything
                if ($user->tenantRole->hasPermission('admin')) {
                    return $next($request);
                }
            }
            abort(403, 'No tienes permiso para acceder a esta pagina.');
        }

        // Legacy fallback: check the `rol` column directly
        if (!in_array($user->rol, $roles)) {
            abort(403, 'No tienes permiso para acceder a esta pagina.');
        }

        return $next($request);
    }
}

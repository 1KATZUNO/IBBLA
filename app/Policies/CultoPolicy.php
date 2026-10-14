<?php

namespace App\Policies;

use App\Models\Culto;
use App\Models\User;
use App\Support\Permiso;
use Illuminate\Auth\Access\Response;

/**
 * Quien puede modificar el recuento de un culto. Es la UNICA regla que usan las pantallas y
 * el backend de Recuento (sobres, dinero suelto, egresos, firmas y cierre):
 *
 *  - Administrador: siempre, tambien para corregir un culto cerrado.
 *  - Tesoreria (rol con acceso a Recuento): mientras el culto este abierto. Cerrado = solo lectura.
 *  - Los demas roles no registran recuento (la accion ni se muestra).
 */
class CultoPolicy
{
    public function puedeEditarCulto(User $user, Culto $culto): Response
    {
        if (! $this->esDeSuIglesia($user, $culto)) {
            return Response::denyAsNotFound();
        }

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return Permiso::permitir();
        }

        if (! $user->canAccessRecuento()) {
            return Permiso::ocultar('Tu rol no registra el recuento de los cultos.');
        }

        if (! $culto->cerrado) {
            return Permiso::permitir();
        }

        return Permiso::deshabilitar(self::mensajeCerrado($culto));
    }

    /** "Cerrado el 11/10 por Ana. Solo lectura: ..." (sin fecha o sin nombre si el culto no los tiene). */
    public static function mensajeCerrado(Culto $culto): string
    {
        $cuando = $culto->cerrado_at ? ' el '.$culto->cerrado_at->format('d/m') : '';
        $quien = $culto->cerradoPor ? ' por '.$culto->cerradoPor->name : '';

        return "Cerrado{$cuando}{$quien}. Solo lectura: si hay que corregir algo, pídele al administrador que lo corrija.";
    }

    private function esDeSuIglesia(User $user, Culto $culto): bool
    {
        return $user->isSuperAdmin()
            || $culto->tenant_id === null
            || (int) $culto->tenant_id === (int) $user->tenant_id;
    }
}

<?php

namespace App\Policies;

use App\Models\Culto;
use App\Models\User;
use App\Services\PermisosEdicionService;
use App\Support\Permiso;
use Illuminate\Auth\Access\Response;

/**
 * Quien puede modificar el recuento de un culto y quien puede crear cultos. Es la UNICA regla que
 * usan las pantallas y el backend de Recuento (sobres, dinero suelto, egresos, firmas y cierre):
 *
 *  - Administrador: siempre, tambien para corregir un culto cerrado.
 *  - Tesoreria (rol con acceso a Recuento): mientras el culto este abierto. Cerrado = solo lectura,
 *    salvo que tenga un acceso de edicion vigente que alcance a ese culto (modulo Accesos).
 *  - Los demas roles no registran recuento (la accion ni se muestra).
 *
 * Crear cultos: el administrador y quien tenga un acceso de edicion vigente.
 */
class CultoPolicy
{
    public function __construct(private PermisosEdicionService $permisos) {}

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

        if (! $culto->cerrado || $this->permisos->permisoQueCubre($user, $culto)) {
            return Permiso::permitir();
        }

        return Permiso::deshabilitar(self::mensajeCerrado($culto));
    }

    public function create(User $user): Response
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return Permiso::permitir();
        }

        if (! $user->canAccessRecuento()) {
            return Permiso::ocultar('Tu rol no crea cultos.');
        }

        if ($this->permisos->tieneAlgunoVigente($user)) {
            return Permiso::permitir();
        }

        return Permiso::deshabilitar('Crear cultos le toca al administrador. Si necesitas crear uno, pídele un acceso de edición.');
    }

    /**
     * Eliminar un culto (decision del cliente 13/10, QA-003): solo el administrador. Si el culto esta
     * cerrado o tiene movimientos, el controlador ademas pide confirmacion y motivo y lo audita todo.
     * Los demas roles no ven el boton.
     */
    public function delete(User $user, Culto $culto): Response
    {
        if (! $this->esDeSuIglesia($user, $culto)) {
            return Response::denyAsNotFound();
        }

        return $user->isSuperAdmin() || $user->isAdmin()
            ? Permiso::permitir()
            : Permiso::ocultar('Eliminar cultos le toca solo al administrador.');
    }

    /** "Cerrado el 11/10 por Ana. Solo lectura: ..." (sin fecha o sin nombre si el culto no los tiene). */
    public static function mensajeCerrado(Culto $culto): string
    {
        $cuando = $culto->cerrado_at ? ' el '.$culto->cerrado_at->format('d/m') : '';
        $quien = $culto->cerradoPor ? ' por '.$culto->cerradoPor->name : '';

        return "Cerrado{$cuando}{$quien}. Solo lectura: si hay que corregir algo, pídele al administrador que lo corrija o que te dé acceso de edición.";
    }

    private function esDeSuIglesia(User $user, Culto $culto): bool
    {
        return $user->isSuperAdmin()
            || $culto->tenant_id === null
            || (int) $culto->tenant_id === (int) $user->tenant_id;
    }
}

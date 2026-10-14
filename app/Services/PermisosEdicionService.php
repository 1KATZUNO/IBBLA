<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Culto;
use App\Models\PermisoEdicion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Accesos de edicion sobre cultos cerrados (modulo Accesos, HU-REC-3): consultar si un usuario
 * tiene uno que alcance a un culto, concederlos, revocarlos y dejar todo en la auditoria.
 *
 * La decision de si alguien puede editar un culto NO esta aqui: es CultoPolicy::puedeEditarCulto,
 * que usa este servicio para saber si hay un acceso vigente.
 */
class PermisosEdicionService
{
    public const ACCION_CONCEDER = 'Conceder acceso de edición';

    public const ACCION_REVOCAR = 'Revocar acceso de edición';

    /** Cambio hecho en un culto cerrado gracias a un acceso de edicion. */
    public const ACCION_EDICION = 'Edición bajo permiso';

    /** Cambio hecho por el administrador en un culto cerrado. */
    public const ACCION_CORRECCION = 'Corrección de culto cerrado';

    /** Accesos vigentes del usuario. Se consulta una vez por peticion (la pantalla pregunta por cada boton). */
    public function vigentesDe(User $user): Collection
    {
        $peticion = app()->bound('request') ? app('request') : null;
        $clave = 'permisos_edicion.vigentes.'.$user->id;

        if ($peticion && $peticion->attributes->has($clave)) {
            return $peticion->attributes->get($clave);
        }

        $vigentes = PermisoEdicion::withoutGlobalScope('tenant')
            ->where('user_id', $user->id)
            ->when($user->tenant_id, fn ($q) => $q->where('tenant_id', $user->tenant_id))
            ->vigentes()
            ->orderByDesc('id')
            ->get();

        $peticion?->attributes->set($clave, $vigentes);

        return $vigentes;
    }

    /** El acceso vigente que le permite al usuario editar ese culto, o null. */
    public function permisoQueCubre(User $user, Culto $culto): ?PermisoEdicion
    {
        return $this->vigentesDe($user)->first(fn (PermisoEdicion $permiso) => $permiso->cubre($culto));
    }

    public function tieneAlgunoVigente(User $user): bool
    {
        return $this->vigentesDe($user)->isNotEmpty();
    }

    /**
     * A quien se le puede dar acceso: usuarios de la iglesia que entran a Recuento (tesoreria) y no son
     * administradores (el admin ya puede todo). Para darselo a otra persona primero hay que darle ese rol.
     */
    public function esElegible(User $usuario): bool
    {
        return ! $usuario->isSuperAdmin() && ! $usuario->isAdmin() && $usuario->canAccessRecuento();
    }

    /** @return Collection<int, User> */
    public function usuariosElegibles(?int $tenantId): Collection
    {
        return User::with('tenantRole')
            ->where('tenant_id', $tenantId)
            ->where('is_super_admin', false)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $u) => $this->esElegible($u))
            ->values();
    }

    /**
     * @param  array{tipo: string, cultos?: array<int, int|string>, desde?: ?string, hasta?: ?string, vence_el?: ?string, motivo?: ?string}  $datos
     */
    public function conceder(User $admin, User $usuario, array $datos, Request $request): PermisoEdicion
    {
        $tipo = $datos['tipo'];

        $permiso = PermisoEdicion::create([
            'tenant_id' => $usuario->tenant_id,
            'user_id' => $usuario->id,
            'tipo' => $tipo,
            'referencia' => $tipo === PermisoEdicion::TIPO_CULTOS
                ? array_values(array_unique(array_map('intval', $datos['cultos'] ?? [])))
                : null,
            'desde' => $tipo === PermisoEdicion::TIPO_RANGO ? $datos['desde'] : null,
            'hasta' => $tipo === PermisoEdicion::TIPO_RANGO ? $datos['hasta'] : null,
            // El vencimiento es el final del dia elegido
            'vence_el' => ! empty($datos['vence_el']) ? Carbon::parse($datos['vence_el'])->endOfDay() : null,
            'motivo' => $datos['motivo'] ?? null,
            'otorgado_por' => $admin->id,
        ]);

        $this->auditar($request, $admin, self::ACCION_CONCEDER, [
            'permiso_id' => $permiso->id,
            'usuario_id' => $usuario->id,
            'usuario' => $usuario->name,
            'tipo' => $permiso->tipo,
            'cultos' => $permiso->referencia,
            'desde' => $permiso->desde?->toDateString(),
            'hasta' => $permiso->hasta?->toDateString(),
            'vence_el' => $permiso->vence_el?->toDateTimeString(),
            'motivo' => $permiso->motivo,
        ]);
        $this->olvidar($usuario);

        return $permiso;
    }

    public function revocar(User $admin, PermisoEdicion $permiso, Request $request): void
    {
        if ($permiso->revocado_at !== null) {
            return;
        }

        $permiso->update(['revocado_at' => now(), 'revocado_por' => $admin->id]);

        $this->auditar($request, $admin, self::ACCION_REVOCAR, [
            'permiso_id' => $permiso->id,
            'usuario_id' => $permiso->user_id,
            'usuario' => $permiso->user?->name,
        ]);
        if ($permiso->user) {
            $this->olvidar($permiso->user);
        }
    }

    /**
     * Deja en la auditoria cada cambio hecho en un culto CERRADO: bajo un acceso de edicion (con el id
     * del acceso) o como correccion del administrador. En cultos abiertos no hace nada.
     */
    public function registrarCambioEnCerrado(Request $request, Culto $culto, string $accion, array $detalles = []): void
    {
        $user = $request->user();
        if (! $user || ! $culto->cerrado) {
            return;
        }

        $permiso = ($user->isAdmin() || $user->isSuperAdmin()) ? null : $this->permisoQueCubre($user, $culto);

        $this->auditar($request, $user, $permiso ? self::ACCION_EDICION : self::ACCION_CORRECCION, [
            // permiso_id va primero: el modulo Accesos cuenta las ediciones de cada acceso por este prefijo
            'permiso_id' => $permiso?->id,
            'culto_id' => $culto->id,
            'fecha_culto' => $culto->fecha->toDateString(),
            'accion' => $accion,
        ] + $detalles);
    }

    /**
     * Cuantos cambios se hicieron con cada acceso.
     *
     * @param  Collection<int, PermisoEdicion>  $permisos
     * @return Collection<int, int> permiso_id => cantidad
     */
    public function edicionesPorPermiso(Collection $permisos, ?int $tenantId): Collection
    {
        if ($permisos->isEmpty()) {
            return collect();
        }

        return AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('action', self::ACCION_EDICION)
            ->where('created_at', '>=', $permisos->min('created_at'))
            ->pluck('details')
            ->map(fn ($detalles) => json_decode((string) $detalles, true)['permiso_id'] ?? null)
            ->filter()
            ->countBy();
    }

    /** Ultimos cambios hechos en cultos cerrados (con acceso o por el admin), para el modulo Accesos. */
    public function ultimosCambiosEnCerrados(?int $tenantId, int $cuantos = 15): Collection
    {
        return AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('action', [self::ACCION_EDICION, self::ACCION_CORRECCION])
            ->orderByDesc('id')
            ->limit($cuantos)
            ->get()
            ->map(function (AuditLog $log) {
                $log->setAttribute('datos', json_decode((string) $log->details, true) ?: []);

                return $log;
            });
    }

    private function olvidar(User $user): void
    {
        if (app()->bound('request')) {
            app('request')->attributes->remove('permisos_edicion.vigentes.'.$user->id);
        }
    }

    private function auditar(Request $request, User $user, string $accion, array $detalles): void
    {
        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            'method' => $request->method(),
            'route' => optional($request->route())->getName() ?? $request->path(),
            'action' => $accion,
            'details' => json_encode($detalles, JSON_UNESCAPED_UNICODE),
        ]);
    }
}

<?php

namespace Tests\Concerns;

use App\Models\Asistencia;
use App\Models\AsistenciaClaseDetalle;
use App\Models\ClaseAsistencia;
use App\Models\Culto;
use App\Models\RegistroExtraTipo;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;

/**
 * Datos mínimos para probar asistencia: una iglesia activa con sus roles, un admin,
 * clases, cultos y detalles de clase. Los tests de Breeze crean usuarios sin iglesia
 * y por eso no sirven de modelo (ver docs/LOCAL_SETUP.md, apartado de tests).
 */
trait CreaEscenarioAsistencia
{
    protected Tenant $iglesia;

    protected User $admin;

    protected function crearIglesiaConAdmin(string $slug = 'iglesia-prueba'): void
    {
        $this->iglesia = Tenant::create([
            'nombre' => 'Iglesia de Prueba',
            'siglas' => 'IDP',
            'slug' => $slug,
            'color_theme' => 'blue',
            'timezone' => 'America/Costa_Rica',
            'locale' => 'es',
            'moneda_codigo' => 'CRC',
            'moneda_simbolo' => '₡',
            'activo' => true,
        ]);

        $roles = [];
        foreach (Tenant::defaultRoles() as $rol) {
            $roles[$rol['slug']] = TenantRole::create($rol + ['tenant_id' => $this->iglesia->id]);
        }

        $this->admin = User::create([
            'name' => 'Administrador de Prueba',
            'email' => "admin@{$slug}.test",
            'password' => 'secreto-123',
            'rol' => 'admin',
            'is_super_admin' => false,
            'tenant_id' => $this->iglesia->id,
            'tenant_role_id' => $roles['admin']->id,
        ]);
    }

    /** Segunda iglesia, sin usuarios: sirve para comprobar que sus datos no se mezclan con los de la primera. */
    protected function crearOtraIglesia(string $slug = 'otra-iglesia'): Tenant
    {
        return Tenant::create([
            'nombre' => 'Otra Iglesia',
            'siglas' => 'OTR',
            'slug' => $slug,
            'color_theme' => 'blue',
            'timezone' => 'America/Costa_Rica',
            'locale' => 'es',
            'moneda_codigo' => 'CRC',
            'moneda_simbolo' => '₡',
            'activo' => true,
        ]);
    }

    /** Usuario de la iglesia con uno de los roles por defecto (admin, tesorero, asistente...). */
    protected function crearUsuarioConRol(string $slugRol): User
    {
        $rol = TenantRole::where('tenant_id', $this->iglesia->id)->where('slug', $slugRol)->firstOrFail();

        return User::create([
            'name' => ucfirst($slugRol).' de Prueba',
            'email' => "{$slugRol}@{$this->iglesia->slug}.test",
            'password' => 'secreto-123',
            'rol' => $slugRol,
            'is_super_admin' => false,
            'tenant_id' => $this->iglesia->id,
            'tenant_role_id' => $rol->id,
        ]);
    }

    protected function crearClase(string $nombre, string $slug, int $orden = 1, array $extra = []): ClaseAsistencia
    {
        return ClaseAsistencia::create($extra + [
            'nombre' => $nombre,
            'slug' => $slug,
            'color' => '#3b82f6',
            'orden' => $orden,
            'activa' => true,
            'tiene_maestros' => true,
            'tenant_id' => $this->iglesia->id,
        ]);
    }

    protected function crearTipoExtra(string $slug = 'transmision', array $subcampos = ['miembros']): RegistroExtraTipo
    {
        return RegistroExtraTipo::create([
            'nombre' => ucfirst($slug),
            'slug' => $slug,
            'color' => '#F59E0B',
            'subcampos' => $subcampos,
            'orden' => 1,
            'activo' => true,
            'cuenta_en_asistencia' => false,
            'tenant_id' => $this->iglesia->id,
        ]);
    }

    protected function crearCulto(string $fecha, string $tipo = 'domingo', ?int $tenantId = null): Culto
    {
        return Culto::create([
            'fecha' => $fecha,
            'hora' => $tipo === 'domingo_pm' ? '18:00:00' : '09:00:00',
            'tipo_culto' => $tipo,
            'cerrado' => false,
            'tenant_id' => $tenantId ?? $this->iglesia->id,
        ]);
    }

    /**
     * Crea (o reutiliza) la asistencia del culto y le agrega el detalle de una clase.
     * Los maestros se guardan como el formulario: el total de seleccionados en maestros_hombres.
     */
    protected function registrarClase(Culto $culto, ClaseAsistencia $clase, int $ninos, int $ninas, int $maestros = 0, int $maestrasMujeres = 0): AsistenciaClaseDetalle
    {
        $asistencia = Asistencia::firstOrCreate(
            ['culto_id' => $culto->id],
            ['tenant_id' => $culto->tenant_id, 'total_asistencia' => 0],
        );

        return AsistenciaClaseDetalle::create([
            'asistencia_id' => $asistencia->id,
            'clase_asistencia_id' => $clase->id,
            'hombres' => $ninos,
            'mujeres' => $ninas,
            'maestros_hombres' => $maestros,
            'maestros_mujeres' => $maestrasMujeres,
        ]);
    }
}

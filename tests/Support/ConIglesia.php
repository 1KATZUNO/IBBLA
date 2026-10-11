<?php

namespace Tests\Support;

use App\Models\Tenant;
use App\Models\TenantCategory;
use App\Models\TenantRole;
use App\Models\TenantServiceType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Arma lo mínimo que la app exige para iniciar sesión: una iglesia (tenant) activa
 * con sus categorías, tipos de culto y roles, y usuarios ligados a ella.
 *
 * Es el mismo armado que hace AdminUserSeeder, pero parametrizable para poder crear
 * una segunda iglesia y probar el aislamiento entre ellas. Los usuarios de los tests
 * necesitan tenant activo y tenant_role_id; si no, el login responde "Tu iglesia no
 * esta activa" (por eso fallan los tests de Breeze originales).
 */
trait ConIglesia
{
    /** @var array<int, array<string, TenantRole>> roles por id de iglesia */
    private array $rolesPorIglesia = [];

    protected function crearIglesia(string $slug = 'ibbla', string $siglas = 'IBBLA', bool $activa = true): Tenant
    {
        $iglesia = Tenant::create([
            'nombre' => 'Iglesia '.$siglas,
            'siglas' => $siglas,
            'slug' => $slug,
            'color_theme' => 'blue',
            'timezone' => 'America/Costa_Rica',
            'locale' => 'es',
            'moneda_codigo' => 'CRC',
            'moneda_simbolo' => '₡',
            'activo' => $activa,
        ]);

        foreach (Tenant::defaultCategories() as $categoria) {
            TenantCategory::create($categoria + ['tenant_id' => $iglesia->id]);
        }
        foreach (Tenant::defaultServiceTypes() as $tipo) {
            TenantServiceType::create($tipo + ['tenant_id' => $iglesia->id]);
        }
        foreach (Tenant::defaultRoles() as $rol) {
            $this->rolesPorIglesia[$iglesia->id][$rol['slug']] = TenantRole::create($rol + ['tenant_id' => $iglesia->id]);
        }

        return $iglesia;
    }

    /**
     * @param  string  $rol  admin | tesorero | asistente | miembro | servidor | invitado
     */
    protected function crearUsuario(Tenant $iglesia, string $rol = 'admin', array $atributos = []): User
    {
        static $contador = 0;
        $contador++;

        return User::create($atributos + [
            'name' => ucfirst($rol).' '.$contador,
            'email' => "{$rol}{$contador}@{$iglesia->slug}.test",
            'password' => Hash::make('password'),
            'rol' => $rol,
            'is_super_admin' => false,
            'tenant_id' => $iglesia->id,
            'tenant_role_id' => $this->rolesPorIglesia[$iglesia->id][$rol]->id ?? null,
        ]);
    }

    protected function crearSuperAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'email' => 'super@plataforma.test',
            'password' => Hash::make('password'),
            'rol' => 'admin',
            'is_super_admin' => true,
            'tenant_id' => null,
            'tenant_role_id' => null,
        ]);
    }
}

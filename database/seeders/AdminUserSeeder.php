<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantCategory;
use App\Models\TenantEmailDomain;
use App\Models\TenantRole;
use App\Models\TenantServiceType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Iglesia por defecto del entorno local (IBBLA). El slug y el dominio de correo se conservan
        // porque los usuarios de prueba, los tests y la documentacion usan @ibbsc.com.
        $tenant = Tenant::create([
            'nombre' => 'Iglesia Bíblica Bautista en La Aurora',
            'siglas' => 'IBBLA',
            'slug' => 'ibbsc',
            'color_theme' => 'blue',
            'timezone' => 'America/Costa_Rica',
            'locale' => 'es',
            'moneda_codigo' => 'CRC',
            'moneda_simbolo' => '₡',
            'redes_sociales' => [],
            'activo' => true,
        ]);

        // Create email domain
        TenantEmailDomain::create([
            'tenant_id' => $tenant->id,
            'dominio' => 'ibbsc.com',
            'principal' => true,
            'activo' => true,
        ]);

        // Create default categories
        foreach (Tenant::defaultCategories() as $cat) {
            TenantCategory::create(array_merge($cat, ['tenant_id' => $tenant->id]));
        }

        // Create default service types
        foreach (Tenant::defaultServiceTypes() as $st) {
            TenantServiceType::create(array_merge($st, ['tenant_id' => $tenant->id]));
        }

        // Create default roles
        $roles = [];
        foreach (Tenant::defaultRoles() as $role) {
            $roles[$role['slug']] = TenantRole::create(array_merge($role, ['tenant_id' => $tenant->id]));
        }

        // Super Admin (no tenant)
        User::create([
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'password' => Hash::make('admin123'),
            'rol' => 'admin',
            'is_super_admin' => true,
            'tenant_id' => null,
            'tenant_role_id' => null,
        ]);

        // Tenant Admin
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@ibbsc.com',
            'password' => Hash::make('admin123'),
            'rol' => 'admin',
            'is_super_admin' => false,
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $roles['admin']->id,
        ]);

        // Tenant Tesorero
        User::create([
            'name' => 'Tesorero',
            'email' => 'tesorero@ibbsc.com',
            'password' => Hash::make('tesorero123'),
            'rol' => 'tesorero',
            'is_super_admin' => false,
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $roles['tesorero']->id,
        ]);
    }
}

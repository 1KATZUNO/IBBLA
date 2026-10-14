<?php

namespace Database\Seeders;

use App\Models\Persona;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Un usuario por rol SOLO para desarrollo local (no se llama desde DatabaseSeeder).
 *
 *   php artisan db:seed --class=UsuariosDemoSeeder
 *
 * Sirve para probar pantallas por rol y para las capturas del manual (tools/manual). Se agregan al
 * primer tenant (el de AdminUserSeeder), junto a admin@ibbsc.com y tesorero@ibbsc.com:
 *
 *   asistente@ibbsc.com / asistente123   (Asistente)
 *   servidor@ibbsc.com  / servidor123    (Servidor: marca su asistencia con el QR del culto)
 *   miembro@ibbsc.com   / miembro123     (Miembro, ligado a una persona con promesas para ver Mi perfil)
 *
 * Es re-ejecutable: actualiza lo que ya existe. Se niega a correr con APP_ENV=production.
 */
class UsuariosDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('UsuariosDemoSeeder es solo para desarrollo local.');

            return;
        }

        $tenant = Tenant::query()->orderBy('id')->first();
        if (! $tenant) {
            $this->command?->error('No hay tenant: corre primero php artisan migrate --seed.');

            return;
        }

        $roles = TenantRole::where('tenant_id', $tenant->id)->get()->keyBy('slug');

        $miembro = $this->usuario($tenant, $roles, 'miembro', 'Miembro Demo', 'miembro@ibbsc.com', 'miembro123');
        $this->usuario($tenant, $roles, 'asistente', 'Asistente Demo', 'asistente@ibbsc.com', 'asistente123');
        $this->usuario($tenant, $roles, 'servidor', 'Servidor Demo', 'servidor@ibbsc.com', 'servidor123');

        // El miembro ve sus promesas y compromisos: se liga a una persona que ya tenga promesas
        // (VolumenDevSeeder), activa y con sobres; si no hay datos de volumen, a una persona nueva.
        $persona = Persona::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('activo', true)->whereHas('promesas')->whereHas('sobres')->orderBy('id')->first()
            ?? Persona::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'nombre' => 'Miembro Demo'],
                ['activo' => true, 'telefono' => '8888-0000']
            );
        Persona::withoutGlobalScopes()->where('user_id', $miembro->id)->where('id', '!=', $persona->id)->update(['user_id' => null]);
        $persona->forceFill(['user_id' => $miembro->id])->save();

        $this->command?->info('Usuarios demo: miembro@ibbsc.com, asistente@ibbsc.com, servidor@ibbsc.com (contraseña: <rol>123).');
    }

    private function usuario(Tenant $tenant, $roles, string $rol, string $nombre, string $correo, string $clave): User
    {
        return User::updateOrCreate(
            ['email' => $correo],
            [
                'name' => $nombre,
                'password' => Hash::make($clave),
                'rol' => $rol,
                'is_super_admin' => false,
                'tenant_id' => $tenant->id,
                'tenant_role_id' => $roles[$rol]->id ?? null,
            ]
        );
    }
}

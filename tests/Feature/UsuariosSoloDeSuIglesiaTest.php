<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/** QA-012: el admin de la iglesia no abre, guarda ni borra al Super Admin ni a usuarios de otra iglesia. */
class UsuariosSoloDeSuIglesiaTest extends TestCase
{
    use ConIglesia, RefreshDatabase;

    public function test_el_admin_no_toca_al_super_admin(): void
    {
        $iglesia = $this->crearIglesia();
        $admin = $this->crearUsuario($iglesia, 'admin');
        $super = $this->crearSuperAdmin();

        $this->actingAs($admin)->get(route('usuarios.edit', $super))->assertNotFound();
        $this->actingAs($admin)->put(route('usuarios.update', $super), [
            'name' => 'Cambiado', 'email' => $super->email, 'rol' => 'miembro',
        ])->assertNotFound();
        $this->actingAs($admin)->delete(route('usuarios.destroy', $super))->assertNotFound();

        $this->assertSame('Super Admin', $super->fresh()->name);
        $this->assertNotNull(User::find($super->id));
    }

    public function test_el_admin_no_toca_usuarios_de_otra_iglesia(): void
    {
        $iglesia = $this->crearIglesia();
        $otra = $this->crearIglesia('otra', 'OTRA');
        $admin = $this->crearUsuario($iglesia, 'admin');
        $ajeno = $this->crearUsuario($otra, 'tesorero');

        $this->actingAs($admin)->get(route('usuarios.edit', $ajeno))->assertNotFound();
        $this->actingAs($admin)->delete(route('usuarios.destroy', $ajeno))->assertNotFound();
        $this->assertNotNull(User::find($ajeno->id));
    }

    public function test_el_admin_si_edita_usuarios_de_su_iglesia_y_no_hay_pantalla_de_detalle(): void
    {
        $iglesia = $this->crearIglesia();
        $admin = $this->crearUsuario($iglesia, 'admin');
        $tesorero = $this->crearUsuario($iglesia, 'tesorero');

        $this->actingAs($admin)->get(route('usuarios.edit', $tesorero))->assertOk();
        $this->actingAs($admin)->put(route('usuarios.update', $tesorero), [
            'name' => 'Tesorera Nueva', 'email' => $tesorero->email, 'rol' => 'tesorero',
        ])->assertRedirect(route('usuarios.index'));
        $this->assertSame('Tesorera Nueva', $tesorero->fresh()->name);

        // /usuarios/{id} ya no da 500: no existe
        $this->actingAs($admin)->get('/usuarios/'.$tesorero->id)->assertStatus(405);
    }

    public function test_no_se_asigna_un_rol_dinamico_de_otra_iglesia(): void
    {
        $iglesia = $this->crearIglesia();
        $otra = $this->crearIglesia('otra', 'OTRA');
        $admin = $this->crearUsuario($iglesia, 'admin');
        $rolAjeno = $otra->roles()->where('slug', 'admin')->firstOrFail();

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Alguien', 'email' => 'alguien@ibbla.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'rol' => 'miembro', 'tenant_role_id' => $rolAjeno->id,
        ])->assertSessionHasErrors('tenant_role_id');
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Decision del cliente (13/10, QA-017): se elimina "Entrar como invitado". Solo entra quien tiene
 * usuario y contrasena, y el rol Invitado ya no se ofrece al crear usuarios.
 */
class SinInvitadoTest extends TestCase
{
    use ConIglesia, RefreshDatabase;

    public function test_el_login_no_ofrece_entrar_como_invitado(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Invitado')
            ->assertDontSee('login/guest');

        $this->assertFalse(Route::has('login.guest'));
        $this->post('/login/guest')->assertNotFound();
        $this->assertGuest();
    }

    public function test_el_rol_invitado_no_se_ofrece_ni_se_acepta_al_crear_usuarios(): void
    {
        $iglesia = $this->crearIglesia();
        $admin = $this->crearUsuario($iglesia, 'admin');

        $this->actingAs($admin)->get(route('usuarios.create'))
            ->assertOk()
            ->assertDontSee('value="invitado"', false);

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Alguien',
            'email' => 'alguien@ibbla.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'rol' => 'invitado',
        ])->assertSessionHasErrors('rol');
    }
}

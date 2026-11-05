<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\Navegacion\Menu;
use App\Support\Tema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Barra superior fija (upbar): logo, buscador global, manual, usuario y rol, salir; convive con el
 * menu lateral y los colores salen del tema de la iglesia.
 */
class UpbarTest extends TestCase
{
    use ConIglesia;
    use RefreshDatabase;

    private Tenant $iglesia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia('ibbla-ui', 'UIX');
    }

    public function test_la_barra_superior_trae_logo_buscador_manual_usuario_rol_y_salir(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin', ['name' => 'Ana Administradora']);

        $this->actingAs($admin)->get('/principal')
            ->assertOk()
            ->assertSee('id="upbar"', false)
            ->assertSee('UIX')                                   // siglas junto al logo
            ->assertSee('Ana Administradora')                    // usuario
            ->assertSee('Administrador')                         // rol
            ->assertSee('action="'.route('logout').'"', false)   // salir
            ->assertSee('id="sidebar"', false);                  // convive con el menu lateral
    }

    public function test_todos_los_roles_ven_la_barra_superior_con_su_nombre_y_rol(): void
    {
        $roles = ['admin' => 'Administrador', 'tesorero' => 'Tesorero', 'asistente' => 'Asistente', 'miembro' => 'Miembro', 'servidor' => 'Servidor'];

        foreach ($roles as $rol => $etiqueta) {
            $usuario = $this->crearUsuario($this->iglesia, $rol, ['name' => "Usuario {$etiqueta}"]);

            $this->actingAs($usuario)->get('/principal')
                ->assertOk()
                ->assertSee('id="upbar"', false)
                ->assertSee("Usuario {$etiqueta}")
                ->assertSee('action="'.route('logout').'"', false);
        }
    }

    public function test_el_menu_del_usuario_lleva_a_mi_cuenta_y_el_miembro_ve_mi_perfil(): void
    {
        $miembro = $this->crearUsuario($this->iglesia, 'miembro');
        $admin = $this->crearUsuario($this->iglesia, 'admin');
        // "Yo" (Mi perfil) solo se ofrece a quien tiene una persona vinculada (docs/ROLES.md).
        \App\Models\Persona::withoutGlobalScopes()->create(['nombre' => 'Miembro Prueba', 'activo' => true, 'tenant_id' => $this->iglesia->id, 'user_id' => $miembro->id]);

        $this->actingAs($miembro->fresh())->get('/principal')
            ->assertSee('href="'.route('profile.edit').'"', false)
            ->assertSee('href="'.route('mi-perfil.index').'"', false);

        // El admin no tiene la pantalla "Yo": su menu de usuario solo lleva a Mi cuenta
        $this->actingAs($admin)->get('/principal')
            ->assertSee('href="'.route('profile.edit').'"', false)
            ->assertDontSee('href="'.route('mi-perfil.index').'"', false);
    }

    public function test_el_perfil_de_usuario_usa_el_mismo_marco_que_el_resto_de_la_app(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin');

        $this->actingAs($admin)->get('/profile')
            ->assertOk()
            ->assertSee('id="upbar"', false)
            ->assertSee('id="sidebar"', false)
            ->assertSee('Mi cuenta');
    }

    public function test_el_modo_app_movil_conserva_su_cabecera_sin_barra_superior(): void
    {
        $asistente = $this->crearUsuario($this->iglesia, 'asistente');

        $this->actingAs($asistente)->get('/principal?mobile=1')
            ->assertOk()
            ->assertDontSee('id="upbar"', false)
            ->assertSee('id="mobileBottomNav"', false);
    }

    public function test_la_barra_y_el_menu_toman_los_colores_de_la_iglesia(): void
    {
        $this->iglesia->update(['color_theme' => 'green']);
        $admin = $this->crearUsuario($this->iglesia, 'admin');

        $this->actingAs($admin)->get('/principal')
            ->assertOk()
            ->assertSee('--t-900:#14532d;--t-900-rgb:20 83 45;', false);
    }

    public function test_las_pantallas_con_titulo_pintan_el_encabezado_de_pagina(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin');

        $this->actingAs($admin)->get('/cultos')
            ->assertOk()
            ->assertSee('class="page-title"', false);
    }

    public function test_tema_convierte_hexadecimal_a_canales_rgb(): void
    {
        $this->assertSame('37 99 235', Tema::rgb('#2563eb'));
        $this->assertSame('34 102 238', Tema::rgb('#26e'));
        $this->assertSame('37 99 235', Tema::rgb('no-es-un-color'), 'valor invalido: cae al azul por defecto');
        $this->assertSame('PA', Tema::iniciales('Paula Araya Zuniga'));
        $this->assertSame('A', Tema::iniciales('Administrador'));
    }

    public function test_el_menu_nunca_incluye_rutas_que_no_existen(): void
    {
        // Las entradas de otros equipos (Centro de Reportes, Accesos) solo aparecen cuando su ruta existe
        $admin = $this->crearUsuario($this->iglesia, 'admin');
        $this->actingAs($admin);

        foreach (Menu::grupos($admin) as $grupo) {
            foreach ($grupo['items'] as $item) {
                $this->assertTrue(\Illuminate\Support\Facades\Route::has($item['ruta']), "La ruta {$item['ruta']} no existe");
            }
        }
    }
}

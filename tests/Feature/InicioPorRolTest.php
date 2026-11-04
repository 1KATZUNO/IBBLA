<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * QA-011: con sesion abierta, "/", /login y la app instalada llevan a cada rol a su inicio (antes
 * /dashboard y 403). QA-014: "Mi perfil" solo con persona vinculada (tambien el admin). QA-015 y
 * decision 2.7: dashboard de asistencia para admin, tesorero y asistente. Decision 3: alta rapida
 * de personas para el tesorero sin el modulo Personas.
 */
class InicioPorRolTest extends TestCase
{
    use ConIglesia, RefreshDatabase;

    private Tenant $iglesia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
    }

    public function test_con_sesion_la_raiz_y_el_login_llevan_al_inicio_de_cada_rol(): void
    {
        $esperado = [
            'admin' => 'principal',
            'tesorero' => 'principal',
            'asistente' => 'principal',
            'servidor' => 'principal',
            'miembro' => 'mi-perfil.index',
        ];

        foreach ($esperado as $rol => $ruta) {
            $usuario = $this->crearUsuario($this->iglesia, $rol);
            $this->actingAs($usuario)->get('/')->assertRedirect(route($ruta));
            $this->actingAs($usuario)->get('/login')->assertRedirect(route($ruta));
        }
    }

    public function test_sin_sesion_la_raiz_va_al_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_al_iniciar_sesion_el_miembro_va_a_mi_perfil_y_el_asistente_a_principal(): void
    {
        $miembro = $this->crearUsuario($this->iglesia, 'miembro', ['email' => 'm@ibbla.test']);
        $this->post('/login', ['email' => 'm@ibbla.test', 'password' => 'password'])
            ->assertRedirect(route('mi-perfil.index', absolute: false));
        auth()->logout();

        $this->crearUsuario($this->iglesia, 'asistente', ['email' => 'a@ibbla.test']);
        $this->post('/login', ['email' => 'a@ibbla.test', 'password' => 'password'])
            ->assertRedirect(route('principal', absolute: false));
        $this->assertNotNull($miembro);
    }

    public function test_mi_perfil_solo_se_ofrece_con_persona_vinculada(): void
    {
        $tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $this->assertFalse($tesorero->tieneMiPerfil());
        // Acceso rapido de Principal (el menu lateral lo aplica el equipo de UI con el mismo metodo)
        $this->actingAs($tesorero)->get(route('principal'))
            ->assertOk()
            ->assertDontSee('Mis promesas y compromisos');

        $admin = $this->crearUsuario($this->iglesia, 'admin');
        Persona::withoutGlobalScopes()->create(['nombre' => 'Roy', 'activo' => true, 'tenant_id' => $this->iglesia->id, 'user_id' => $admin->id]);
        $this->assertTrue($admin->fresh()->tieneMiPerfil());
        $this->actingAs($admin->fresh())->get(route('principal'))
            ->assertOk()
            ->assertSee('Mis promesas y compromisos');
        // El admin tambien entra a su Mi perfil
        $this->actingAs($admin->fresh())->get(route('mi-perfil.index'))->assertOk();
    }

    public function test_dashboard_de_asistencia_para_admin_tesorero_y_asistente(): void
    {
        foreach (['admin', 'tesorero', 'asistente'] as $rol) {
            $usuario = $this->crearUsuario($this->iglesia, $rol);
            $this->assertTrue($usuario->puedeVerDashboardAsistencia(), $rol);
            $this->actingAs($usuario)->get(route('dashboard.asistencia'))->assertOk();
        }

        $miembro = $this->crearUsuario($this->iglesia, 'miembro');
        $this->assertFalse($miembro->puedeVerDashboardAsistencia());
        $this->actingAs($miembro)->get(route('dashboard.asistencia'))->assertForbidden();
    }

    public function test_el_tesorero_agrega_personas_rapido_sin_ver_el_modulo_personas(): void
    {
        $tesorero = $this->crearUsuario($this->iglesia, 'tesorero');

        $this->actingAs($tesorero)->get(route('personas.index'))->assertForbidden();
        $this->actingAs($tesorero)->get(route('principal'))->assertSee(route('personas.alta-rapida'), false);
        $this->actingAs($tesorero)->get(route('personas.alta-rapida'))->assertOk()->assertSee('Agregar persona');

        $this->actingAs($tesorero)->from(route('personas.alta-rapida'))
            ->post(route('personas.quick-store'), ['nombre' => 'Visita Nueva', 'telefono' => '8888-2222'])
            ->assertRedirect(route('personas.alta-rapida'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('personas', ['nombre' => 'Visita Nueva', 'telefono' => '8888-2222', 'activo' => true]);

        $asistente = $this->crearUsuario($this->iglesia, 'asistente');
        $this->actingAs($asistente)->get(route('personas.alta-rapida'))->assertForbidden();
    }
}

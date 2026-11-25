<?php

namespace Tests\Feature;

use App\Models\Promesa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * QE-020 (resto de QA-019): las direcciones viejas no muestran la página de error de Laravel.
 * /promesas daba 500 y /usuarios/{id} 405: ahora llevan a donde se hace eso hoy.
 */
class RutasViejasTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');
    }

    public function test_las_direcciones_viejas_de_promesas_llevan_a_personas(): void
    {
        $persona = $this->crearPersonaDe($this->iglesia);
        $promesa = Promesa::create(['persona_id' => $persona->id, 'categoria' => 'diezmo', 'monto' => 1000, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);

        foreach (['/promesas', '/promesas/create', "/promesas/{$promesa->id}", "/promesas/{$promesa->id}/edit"] as $url) {
            $this->actingAs($this->admin)->get($url)
                ->assertRedirect(route('personas.index'))
                ->assertSessionHas('info');
        }

        $this->actingAs($this->admin)->followingRedirects()->get('/promesas')
            ->assertOk()
            ->assertSee('Las promesas se registran y se cambian en la ficha de cada persona');
    }

    public function test_ya_no_se_crean_promesas_por_fuera_de_la_ficha(): void
    {
        $persona = $this->crearPersonaDe($this->iglesia);

        $this->actingAs($this->admin)->post('/promesas', [
            'persona_id' => $persona->id, 'categoria' => 'diezmo', 'monto' => 1000, 'frecuencia' => 'mensual',
        ])->assertStatus(405);

        $this->assertSame(0, Promesa::count());
    }

    public function test_detalle_de_usuario_lleva_a_la_lista(): void
    {
        $otro = $this->crearUsuario($this->iglesia, 'tesorero');

        $this->actingAs($this->admin)->get("/usuarios/{$otro->id}")->assertRedirect(route('usuarios.index'));
        $this->actingAs($this->admin)->get('/usuarios/999999')->assertRedirect(route('usuarios.index'));
    }

    public function test_los_demas_roles_reciben_403_y_no_un_error(): void
    {
        foreach (['tesorero', 'asistente', 'miembro'] as $rol) {
            $usuario = $this->crearUsuario($this->iglesia, $rol);

            $this->actingAs($usuario)->get('/promesas')->assertForbidden();
            $this->actingAs($usuario)->get("/usuarios/{$this->admin->id}")->assertForbidden();
        }
    }

    public function test_register_y_profile_no_dan_error(): void
    {
        $this->get('/register')->assertStatus(404);
        $this->actingAs($this->admin)->get('/profile')->assertOk()->assertDontSee('Delete Account');
    }
}

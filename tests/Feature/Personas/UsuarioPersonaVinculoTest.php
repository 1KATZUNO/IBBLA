<?php

namespace Tests\Feature\Personas;

use App\Models\AuditLog;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * QA-018: al crear o editar un usuario se lo vincula con su persona (buscador, no desplegable), y el
 * usuario que crea Personas > Editar lleva el rol dinámico "miembro" como los de Usuarios.
 */
class UsuarioPersonaVinculoTest extends TestCase
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

    private function datosUsuario(array $extra = []): array
    {
        return $extra + [
            'name' => 'Ana Mora',
            'email' => 'ana@ibbla.test',
            'password' => 'secreta123',
            'password_confirmation' => 'secreta123',
            'rol' => 'miembro',
        ];
    }

    public function test_crear_usuario_vinculado_con_su_persona(): void
    {
        $ana = $this->crearPersonaDe($this->iglesia, 'Ana Mora');

        $this->actingAs($this->admin)->post(route('usuarios.store'), $this->datosUsuario(['persona_id' => $ana->id]))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('success', 'Usuario creado exitosamente. Quedó vinculado con Ana Mora.');

        $usuario = User::where('email', 'ana@ibbla.test')->sole();
        $this->assertSame($usuario->id, (int) $ana->fresh()->user_id);
        $this->assertNotNull($usuario->tenant_role_id);
        $this->assertSame(1, AuditLog::where('action', 'Vincular usuario con persona')->count());
    }

    public function test_editar_cambia_y_quita_el_vinculo(): void
    {
        $ana = $this->crearPersonaDe($this->iglesia, 'Ana Mora');
        $luis = $this->crearPersonaDe($this->iglesia, 'Luis Ulate');
        $usuario = $this->crearUsuario($this->iglesia, 'miembro', ['name' => 'Ana Mora', 'email' => 'ana@ibbla.test']);
        $ana->forceFill(['user_id' => $usuario->id])->save();

        $this->actingAs($this->admin)->get(route('usuarios.edit', $usuario))
            ->assertOk()->assertSee('Persona vinculada')->assertSee('Ana Mora');

        $datos = ['name' => 'Ana Mora', 'email' => 'ana@ibbla.test', 'rol' => 'miembro'];
        $this->actingAs($this->admin)->put(route('usuarios.update', $usuario), $datos + ['persona_id' => $luis->id])
            ->assertRedirect(route('usuarios.index'));
        $this->assertNull($ana->fresh()->user_id);
        $this->assertSame($usuario->id, (int) $luis->fresh()->user_id);

        $this->actingAs($this->admin)->put(route('usuarios.update', $usuario), $datos + ['persona_id' => ''])
            ->assertRedirect(route('usuarios.index'));
        $this->assertNull($luis->fresh()->user_id);
    }

    public function test_no_se_vincula_una_persona_que_ya_tiene_otro_usuario_ni_de_otra_iglesia(): void
    {
        $ana = $this->crearPersonaDe($this->iglesia, 'Ana Mora');
        $otro = $this->crearUsuario($this->iglesia, 'miembro');
        $ana->forceFill(['user_id' => $otro->id])->save();
        $ajena = $this->crearPersonaDe($this->crearIglesia('otra', 'OTRA'), 'Ajena');

        foreach ([$ana->id, $ajena->id] as $id) {
            $this->actingAs($this->admin)->post(route('usuarios.store'), $this->datosUsuario(['persona_id' => $id, 'email' => "u{$id}@ibbla.test"]))
                ->assertSessionHasErrors('persona_id');
        }
        $this->assertSame($otro->id, (int) $ana->fresh()->user_id);
    }

    public function test_el_buscador_encuentra_por_nombre_pin_y_telefono_y_marca_las_ocupadas(): void
    {
        $ana = $this->crearPersonaDe($this->iglesia, 'Ana Mora');
        $ana->update(['pin' => '4321', 'telefono' => '8815-2233']);
        $ocupada = $this->crearPersonaDe($this->iglesia, 'Ana Ocupada');
        $ocupada->forceFill(['user_id' => $this->admin->id])->save();
        $this->crearPersonaDe($this->crearIglesia('otra', 'OTRA'), 'Ana Ajena');

        $porNombre = $this->actingAs($this->admin)->getJson(route('usuarios.buscar-personas', ['q' => 'Ana']))->assertOk()->json();
        $this->assertEqualsCanonicalizing(['Ana Mora', 'Ana Ocupada'], array_column($porNombre, 'nombre'));
        $this->assertTrue(collect($porNombre)->firstWhere('nombre', 'Ana Ocupada')['ocupada']);
        $this->assertFalse(collect($porNombre)->firstWhere('nombre', 'Ana Mora')['ocupada']);

        $this->assertSame('Ana Mora', $this->getJson(route('usuarios.buscar-personas', ['q' => '4321']))->json('0.nombre'));
        $this->assertSame('Ana Mora', $this->getJson(route('usuarios.buscar-personas', ['q' => '88152233']))->json('0.nombre'));

        // La persona del propio usuario que se edita no sale como ocupada
        $propia = $this->getJson(route('usuarios.buscar-personas', ['q' => 'Ocupada', 'usuario' => $this->admin->id]))->json();
        $this->assertFalse($propia[0]['ocupada']);
    }

    public function test_solo_el_admin_usa_el_buscador(): void
    {
        foreach (['tesorero', 'asistente', 'miembro'] as $rol) {
            $this->actingAs($this->crearUsuario($this->iglesia, $rol))
                ->getJson(route('usuarios.buscar-personas', ['q' => 'Ana']))->assertForbidden();
        }
    }

    public function test_el_usuario_creado_desde_la_ficha_lleva_rol_dinamico_miembro(): void
    {
        $ana = $this->crearPersonaDe($this->iglesia, 'Ana Mora');

        $this->actingAs($this->admin)->put(route('personas.update', $ana), [
            'nombre' => 'Ana Mora', 'correo' => 'ana@ibbla.test', 'password' => 'secreta123', 'activo' => 1,
        ])->assertRedirect(route('personas.index'));

        $usuario = User::where('email', 'ana@ibbla.test')->sole();
        $this->assertSame('miembro', $usuario->rol);
        $this->assertNotNull($usuario->tenant_role_id);
        $this->assertSame('miembro', $usuario->tenantRole->slug);
        $this->assertSame($usuario->id, (int) $ana->fresh()->user_id);
    }

    public function test_el_usuario_creado_al_registrar_la_persona_tambien(): void
    {
        $this->actingAs($this->admin)->post(route('personas.store'), [
            'nombre' => 'Luis Ulate', 'correo' => 'luis@ibbla.test', 'password' => 'secreta123', 'activo' => 1,
        ])->assertRedirect(route('personas.index'));

        $this->assertSame('miembro', User::where('email', 'luis@ibbla.test')->sole()->tenantRole?->slug);
    }
}

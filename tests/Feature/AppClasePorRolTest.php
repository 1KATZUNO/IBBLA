<?php

namespace Tests\Feature;

use App\Models\ClaseAsistencia;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * QE-016: el tesorero en /app/clase recibia un 302 al login, y el miembro que no es maestra veia
 * "Selecciona una clase / No hay clases activas". Matriz (docs/ROLES.md): admin todas, maestra solo la
 * suya, los demas roles no usan la App de Clases.
 */
class AppClasePorRolTest extends TestCase
{
    use ConIglesia, RefreshDatabase;

    private Tenant $iglesia;

    private ClaseAsistencia $clase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
        $this->clase = ClaseAsistencia::create([
            'tenant_id' => $this->iglesia->id, 'nombre' => 'Niños 2-6', 'slug' => 'ninos_2_6', 'activa' => true, 'orden' => 1,
        ]);
        ClaseAsistencia::create([
            'tenant_id' => $this->iglesia->id, 'nombre' => 'Jóvenes', 'slug' => 'jovenes', 'activa' => true, 'orden' => 2,
        ]);
    }

    public function test_el_tesorero_recibe_403_con_la_pantalla_sin_permiso(): void
    {
        $this->actingAs($this->crearUsuario($this->iglesia, 'tesorero'))
            ->get(route('clase-app.index'))
            ->assertForbidden()
            ->assertSee('Acceso Denegado')
            ->assertSee('La App de Clases es para el administrador y las maestras de cada clase.');
    }

    public function test_el_tesorero_tampoco_entra_a_una_clase_por_url(): void
    {
        $this->actingAs($this->crearUsuario($this->iglesia, 'tesorero'))
            ->get(route('clase-app.shell', 'ninos_2_6'))
            ->assertForbidden();
    }

    public function test_el_asistente_sin_clase_recibe_403(): void
    {
        $this->actingAs($this->crearUsuario($this->iglesia, 'asistente'))
            ->get(route('clase-app.index'))
            ->assertForbidden();
    }

    public function test_el_miembro_sin_clase_ve_un_mensaje_claro(): void
    {
        $this->actingAs($this->crearUsuario($this->iglesia, 'miembro'))
            ->get(route('clase-app.index'))
            ->assertOk()
            ->assertSee('No tienes una clase asignada.')
            ->assertSee('Pídele al administrador que te asigne una.')
            ->assertDontSee('Selecciona una clase')
            ->assertDontSee('No hay clases activas');
    }

    public function test_el_miembro_maestra_entra_a_su_clase(): void
    {
        $maestra = $this->crearUsuario($this->iglesia, 'miembro', ['clase_asistencia_id' => $this->clase->id]);

        $this->actingAs($maestra)->get(route('clase-app.index'))
            ->assertRedirect(route('clase-app.shell', 'ninos_2_6'));
        $this->actingAs($maestra)->get(route('clase-app.shell', 'jovenes'))
            ->assertForbidden();
    }

    public function test_el_admin_ve_todas_las_clases(): void
    {
        $this->actingAs($this->crearUsuario($this->iglesia, 'admin'))
            ->get(route('clase-app.index'))
            ->assertOk()
            ->assertSee('Niños 2-6')
            ->assertSee('Jóvenes');
    }
}

<?php

namespace Tests\Feature\Ministerios;

use App\Models\Ministerio;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Asignación de ministerios a personas (formularios de Gestionar Personas).
 */
class MinisterioAsignacionPersonaTest extends TestCase
{
    use ConIglesia, RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');
    }

    private function ministerio(string $nombre, array $extra = [], ?Tenant $iglesia = null): Ministerio
    {
        return Ministerio::create($extra + [
            'tenant_id' => ($iglesia ?? $this->iglesia)->id,
            'nombre' => $nombre,
        ]);
    }

    private function persona(string $nombre = 'Ana Pérez'): Persona
    {
        return Persona::create(['nombre' => $nombre, 'tenant_id' => $this->iglesia->id, 'activo' => true]);
    }

    public function test_crear_una_persona_con_ministerios_los_asigna_con_lider_y_fecha(): void
    {
        $alabanza = $this->ministerio('Alabanza');
        $ujieres = $this->ministerio('Ujieres');

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'nombre' => 'Carlos Mora',
            'activo' => '1',
            'ministerios' => [
                ['ministerio_id' => $alabanza->id, 'es_lider' => '1'],
                ['ministerio_id' => $ujieres->id],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('personas.index'));

        $persona = Persona::where('nombre', 'Carlos Mora')->firstOrFail();
        $asignados = $persona->ministerios->keyBy('id');

        $this->assertCount(2, $asignados);
        $this->assertTrue((bool) $asignados[$alabanza->id]->pivot->es_lider);
        $this->assertFalse((bool) $asignados[$ujieres->id]->pivot->es_lider);
        $this->assertSame(now()->toDateString(), substr((string) $asignados[$alabanza->id]->pivot->desde, 0, 10));
        $this->assertNull($asignados[$alabanza->id]->pivot->hasta);
    }

    public function test_editar_una_persona_agrega_y_quita_ministerios_y_conserva_la_fecha_de_inicio(): void
    {
        $alabanza = $this->ministerio('Alabanza');
        $ujieres = $this->ministerio('Ujieres');
        $persona = $this->persona();
        $persona->ministerios()->attach($alabanza->id, ['desde' => '2025-03-01']);

        $this->actingAs($this->admin)->put(route('personas.update', $persona), [
            'nombre' => 'Ana Pérez',
            'activo' => '1',
            'ministerios' => [
                ['ministerio_id' => $alabanza->id, 'es_lider' => '1'],   // sigue, ahora líder
                ['ministerio_id' => $ujieres->id],                       // nuevo
            ],
        ])->assertSessionHasNoErrors();

        $asignados = $persona->fresh()->ministerios->keyBy('id');
        $this->assertCount(2, $asignados);
        $this->assertSame('2025-03-01', substr((string) $asignados[$alabanza->id]->pivot->desde, 0, 10), 'Mantiene el desde original.');
        $this->assertTrue((bool) $asignados[$alabanza->id]->pivot->es_lider);

        // Quitar todos: el formulario sin la clave "ministerios" deja a la persona sin asignaciones.
        $this->actingAs($this->admin)->put(route('personas.update', $persona), [
            'nombre' => 'Ana Pérez',
            'activo' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $persona->fresh()->ministerios()->count());
    }

    public function test_no_se_puede_asignar_un_ministerio_de_otra_iglesia(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        $ajeno = $this->ministerio('Ajeno', [], $otra);
        $persona = $this->persona();

        $this->actingAs($this->admin)->put(route('personas.update', $persona), [
            'nombre' => 'Ana Pérez',
            'activo' => '1',
            'ministerios' => [['ministerio_id' => $ajeno->id]],
        ])->assertSessionHasErrors('ministerios.0.ministerio_id');

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'nombre' => 'Otra Persona',
            'activo' => '1',
            'ministerios' => [['ministerio_id' => $ajeno->id]],
        ])->assertSessionHasErrors('ministerios.0.ministerio_id');

        $this->assertSame(0, $persona->ministerios()->count());
        $this->assertNull(Persona::where('nombre', 'Otra Persona')->first());
    }

    public function test_el_formulario_de_crear_solo_ofrece_ministerios_activos(): void
    {
        $this->ministerio('Alabanza');
        $this->ministerio('Ministerio Cerrado', ['activo' => false]);

        $this->actingAs($this->admin)->get(route('personas.create'))
            ->assertOk()
            ->assertSee('Alabanza')
            ->assertDontSee('Ministerio Cerrado');
    }

    public function test_el_formulario_de_editar_ofrece_el_inactivo_solo_a_quien_ya_esta_en_el(): void
    {
        $cerrado = $this->ministerio('Ministerio Cerrado', ['activo' => false]);
        $this->ministerio('Otro Cerrado', ['activo' => false]);
        $this->ministerio('Alabanza');
        $persona = $this->persona();
        $persona->ministerios()->attach($cerrado->id, ['desde' => '2025-01-01']);

        $this->actingAs($this->admin)->get(route('personas.edit', $persona))
            ->assertOk()
            ->assertSee('Ministerio Cerrado (inactivo)')
            ->assertSee('Alabanza')
            ->assertDontSee('Otro Cerrado');
    }

    public function test_se_puede_guardar_la_ficha_de_alguien_asignado_a_un_ministerio_desactivado_sin_perder_la_asignacion(): void
    {
        $cerrado = $this->ministerio('Ministerio Cerrado', ['activo' => false]);
        $persona = $this->persona();
        $persona->ministerios()->attach($cerrado->id, ['desde' => '2025-01-01']);

        // Lo que enviaría el formulario: el select conserva el ministerio asignado.
        $this->actingAs($this->admin)->put(route('personas.update', $persona), [
            'nombre' => 'Ana Pérez Editada',
            'activo' => '1',
            'ministerios' => [['ministerio_id' => $cerrado->id]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Ana Pérez Editada', $persona->fresh()->nombre);
        $this->assertSame(1, $persona->fresh()->ministerios()->count());
    }
}

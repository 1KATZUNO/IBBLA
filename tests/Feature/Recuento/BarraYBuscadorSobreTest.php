<?php

namespace Tests\Feature\Recuento;

use App\Models\Culto;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * QE-014: la barra junto a Guardar decia "SOBRE DE ANÓNIMO" en rojo antes de elegir a nadie; con un
 * resultado, Flecha abajo + Enter elegia "Crear … como persona nueva"; y el buscador no mostraba PIN ni
 * telefono (hay homonimos).
 */
class BarraYBuscadorSobreTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    private User $tesorero;

    private Culto $culto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
        $this->tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $this->culto = $this->crearCulto($this->iglesia, '2026-11-01');
    }

    public function test_al_abrir_registrar_sobres_la_barra_dice_sin_persona_y_no_anonimo(): void
    {
        $this->actingAs($this->tesorero)
            ->get(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->assertOk()
            ->assertSee('data-estado="vacio"', false)
            ->assertSee('Sin persona')
            ->assertDontSee('>ANÓNIMO</span>', false);
    }

    public function test_con_anonimo_marcado_la_barra_dice_anonimo(): void
    {
        $this->actingAs($this->tesorero)
            ->withSession(['_old_input' => ['anonimo' => '1']])
            ->get(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->assertOk()
            ->assertSee('data-estado="anonimo"', false)
            ->assertSee('ANÓNIMO');
    }

    public function test_al_editar_un_sobre_anonimo_la_barra_dice_anonimo_y_uno_con_persona_su_nombre(): void
    {
        $anonimo = $this->crearSobreEn($this->culto);
        $persona = $this->crearPersonaDe($this->iglesia, 'Luis Ulate');
        $conPersona = $this->crearSobreEn($this->culto, $persona);

        $this->actingAs($this->tesorero)->get(route('recuento.edit', $anonimo))
            ->assertOk()->assertSee('data-estado="anonimo"', false);

        $this->actingAs($this->tesorero)->get(route('recuento.edit', $conPersona))
            ->assertOk()->assertSee('data-estado="persona"', false)->assertSee('Luis Ulate');
    }

    public function test_el_buscador_recibe_nombre_pin_y_telefono_de_cada_persona(): void
    {
        $a = $this->crearPersonaDe($this->iglesia, 'Ana Mora');
        $a->update(['telefono' => '8888-1111']);
        $b = $this->crearPersonaDe($this->iglesia, 'Ana Mora');
        $b->update(['telefono' => '8888-2222']);

        $respuesta = $this->actingAs($this->tesorero)
            ->get(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->assertOk();

        $personas = collect($respuesta->viewData('personas'))->where('nombre', 'Ana Mora')->values();
        $this->assertCount(2, $personas);
        $this->assertEqualsCanonicalizing(['8888-1111', '8888-2222'], $personas->pluck('telefono')->all());
        $this->assertEqualsCanonicalizing([$a->fresh()->pin, $b->fresh()->pin], $personas->pluck('pin')->all());
        foreach ($personas as $p) {
            $this->assertSame(['id', 'nombre', 'pin', 'telefono', 'inactiva'], array_keys($p));
        }
        // Cada resultado pinta PIN y telefono
        $respuesta->assertSee("'Tel. '", false)->assertSee("'PIN '", false);
    }

    public function test_las_flechas_no_llegan_a_crear_persona_nueva_cuando_hay_resultados(): void
    {
        // La logica de seleccion vive en el script del formulario: con resultados, Flecha abajo se
        // detiene en la ultima persona y "Crear … como persona nueva" queda fuera de su alcance.
        $this->actingAs($this->tesorero)
            ->get(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->assertOk()
            ->assertSee('const ultimo = resultados.length ? resultados.length - 1 : opciones.length - 1;', false)
            ->assertSee('marcar(Math.min(marcado + 1, ultimo))', false)
            ->assertDontSee('marcar(Math.min(marcado + 1, opciones.length - 1))', false);
    }
}

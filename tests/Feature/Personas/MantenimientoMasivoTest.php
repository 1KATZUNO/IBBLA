<?php

namespace Tests\Feature\Personas;

use App\Models\AuditLog;
use App\Models\Persona;
use App\Models\Promesa;
use App\Models\Sobre;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * QA-034: "Eliminar personas inactivas" y "Borrar todas las promesas" pasan por una pantalla propia que
 * dice qué y cuánto se borra y pide escribir CONFIRMAR; solo el admin; quedan en la auditoría con detalle.
 */
class MantenimientoMasivoTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    private Persona $inactiva;

    private Persona $activa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');
        $this->activa = $this->crearPersonaDe($this->iglesia, 'Ana Activa');
        $this->inactiva = $this->crearPersonaDe($this->iglesia, 'Iris Inactiva');
        $this->inactiva->update(['activo' => false]);
        foreach ([$this->activa, $this->inactiva] as $p) {
            Promesa::create(['persona_id' => $p->id, 'categoria' => 'diezmo', 'monto' => 1000, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);
        }
        $this->crearSobreEn($this->crearCulto($this->iglesia, '2026-10-04'), $this->inactiva);
    }

    public function test_personas_lleva_a_la_pantalla_de_confirmacion_y_no_usa_confirm(): void
    {
        $this->actingAs($this->admin)->get(route('personas.index'))
            ->assertOk()
            ->assertSee(route('personas.mantenimiento', 'inactivas'))
            ->assertSee(route('personas.mantenimiento', 'promesas'))
            ->assertSee('pide confirmación')
            ->assertDontSee('return confirm(', false);
    }

    public function test_la_pantalla_dice_que_y_cuanto_se_borra(): void
    {
        $this->actingAs($this->admin)->get(route('personas.mantenimiento', 'inactivas'))
            ->assertOk()
            ->assertSee('1 persona inactiva')
            ->assertSee('1 promesa')
            ->assertSee('Iris Inactiva')
            ->assertDontSee('Ana Activa')
            ->assertSee('Los sobres quedan registrados')
            ->assertSee('CONFIRMAR');

        $this->actingAs($this->admin)->get(route('personas.mantenimiento', 'promesas'))
            ->assertOk()
            ->assertSee('2 promesas de 2 personas');
    }

    public function test_sin_escribir_confirmar_no_borra_nada(): void
    {
        foreach (['', 'confirmar', 'SI'] as $texto) {
            $this->actingAs($this->admin)->from(route('personas.mantenimiento', 'inactivas'))
                ->post(route('personas.limpiar-inactivas'), ['confirmacion' => $texto])
                ->assertRedirect(route('personas.mantenimiento', 'inactivas'))
                ->assertSessionHasErrors('confirmacion');
            $this->actingAs($this->admin)->post(route('personas.resetear-promesas'), ['confirmacion' => $texto])
                ->assertSessionHasErrors('confirmacion');
        }

        $this->assertNotNull(Persona::find($this->inactiva->id));
        $this->assertSame(2, Promesa::count());
    }

    public function test_eliminar_inactivas_con_confirmar_borra_y_audita_con_detalle(): void
    {
        $this->actingAs($this->admin)->post(route('personas.limpiar-inactivas'), ['confirmacion' => 'CONFIRMAR'])
            ->assertRedirect(route('personas.index'))
            ->assertSessionHas('success');

        $this->assertNull(Persona::find($this->inactiva->id));
        $this->assertNotNull(Persona::find($this->activa->id));
        $this->assertSame(1, Sobre::whereNull('persona_id')->count(), 'El sobre queda, sin persona');

        $log = AuditLog::where('action', 'Eliminar personas inactivas')->sole();
        $detalle = json_decode($log->details, true);
        $this->assertSame(1, $detalle['cantidad_personas']);
        $this->assertSame('Iris Inactiva', $detalle['personas'][0]['nombre']);
        $this->assertSame(1, $detalle['promesas_borradas']);
        $this->assertSame(1, $detalle['sobres_sin_persona']);
        $this->assertSame(0, AuditLog::where('action', 'personas.limpiar-inactivas')->count(), 'Sin la fila genérica duplicada');
    }

    public function test_borrar_promesas_con_confirmar_borra_y_audita(): void
    {
        $this->actingAs($this->admin)->post(route('personas.resetear-promesas'), ['confirmacion' => 'CONFIRMAR'])
            ->assertRedirect(route('personas.index'));

        $this->assertSame(0, Promesa::count());
        $detalle = json_decode(AuditLog::where('action', 'Borrar todas las promesas')->sole()->details, true);
        $this->assertSame(2, $detalle['promesas_borradas']);
        $this->assertSame(2, $detalle['personas_con_promesa']);
    }

    public function test_solo_el_admin(): void
    {
        foreach (['tesorero', 'asistente', 'miembro'] as $rol) {
            $usuario = $this->crearUsuario($this->iglesia, $rol);
            $this->actingAs($usuario)->get(route('personas.mantenimiento', 'inactivas'))->assertForbidden();
            $this->actingAs($usuario)->post(route('personas.limpiar-inactivas'), ['confirmacion' => 'CONFIRMAR'])->assertForbidden();
            $this->actingAs($usuario)->post(route('personas.resetear-promesas'), ['confirmacion' => 'CONFIRMAR'])->assertForbidden();
        }

        $this->assertSame(2, Promesa::count());
        $this->assertNotNull(Persona::find($this->inactiva->id));
    }

    public function test_sin_inactivas_la_pantalla_lo_dice_y_no_ofrece_borrar(): void
    {
        $this->inactiva->update(['activo' => true]);

        $this->actingAs($this->admin)->get(route('personas.mantenimiento', 'inactivas'))
            ->assertOk()
            ->assertSee('No hay personas inactivas')
            ->assertDontSee('name="confirmacion"', false);
    }
}

<?php

namespace Tests\Feature\Recuento;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/** QA-005 / QA-009: no se cierra un culto futuro ni vacio; el modal muestra el resumen antes de cerrar. */
class CerrarCultoReglasTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
        $this->travelTo('2026-11-04 12:00:00');
    }

    public function test_no_se_cierra_un_culto_que_todavia_no_ocurre(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin');
        $culto = $this->crearCulto($this->iglesia, '2026-11-08');
        $this->crearSobreEn($culto);

        $this->actingAs($admin)->get(route('recuento.culto', $culto))
            ->assertOk()
            ->assertSee('Todavía no se puede cerrar: el culto es el 08/11/2026.');
        $this->actingAs($admin)->post(route('recuento.cerrar-culto', $culto))->assertForbidden();
        $this->assertFalse($culto->fresh()->cerrado);
    }

    public function test_no_se_cierra_un_culto_vacio(): void
    {
        $tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $culto = $this->crearCulto($this->iglesia, '2026-11-01');

        $this->actingAs($tesorero)->post(route('recuento.cerrar-culto', $culto))->assertForbidden();
        $this->assertFalse($culto->fresh()->cerrado);
    }

    public function test_el_modal_de_cierre_muestra_sobres_total_y_anonimos(): void
    {
        $tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $culto = $this->crearCulto($this->iglesia, '2026-11-01');
        $this->crearSobreEn($culto, null, ['diezmo' => 1500]);
        $this->crearSobreEn($culto, $this->crearPersonaDe($this->iglesia), ['diezmo' => 2500]);

        $this->actingAs($tesorero)->get(route('recuento.culto', $culto))
            ->assertOk()
            ->assertSee('2 sobres, total ₡4,000.00. Ojo: 1 sobre está como Anónimo.', false);

        $this->actingAs($tesorero)->post(route('recuento.cerrar-culto', $culto))
            ->assertRedirect(route('recuento.culto', $culto));
        $this->assertTrue($culto->fresh()->cerrado);
    }
}

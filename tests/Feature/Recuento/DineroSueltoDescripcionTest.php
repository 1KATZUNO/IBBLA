<?php

namespace Tests\Feature\Recuento;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/** El dinero suelto guarda la descripcion que se escribe en su ventana (antes se perdia). */
class DineroSueltoDescripcionTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    public function test_la_descripcion_del_dinero_suelto_se_guarda_y_se_ve(): void
    {
        $iglesia = $this->crearIglesia();
        $tesorero = $this->crearUsuario($iglesia, 'tesorero');
        $culto = $this->crearCulto($iglesia, '2026-10-18');

        $this->actingAs($tesorero)->post(route('recuento.store-suelto'), [
            'culto_id' => $culto->id,
            'monto' => 3500,
            'descripcion' => 'Ofrenda de la cajita de niños',
        ])->assertRedirect(route('recuento.culto', $culto));

        $suelto = $culto->ofrendasSueltas()->firstOrFail();
        $this->assertSame('Ofrenda de la cajita de niños', $suelto->descripcion);

        $this->actingAs($tesorero)->put(route('recuento.update-suelto', $suelto), [
            'monto' => 4000,
            'descripcion' => 'Cajita de niños (recontada)',
        ])->assertRedirect(route('recuento.culto', $culto));

        $this->actingAs($tesorero)->get(route('recuento.culto', $culto))
            ->assertOk()
            ->assertSee('Cajita de niños (recontada)');
    }
}

<?php

namespace Tests\Feature\Recuento;

use App\Models\Culto;
use App\Models\Egreso;
use App\Models\OfrendaSuelta;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * HU-REC-2: un culto cerrado es solo lectura para tesoreria. Ven todo, los botones salen
 * deshabilitados con el motivo ("Cerrado el dd/mm por X") y el backend rechaza cualquier
 * cambio con 403 y el mismo mensaje. El administrador si puede corregirlo.
 */
class CultoCerradoSoloLecturaTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    private User $tesorero;

    private User $admin;

    private Culto $cerrado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
        $this->tesorero = $this->crearUsuario($this->iglesia, 'tesorero', ['name' => 'Ana Tesorera']);
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');
        $this->cerrado = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true, $this->tesorero);
    }

    public function test_el_tesorero_ve_el_culto_cerrado_en_solo_lectura_con_el_motivo(): void
    {
        $sobre = $this->crearSobreEn($this->cerrado, $this->crearPersonaDe($this->iglesia, 'Luis Mora'));

        $html = $this->actingAs($this->tesorero)->get(route('recuento.culto', $this->cerrado))
            ->assertOk()
            ->assertSee('Luis Mora')
            ->assertSee('solo lectura')
            ->assertSee('Cerrado el 11/10 por Ana Tesorera', false)
            ->assertDontSee('id="form-eliminar-sobre-'.$sobre->id.'"', false)
            ->assertDontSee('id="formMovimiento"', false)
            ->assertDontSee('id="firmasForm"', false)
            ->getContent();

        // Agregar sobre, dinero suelto, egreso y las acciones de cada sobre: deshabilitadas, no ocultas
        $this->assertGreaterThanOrEqual(5, substr_count($html, 'class="accion-bloqueada"'));
        $this->assertStringContainsString('aria-disabled="true"', $html);
    }

    public function test_el_backend_rechaza_con_403_y_mensaje_cualquier_cambio_del_tesorero(): void
    {
        $sobre = $this->crearSobreEn($this->cerrado);
        $suelto = OfrendaSuelta::create(['culto_id' => $this->cerrado->id, 'monto' => 1000, 'moneda' => 'CRC', 'metodo_pago' => 'efectivo']);
        $egreso = Egreso::create(['culto_id' => $this->cerrado->id, 'monto' => 500, 'moneda' => 'CRC']);
        $comoTesorero = $this->actingAs($this->tesorero);

        $intentos = [
            ['get', route('recuento.create', ['culto_id' => $this->cerrado->id]), []],
            ['post', route('recuento.store'), ['culto_id' => $this->cerrado->id, 'metodo_pago' => 'efectivo', 'detalles' => [['categoria' => 'diezmo', 'monto' => 100]]]],
            ['get', route('recuento.edit', $sobre), []],
            ['put', route('recuento.update', $sobre), ['metodo_pago' => 'efectivo', 'detalles' => [['categoria' => 'diezmo', 'monto' => 1]]]],
            ['delete', route('recuento.destroy', $sobre), []],
            ['post', route('recuento.store-suelto'), ['culto_id' => $this->cerrado->id, 'monto' => 100]],
            ['put', route('recuento.update-suelto', $suelto), ['monto' => 1]],
            ['delete', route('recuento.destroy-suelto', $suelto), []],
            ['post', route('recuento.store-egreso'), ['culto_id' => $this->cerrado->id, 'monto' => 100]],
            ['put', route('recuento.update-egreso', $egreso), ['monto' => 1]],
            ['delete', route('recuento.destroy-egreso', $egreso), []],
            ['post', route('recuento.firmas.update', $this->cerrado), ['firma_pastor' => 'Otro']],
        ];

        foreach ($intentos as [$metodo, $url, $datos]) {
            $comoTesorero->{$metodo}($url, $datos)
                ->assertForbidden()
                ->assertSee('Cerrado el 11/10 por Ana Tesorera', false);
        }

        // Nada cambio
        $this->assertSame(1, $this->cerrado->sobres()->count());
        $this->assertSame('1000.00', (string) $suelto->fresh()->monto);
        $this->assertNotNull($egreso->fresh());
        $this->assertNull($this->cerrado->fresh()->firma_pastor);
    }

    public function test_la_respuesta_json_tambien_trae_el_motivo(): void
    {
        $this->actingAs($this->tesorero)
            ->postJson(route('recuento.store'), ['culto_id' => $this->cerrado->id, 'metodo_pago' => 'efectivo', 'detalles' => [['categoria' => 'diezmo', 'monto' => 100]]])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'Cerrado el 11/10 por Ana Tesorera. Solo lectura: si hay que corregir algo, pídele al administrador que lo corrija o que te dé acceso de edición.']);
    }

    public function test_el_admin_si_corrige_un_culto_cerrado(): void
    {
        $sobre = $this->crearSobreEn($this->cerrado, null, ['diezmo' => 10000]);
        $comoAdmin = $this->actingAs($this->admin);

        $comoAdmin->get(route('recuento.culto', $this->cerrado))
            ->assertOk()
            ->assertSee('lo estás corrigiendo como administrador')
            ->assertDontSee('class="accion-bloqueada"', false);

        $comoAdmin->put(route('recuento.update', $sobre), [
            'metodo_pago' => 'efectivo',
            'detalles' => [['categoria' => 'diezmo', 'monto' => 12000]],
        ])->assertRedirect(route('recuento.culto', $this->cerrado));

        $comoAdmin->post(route('recuento.store'), [
            'culto_id' => $this->cerrado->id,
            'persona_id' => $this->crearPersonaDe($this->iglesia, 'Rosa Vargas')->id,
            'metodo_pago' => 'efectivo',
            'detalles' => [['categoria' => 'misiones', 'monto' => 3000]],
        ])->assertRedirect(route('recuento.culto', $this->cerrado));

        $this->assertSame(2, $this->cerrado->sobres()->count());
        $this->assertEquals(15000, (float) $this->cerrado->fresh()->totales->total_general);
    }

    public function test_el_tesorero_cierra_un_culto_abierto_y_queda_su_nombre(): void
    {
        $abierto = $this->crearCulto($this->iglesia, '2026-10-18');
        $this->crearSobreEn($abierto);

        $this->actingAs($this->tesorero)->post(route('recuento.cerrar-culto', $abierto))
            ->assertRedirect(route('recuento.culto', $abierto));

        $abierto->refresh();
        $this->assertTrue($abierto->cerrado);
        $this->assertSame($this->tesorero->id, (int) $abierto->cerrado_por);

        // Desde ese momento es solo lectura para tesoreria
        $this->actingAs($this->tesorero)->post(route('recuento.store'), [
            'culto_id' => $abierto->id,
            'metodo_pago' => 'efectivo',
            'detalles' => [['categoria' => 'diezmo', 'monto' => 100]],
        ])->assertForbidden();
    }

    public function test_en_un_culto_abierto_el_tesorero_registra_sin_problema(): void
    {
        $abierto = $this->crearCulto($this->iglesia, '2026-10-18');

        $this->actingAs($this->tesorero)->get(route('recuento.culto', $abierto))
            ->assertOk()
            ->assertDontSee('class="accion-bloqueada"', false)
            ->assertSee('id="formMovimiento"', false);

        $this->actingAs($this->tesorero)->post(route('recuento.store-suelto'), ['culto_id' => $abierto->id, 'monto' => 2500])
            ->assertRedirect(route('recuento.culto', $abierto));
        $this->assertSame(1, $abierto->ofrendasSueltas()->count());
    }

    public function test_no_se_toca_un_sobre_de_otra_iglesia(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        $ajeno = $this->crearSobreEn($this->crearCulto($otra, '2026-10-18'));

        $this->actingAs($this->admin)->get(route('recuento.edit', $ajeno))->assertNotFound();
        $this->actingAs($this->admin)->delete(route('recuento.destroy', $ajeno))->assertNotFound();
        $this->assertNotNull($ajeno->fresh());
    }

    public function test_sin_culto_el_formulario_de_sobre_vuelve_a_la_lista(): void
    {
        $this->actingAs($this->tesorero)->get(route('recuento.create'))
            ->assertRedirect(route('recuento.index'));
    }
}

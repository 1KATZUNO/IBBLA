<?php

namespace Tests\Feature\Recuento;

use App\Models\Culto;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * HU-REC-4: registrar sobres seguidos sin ruido (docs/plan-oct-2026/FLUJO-SOBRES.md):
 * solo el culto en cuestion, "Guardar y siguiente" vuelve al formulario del mismo culto,
 * los anonimos seguidos se guardan, el doble envio del mismo formulario guarda uno solo y un
 * sobre en cero no se guarda.
 */
class RegistrarSobresSeguidosTest extends TestCase
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
        $this->culto = $this->crearCulto($this->iglesia, '2026-10-18');
    }

    private function sobre(array $datos = []): array
    {
        return $datos + [
            'culto_id' => $this->culto->id,
            'metodo_pago' => 'efectivo',
            'moneda' => 'CRC',
            'envio' => (string) Str::uuid(),
            'detalles' => [
                ['categoria' => 'diezmo', 'monto' => '15000'],
                ['categoria' => 'misiones', 'monto' => ''],
            ],
        ];
    }

    public function test_el_formulario_solo_muestra_el_culto_en_cuestion_y_su_avance(): void
    {
        $otro = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true);
        $this->crearSobreEn($otro, $this->crearPersonaDe($this->iglesia, 'Hermano de Otro Culto'));
        $ultimo = $this->crearSobreEn($this->culto, $this->crearPersonaDe($this->iglesia, 'Marta Solano'), ['diezmo' => 7000]);

        $html = $this->actingAs($this->tesorero)->get(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->assertOk()
            ->assertSee('18/10/2026')
            ->assertSee('Último guardado:')
            ->assertSee('Marta Solano')
            ->assertSee(route('recuento.edit', $ultimo), false)
            ->assertSee('Guardar y siguiente')
            ->assertSee('name="envio"', false)
            ->assertSee('inputmode="decimal"', false)
            ->assertDontSee('11/10/2026')
            ->getContent();

        // La persona se elige con el buscador: ya no hay un selector con todas las personas
        $this->assertStringNotContainsString('<select name="persona_id"', $html);
        $this->assertStringContainsString('id="buscarPersona"', $html);
    }

    public function test_guardar_y_siguiente_vuelve_al_formulario_del_mismo_culto(): void
    {
        $persona = $this->crearPersonaDe($this->iglesia, 'Rosa Vargas');

        $this->actingAs($this->tesorero)
            ->post(route('recuento.store'), $this->sobre(['persona_id' => $persona->id, 'siguiente' => '1']))
            ->assertRedirect(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->assertSessionHas('success', 'Sobre #1 guardado: Rosa Vargas, ₡15,000.00.');

        $sobre = $this->culto->sobres()->with('detalles')->firstOrFail();
        $this->assertEquals(15000, (float) $sobre->total_declarado);
        // El monto vacio no crea un detalle en cero
        $this->assertSame(['diezmo'], $sobre->detalles->pluck('categoria')->all());
    }

    public function test_guardar_y_terminar_vuelve_a_la_pantalla_del_culto(): void
    {
        $this->actingAs($this->tesorero)
            ->post(route('recuento.store'), $this->sobre(['siguiente' => '0']))
            ->assertRedirect(route('recuento.culto', $this->culto));
    }

    public function test_dos_sobres_anonimos_seguidos_se_guardan_los_dos(): void
    {
        $this->actingAs($this->tesorero)->post(route('recuento.store'), $this->sobre(['siguiente' => '1']))->assertSessionHas('success');
        $this->actingAs($this->tesorero)->post(route('recuento.store'), $this->sobre(['siguiente' => '1']))->assertSessionHas('success');

        $this->assertSame(2, $this->culto->sobres()->whereNull('persona_id')->count());
    }

    public function test_el_mismo_formulario_enviado_dos_veces_guarda_un_solo_sobre(): void
    {
        $datos = $this->sobre(['siguiente' => '1']);

        $this->actingAs($this->tesorero)->post(route('recuento.store'), $datos)->assertSessionHas('success');
        $this->actingAs($this->tesorero)->post(route('recuento.store'), $datos)
            ->assertRedirect(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->assertSessionHas('warning');

        $this->assertSame(1, $this->culto->sobres()->count());
    }

    public function test_sin_codigo_de_envio_sigue_la_regla_vieja_de_30_segundos(): void
    {
        $persona = $this->crearPersonaDe($this->iglesia);
        $sinCodigo = fn () => $this->sobre(['persona_id' => $persona->id, 'envio' => null]);

        $this->actingAs($this->tesorero)->post(route('recuento.store'), $sinCodigo())->assertSessionHas('success');
        $this->actingAs($this->tesorero)->post(route('recuento.store'), $sinCodigo())->assertSessionHas('warning');

        $this->assertSame(1, $this->culto->sobres()->count());
    }

    public function test_un_sobre_en_cero_no_se_guarda(): void
    {
        $this->actingAs($this->tesorero)
            ->from(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->post(route('recuento.store'), $this->sobre(['detalles' => [
                ['categoria' => 'diezmo', 'monto' => ''],
                ['categoria' => 'misiones', 'monto' => '0'],
            ]]))
            ->assertRedirect(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->assertSessionHasErrors(['detalles' => 'Escribe al menos un monto mayor que cero.']);

        $this->assertSame(0, $this->culto->sobres()->count());
    }

    public function test_transferencia_pide_comprobante(): void
    {
        $this->actingAs($this->tesorero)
            ->post(route('recuento.store'), $this->sobre(['metodo_pago' => 'transferencia']))
            ->assertSessionHasErrors('comprobante_numero');

        $this->actingAs($this->tesorero)
            ->post(route('recuento.store'), $this->sobre(['metodo_pago' => 'transferencia', 'comprobante_numero' => 'TR-123']))
            ->assertSessionHas('success');
        $this->assertSame('TR-123', $this->culto->sobres()->firstOrFail()->comprobante_numero);
    }

    public function test_editar_usa_el_mismo_formulario_y_vuelve_al_culto(): void
    {
        $persona = $this->crearPersonaDe($this->iglesia, 'Luis Mora');
        $sobre = $this->crearSobreEn($this->culto, $persona, ['diezmo' => 10000]);

        $this->actingAs($this->tesorero)->get(route('recuento.edit', $sobre))
            ->assertOk()
            ->assertSee('Sobre de', false)
            ->assertSee('Luis Mora')
            ->assertSee('value="10000.00"', false)
            ->assertSee('Guardar cambios');

        $this->actingAs($this->tesorero)->put(route('recuento.update', $sobre), [
            'persona_id' => $persona->id,
            'metodo_pago' => 'efectivo',
            'detalles' => [['categoria' => 'diezmo', 'monto' => '12000'], ['categoria' => 'misiones', 'monto' => '500']],
        ])->assertRedirect(route('recuento.culto', $this->culto));

        $this->assertEquals(12500, (float) $sobre->fresh()->total_declarado);
        $this->assertSame(2, $sobre->detalles()->count());
    }
}

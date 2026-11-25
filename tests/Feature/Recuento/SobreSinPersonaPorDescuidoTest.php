<?php

namespace Tests\Feature\Recuento;

use App\Models\AuditLog;
use App\Models\Culto;
use App\Models\Sobre;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * Pedido del cliente (04/11): sobres que quedaban "Anonimo" aunque el tesorero habia escrito el
 * nombre. Causa: el buscador mostraba el nombre pero la persona solo quedaba elegida al tocar la
 * sugerencia; si se escribia el nombre y se seguia (Tab, clic en un monto o Enter en el viejo
 * formulario) el sobre se guardaba sin persona y sin aviso.
 */
class SobreSinPersonaPorDescuidoTest extends TestCase
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

    private function sobre(array $datos = []): array
    {
        return $datos + [
            'culto_id' => $this->culto->id,
            'metodo_pago' => 'efectivo',
            'moneda' => 'CRC',
            'envio' => (string) Str::uuid(),
            'detalles' => [['categoria' => 'diezmo', 'monto' => '5000']],
        ];
    }

    public function test_sin_persona_y_sin_marcar_anonimo_no_se_guarda(): void
    {
        $this->actingAs($this->tesorero)
            ->from(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->post(route('recuento.store'), $this->sobre())
            ->assertSessionHasErrors('persona_id');

        $this->assertSame(0, Sobre::count());
    }

    public function test_anonimo_marcado_a_proposito_si_se_guarda(): void
    {
        $this->actingAs($this->tesorero)
            ->post(route('recuento.store'), $this->sobre(['anonimo' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Sobre::firstOrFail()->persona_id);
    }

    public function test_nombre_escrito_sin_elegir_la_sugerencia_queda_con_su_persona(): void
    {
        $persona = $this->crearPersonaDe($this->iglesia, 'José Ángel Mora');

        // Escribio el nombre (sin tildes y con otras mayusculas) pero nunca toco la sugerencia
        $this->actingAs($this->tesorero)
            ->post(route('recuento.store'), $this->sobre(['persona_nombre' => '  jose angel   MORA ']))
            ->assertSessionHasNoErrors();

        $this->assertSame($persona->id, Sobre::firstOrFail()->persona_id);
    }

    public function test_nombre_que_no_coincide_con_nadie_no_se_guarda_como_anonimo(): void
    {
        $this->crearPersonaDe($this->iglesia, 'Ana Mora');

        $this->actingAs($this->tesorero)
            ->from(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->post(route('recuento.store'), $this->sobre(['persona_nombre' => 'Ana Morales', 'anonimo' => '1']))
            ->assertSessionHasErrors(['persona_id' => 'No hay ninguna persona llamada «Ana Morales». Elígela de la lista o agrégala con «+ Nueva persona»; el sobre no se guardó.']);

        $this->assertSame(0, Sobre::count());
    }

    public function test_nombre_repetido_pide_elegir_y_no_adivina(): void
    {
        $this->crearPersonaDe($this->iglesia, 'Luis Rojas');
        $this->crearPersonaDe($this->iglesia, 'Luis Rojas');

        $this->actingAs($this->tesorero)
            ->post(route('recuento.store'), $this->sobre(['persona_nombre' => 'Luis Rojas']))
            ->assertSessionHasErrors('persona_id');

        $this->assertSame(0, Sobre::count());
    }

    public function test_tras_un_error_de_validacion_el_formulario_conserva_la_persona_elegida(): void
    {
        $persona = $this->crearPersonaDe($this->iglesia, 'Marta Solano');

        // Transferencia sin comprobante: falla, y al volver la persona sigue elegida (no Anonimo)
        $this->actingAs($this->tesorero)
            ->from(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->post(route('recuento.store'), $this->sobre(['persona_id' => $persona->id, 'persona_nombre' => 'Marta Solano', 'metodo_pago' => 'transferencia']))
            ->assertSessionHasErrors('comprobante_numero');

        $html = $this->actingAs($this->tesorero)
            ->get(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="persona_id" value="'.$persona->id.'"', $html);
        $this->assertMatchesRegularExpression('/id="barraPersona"[^>]*>Marta Solano</', $html);
    }

    public function test_la_barra_fija_dice_sin_persona_hasta_marcar_anonimo_y_la_casilla_explicita(): void
    {
        $html = $this->actingAs($this->tesorero)
            ->get(route('recuento.create', ['culto_id' => $this->culto->id]))
            ->assertOk()
            ->assertSee('Sobre anónimo (sin persona)')
            ->assertSee('name="anonimo"', false)
            ->assertSee('name="persona_nombre"', false)
            ->getContent();

        // QE-014: "ANÓNIMO" en rojo solo cuando se marca la casilla; antes dice "Sin persona"
        $this->assertMatchesRegularExpression('/id="barraPersona"[^>]*text-gray-400[^>]*>Sin persona</u', $html);
    }

    public function test_editar_un_sobre_anonimo_conserva_la_casilla_marcada(): void
    {
        $sobre = $this->crearSobreEn($this->culto);

        $html = $this->actingAs($this->tesorero)
            ->get(route('recuento.edit', $sobre))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/id="chkAnonimo"[^>]*checked/', $html);
    }

    public function test_alta_rapida_del_tesorero_con_telefono_y_auditada(): void
    {
        $this->actingAs($this->tesorero)
            ->postJson(route('personas.quick-store'), ['nombre' => 'Persona Nueva', 'telefono' => '8888-1111'])
            ->assertOk()
            ->assertJsonPath('persona.nombre', 'Persona Nueva')
            ->assertJsonPath('persona.telefono', '8888-1111');

        $this->assertTrue(AuditLog::where('action', 'Alta rápida de persona')->exists());
    }
}

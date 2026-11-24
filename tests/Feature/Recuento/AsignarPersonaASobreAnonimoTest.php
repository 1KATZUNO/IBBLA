<?php

namespace Tests\Feature\Recuento;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/** Corregir los sobres que ya quedaron Anonimo: etiqueta, filtro "Solo anonimos" y "Asignar persona". */
class AsignarPersonaASobreAnonimoTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
    }

    public function test_los_anonimos_se_marcan_y_se_pueden_ver_solos(): void
    {
        $tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $culto = $this->crearCulto($this->iglesia, '2026-11-01');
        $this->crearSobreEn($culto, $this->crearPersonaDe($this->iglesia, 'Rosa Vargas'));
        $this->crearSobreEn($culto);

        $this->actingAs($tesorero)->get(route('recuento.culto', $culto))
            ->assertOk()
            ->assertSee('Rosa Vargas')
            ->assertSee('data-sobre-anonimo', false)
            ->assertSee('Solo anónimos')
            ->assertSee('Asignar persona');

        $this->actingAs($tesorero)->get(route('recuento.culto', ['culto' => $culto->id, 'anonimos' => 1]))
            ->assertOk()
            ->assertDontSee('rosa vargas"', false) // la fila (data-buscar); el nombre sigue en la lista para asignar
            ->assertSee('data-sobre-anonimo', false);
    }

    public function test_asignar_persona_a_un_anonimo_queda_auditado(): void
    {
        $tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $culto = $this->crearCulto($this->iglesia, '2026-11-01');
        $persona = $this->crearPersonaDe($this->iglesia, 'Rosa Vargas');
        $sobre = $this->crearSobreEn($culto);

        $this->actingAs($tesorero)
            ->patch(route('recuento.asignar-persona', $sobre), ['persona_nombre' => 'rosa vargas'])
            ->assertRedirect(route('recuento.culto', $culto))
            ->assertSessionHas('success');

        $this->assertSame($persona->id, $sobre->fresh()->persona_id);
        $log = AuditLog::where('action', 'Asignar persona a sobre anónimo')->firstOrFail();
        $this->assertSame($tesorero->id, $log->user_id);
        $this->assertStringContainsString('Rosa Vargas', $log->details);
    }

    public function test_nombre_desconocido_no_cambia_nada(): void
    {
        $tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $culto = $this->crearCulto($this->iglesia, '2026-11-01');
        $sobre = $this->crearSobreEn($culto);

        $this->actingAs($tesorero)
            ->patch(route('recuento.asignar-persona', $sobre), ['persona_nombre' => 'Nadie Conocido'])
            ->assertSessionHas('error');

        $this->assertNull($sobre->fresh()->persona_id);
    }

    public function test_en_culto_cerrado_el_tesorero_no_asigna_y_el_admin_si(): void
    {
        $tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $admin = $this->crearUsuario($this->iglesia, 'admin');
        $culto = $this->crearCulto($this->iglesia, '2026-10-25', 'domingo', true, $admin);
        $this->crearPersonaDe($this->iglesia, 'Rosa Vargas');
        $sobre = $this->crearSobreEn($culto);

        $this->actingAs($tesorero)->get(route('recuento.culto', $culto))->assertDontSee('Asignar persona');
        $this->actingAs($tesorero)
            ->patch(route('recuento.asignar-persona', $sobre), ['persona_nombre' => 'Rosa Vargas'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('recuento.asignar-persona', $sobre), ['persona_nombre' => 'Rosa Vargas'])
            ->assertSessionHas('success');
        $this->assertNotNull($sobre->fresh()->persona_id);
    }
}

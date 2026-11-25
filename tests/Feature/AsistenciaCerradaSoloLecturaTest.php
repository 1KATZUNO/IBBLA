<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\AuditLog;
use App\Models\ClaseAsistencia;
use App\Models\Culto;
use App\Services\PermisosEdicionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioAsistencia;
use Tests\TestCase;

/**
 * QE-015 / QA-021: una asistencia cerrada abria el formulario y rebotaba al guardar, incluso para el
 * admin. Ahora sigue la regla de Recuento: el asistente la ve en gris con el motivo (sin formulario);
 * el admin la corrige con aviso y la correccion queda en la auditoria (Accesos > Ultimos cambios).
 */
class AsistenciaCerradaSoloLecturaTest extends TestCase
{
    use CreaEscenarioAsistencia;
    use RefreshDatabase;

    private ClaseAsistencia $clase;

    private Culto $culto;

    private Asistencia $asistencia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearIglesiaConAdmin();
        $this->clase = $this->crearClase('Clase 2-6 Años', 'clase_2_6');
        $this->culto = $this->crearCulto('2026-11-01');
        $this->asistencia = Asistencia::create([
            'tenant_id' => $this->culto->tenant_id,
            'culto_id' => $this->culto->id,
            'chapel_adultos_hombres' => 10,
            'chapel_adultos_mujeres' => 12,
            'total_asistencia' => 22,
            'cerrado' => true,
            'cerrado_at' => '2026-11-01 18:30:00',
            'cerrado_por' => $this->admin->id,
        ]);
    }

    private function payload(array $cambios = []): array
    {
        $payload = [
            'chapel_adultos_hombres' => 10,
            'chapel_adultos_mujeres' => 12,
            'total_asistencia' => 22,
            'clase' => [$this->clase->id => ['hombres' => 0, 'mujeres' => 0]],
        ];
        foreach (['salvos', 'bautismos', 'visitas'] as $grupo) {
            foreach (['adulto_hombre', 'adulto_mujer', 'joven_hombre', 'joven_mujer', 'nino', 'nina'] as $sub) {
                $payload["{$grupo}_{$sub}"] = 0;
            }
        }

        return array_replace($payload, $cambios);
    }

    public function test_el_asistente_ve_la_asistencia_cerrada_en_solo_lectura_con_el_motivo(): void
    {
        $asistente = $this->crearUsuarioConRol('asistente');

        $this->actingAs($asistente)
            ->get(route('asistencia.edit', $this->asistencia))
            ->assertOk()
            ->assertSee('data-aviso-asistencia="lectura"', false)
            ->assertSee('Cerrada el 01/11/2026 a las 18:30 por '.$this->admin->name)
            ->assertSee('solo lectura')
            ->assertDontSee('id="asistenciaForm"', false)
            ->assertDontSee(route('asistencia.update', $this->asistencia), false);
    }

    public function test_el_asistente_no_puede_guardar_cambios_en_una_asistencia_cerrada(): void
    {
        $asistente = $this->crearUsuarioConRol('asistente');

        $this->actingAs($asistente)
            ->put(route('asistencia.update', $this->asistencia), $this->payload(['chapel_adultos_hombres' => 50, 'total_asistencia' => 62]))
            ->assertForbidden();

        $this->assertSame(10, (int) $this->asistencia->fresh()->chapel_adultos_hombres);
        $this->assertSame(0, AuditLog::where('action', PermisosEdicionService::ACCION_CORRECCION)->count());
    }

    public function test_el_asistente_no_ve_corregir_en_el_detalle(): void
    {
        $asistente = $this->crearUsuarioConRol('asistente');

        $this->actingAs($asistente)
            ->get(route('asistencia.show', $this->asistencia))
            ->assertOk()
            ->assertSee('solo lectura')
            ->assertDontSee('>Corregir', false);
    }

    public function test_el_admin_corrige_con_aviso_y_queda_en_la_auditoria(): void
    {
        $this->actingAs($this->admin)
            ->get(route('asistencia.edit', $this->asistencia))
            ->assertOk()
            ->assertSee('id="asistenciaForm"', false)
            ->assertSee('data-aviso-asistencia="admin"', false)
            ->assertSee('la estás corrigiendo como administrador')
            ->assertSee('Guardar corrección');

        $this->actingAs($this->admin)
            ->put(route('asistencia.update', $this->asistencia), $this->payload(['chapel_adultos_hombres' => 15, 'total_asistencia' => 27]))
            ->assertRedirect(route('asistencia.show', $this->asistencia))
            ->assertSessionHas('success');

        $asistencia = $this->asistencia->fresh();
        $this->assertSame(15, (int) $asistencia->chapel_adultos_hombres);
        $this->assertTrue($asistencia->cerrado, 'Corregir no reabre la asistencia');

        $log = AuditLog::where('action', PermisosEdicionService::ACCION_CORRECCION)->sole();
        $detalles = json_decode($log->details, true);
        $this->assertSame('Corregir asistencia cerrada del culto del 01/11/2026', $detalles['accion']);
        $this->assertSame(10, (int) $detalles['antes']['cantidades']['chapel_adultos_hombres']);
        $this->assertSame(15, (int) $detalles['despues']['cantidades']['chapel_adultos_hombres']);

        // Sale en Accesos > Ultimos cambios
        $this->assertCount(1, app(PermisosEdicionService::class)->ultimosCambiosEnCerrados($this->admin->tenant_id));
    }

    public function test_el_admin_ve_corregir_en_el_detalle_y_editar_una_abierta_no_audita_correccion(): void
    {
        $this->actingAs($this->admin)
            ->get(route('asistencia.show', $this->asistencia))
            ->assertOk()
            ->assertSee('Corregir');

        $this->asistencia->update(['cerrado' => false, 'cerrado_at' => null, 'cerrado_por' => null]);
        $this->actingAs($this->admin)
            ->put(route('asistencia.update', $this->asistencia), $this->payload(['chapel_adultos_hombres' => 11, 'total_asistencia' => 23]))
            ->assertRedirect(route('asistencia.index'));
        $this->assertSame(0, AuditLog::where('action', PermisosEdicionService::ACCION_CORRECCION)->count());
    }

    public function test_cerrar_la_asistencia_no_cierra_el_culto(): void
    {
        $this->asistencia->update(['cerrado' => false, 'cerrado_at' => null, 'cerrado_por' => null]);

        $this->actingAs($this->admin)->post(route('asistencia.cerrar', $this->asistencia))->assertRedirect();

        $this->assertTrue($this->asistencia->fresh()->cerrado);
        $this->assertFalse((bool) $this->culto->fresh()->cerrado);
    }
}

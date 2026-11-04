<?php

namespace Tests\Feature\Recuento;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * Decision del cliente (13/10): las firmas del recuento se corrigen despues de firmadas, incluso con
 * el culto cerrado, por admin y tesoreria, con auditoria de quien cambio que firma y cuando.
 */
class FirmasCorregiblesTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
    }

    public function test_el_tesorero_corrige_firmas_de_un_culto_cerrado_y_queda_auditado(): void
    {
        $tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $culto = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true, $tesorero);
        $culto->update([
            'firma_pastor' => 'Pastor Juan',
            'firmas_tesoreros' => ['Ana'],
            'firmas_tesoreros_imagenes' => [['nombre' => 'Ana', 'imagen' => 'data:image/png;base64,AAA']],
        ]);

        $this->actingAs($tesorero)->post(route('recuento.firmas.update', $culto), [
            'firma_pastor' => 'Pastor Juan Pérez',
            'firmas_tesoreros' => [['nombre' => 'Ana', 'imagen' => 'data:image/png;base64,AAA']],
        ])->assertRedirect(route('recuento.culto', $culto))->assertSessionHas('success');

        $this->assertSame('Pastor Juan Pérez', $culto->fresh()->firma_pastor);

        $log = AuditLog::where('action', 'Corregir firmas del recuento')->firstOrFail();
        $detalles = json_decode($log->details, true);
        $this->assertSame($tesorero->id, $log->user_id);
        $this->assertTrue($detalles['culto_cerrado']);
        $this->assertCount(1, $detalles['cambios']);
        $this->assertSame('Pastor', $detalles['cambios'][0]['firma']);
        $this->assertStringContainsString('Pastor Juan', $detalles['cambios'][0]['antes']);
        $this->assertStringContainsString('Pastor Juan Pérez', $detalles['cambios'][0]['despues']);
        // Las imagenes no van completas a la auditoria
        $this->assertStringNotContainsString('base64', $log->details);
    }

    public function test_guardar_sin_cambios_no_ensucia_la_auditoria(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin');
        $culto = $this->crearCulto($this->iglesia, '2026-10-11');
        $culto->update(['firma_pastor' => 'Pastor Juan']);

        $this->actingAs($admin)->post(route('recuento.firmas.update', $culto), ['firma_pastor' => 'Pastor Juan'])
            ->assertSessionHas('success', 'Las firmas no cambiaron.');

        $this->assertSame(0, AuditLog::where('route', 'recuento.firmas.update')->count());
    }

    public function test_un_asistente_no_firma(): void
    {
        $asistente = $this->crearUsuario($this->iglesia, 'asistente');
        $culto = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true);

        $this->actingAs($asistente)->post(route('recuento.firmas.update', $culto), ['firma_pastor' => 'X'])
            ->assertForbidden();
    }
}

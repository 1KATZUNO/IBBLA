<?php

namespace Tests\Feature\Recuento;

use App\Models\AuditLog;
use App\Models\Culto;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * QA-003 y decision del cliente (13/10): un culto cerrado (o con recuento) solo lo borra el admin,
 * con confirmacion y motivo, y la auditoria guarda culto, fecha, total y cuantos sobres.
 * El tesorero no ve el boton.
 */
class EliminarCultoCerradoTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
    }

    public function test_culto_cerrado_sin_motivo_no_se_borra(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin');
        $culto = $this->crearCulto($this->iglesia, '2026-10-13', 'domingo', true, $admin);
        $this->crearSobreEn($culto, null, ['diezmo' => 25000]);

        $this->actingAs($admin)->delete(route('cultos.destroy', $culto))
            ->assertSessionHasErrors(['motivo', 'confirmo']);

        $this->assertNotNull(Culto::find($culto->id));
        $this->assertSame(1, $culto->sobres()->count());
    }

    public function test_admin_borra_culto_cerrado_con_motivo_y_queda_auditado(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin');
        $culto = $this->crearCulto($this->iglesia, '2026-10-13', 'domingo', true, $admin);
        $this->crearSobreEn($culto, $this->crearPersonaDe($this->iglesia, 'Rosa Vargas'), ['diezmo' => 25000]);
        app(\App\Services\CalculoTotalesCultoService::class)->recalcular($culto);

        $this->actingAs($admin)->delete(route('cultos.destroy', $culto), [
            'motivo' => 'Culto duplicado por error al crearlo',
            'confirmo' => '1',
        ])->assertRedirect(route('cultos.index'));

        $this->assertNull(Culto::find($culto->id));

        $log = AuditLog::where('action', 'Eliminar culto')->firstOrFail();
        $detalles = json_decode($log->details, true);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('2026-10-13', $detalles['fecha']);
        $this->assertSame(1, $detalles['cantidad_sobres']);
        $this->assertEquals(25000, $detalles['total_general']);
        $this->assertTrue($detalles['cerrado']);
        $this->assertSame('Culto duplicado por error al crearlo', $detalles['motivo']);
        $this->assertSame('Rosa Vargas', $detalles['sobres'][0]['persona']);
    }

    public function test_culto_abierto_vacio_se_borra_sin_motivo(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin');
        $culto = $this->crearCulto($this->iglesia, '2026-12-20');

        $this->actingAs($admin)->delete(route('cultos.destroy', $culto))->assertRedirect(route('cultos.index'));

        $this->assertNull(Culto::find($culto->id));
        $this->assertTrue(AuditLog::where('action', 'Eliminar culto')->exists());
    }

    public function test_el_tesorero_no_puede_borrar_ni_ve_el_boton(): void
    {
        $tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $culto = $this->crearCulto($this->iglesia, '2026-10-13', 'domingo', true);

        $this->actingAs($tesorero)->delete(route('cultos.destroy', $culto), ['motivo' => 'Quiero borrarlo ya', 'confirmo' => '1'])
            ->assertForbidden();
        $this->assertNotNull(Culto::find($culto->id));

        $this->actingAs($tesorero)->get(route('recuento.culto', $culto))
            ->assertOk()
            ->assertDontSee(route('cultos.destroy', $culto), false);
    }

    public function test_la_lista_de_cultos_del_admin_pide_motivo_para_cultos_con_recuento(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin');
        $culto = $this->crearCulto($this->iglesia, '2026-10-13', 'domingo', true, $admin);

        $this->actingAs($admin)->get(route('cultos.index'))
            ->assertOk()
            ->assertSee('data-eliminar-culto="'.route('cultos.destroy', $culto).'"', false)
            ->assertSee('data-con-datos="1"', false)
            ->assertSee('Motivo (obligatorio)');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Compromiso;
use App\Models\Ministerio;
use App\Services\Reportes\FiltroPersonas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\TestCase;

/**
 * Correcciones del QA de entrega de la E1 (docs/plan-oct-2026/QA-ENTREGA.md): QE-001, QE-002,
 * QE-004, QE-005, QE-006 y QE-010. Escenario de reportes con "hoy" = 15/10/2026.
 */
class CorreccionesQaEntregaTest extends TestCase
{
    use CreaEscenarioReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->crearEscenarioReportes();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function dadoMisionesOctubreDeAna(): float
    {
        return (float) Compromiso::where(['persona_id' => $this->ana->id, 'categoria' => 'misiones', 'año' => 2026, 'mes' => 10])->value('monto_dado_crc');
    }

    /** QE-004: las altas las hace el admin; el registro publico no existe. */
    public function test_el_registro_publico_ya_no_existe(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Intruso', 'email' => 'intruso@ibbsc.com', 'password' => 'secreto123', 'password_confirmation' => 'secreto123',
        ])->assertNotFound();
    }

    /** QE-002: el Excel de promesas es el del cumplimiento, con corte al fin del periodo pedido y nunca despues de hoy. */
    public function test_el_excel_de_promesas_sale_del_motor_con_corte_a_meses_completos(): void
    {
        $destino = fn (string $corte) => route('reportes.cumplimiento.excel', ['corte' => $corte, 'grupo' => FiltroPersonas::CON_PROMESA]);

        $this->actingAs($this->admin)->get(route('ingresos-asistencia.excel-promesas', ['año' => 2026]))->assertRedirect($destino('2026-10-15'));
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.excel-promesas', ['año' => 2026, 'mes' => 9]))->assertRedirect($destino('2026-09-30'));
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.excel-promesas', ['año' => 2025]))->assertRedirect($destino('2025-12-31'));
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.excel-promesas'))->assertRedirect($destino('2026-10-15'));

        $this->actingAs($this->crearUsuarioConRol('tesorero'))->get($destino('2026-09-30'))->assertOk();
        $this->actingAs($this->crearUsuarioConRol('asistente'))->get(route('ingresos-asistencia.excel-promesas', ['año' => 2026]))->assertForbidden();
    }

    /** QE-005: un filtro sin personas no puede dar error 500 al pedir el ZIP. */
    public function test_el_zip_de_estados_de_cuenta_sin_personas_vuelve_con_aviso(): void
    {
        $vacio = Ministerio::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Sin integrantes', 'slug' => 'sin-integrantes', 'activo' => true, 'orden' => 99]);
        $parametros = ['grupo' => FiltroPersonas::MINISTERIO, 'ministerio' => $vacio->id];

        $this->actingAs($this->admin)
            ->get(route('reportes.estados-cuenta.zip', $parametros))
            ->assertRedirect(route('reportes.estados-cuenta', $parametros))
            ->assertSessionHas('error');
    }

    /** QE-006: borrar un culto cerrado saca su dinero de la tabla compromisos (Dashboard de promesas). */
    public function test_borrar_un_culto_cerrado_resincroniza_su_mes(): void
    {
        $culto = $this->crearCulto('2026-10-04');
        $this->crearSobre($culto, $this->ana, ['misiones' => 3000]);
        $culto->update(['cerrado' => true]);
        $antes = $this->dadoMisionesOctubreDeAna();
        $this->assertGreaterThanOrEqual(3000, $antes);

        $this->actingAs($this->admin)
            ->delete(route('cultos.destroy', $culto), ['motivo' => 'Culto registrado dos veces por error', 'confirmo' => '1'])
            ->assertRedirect(route('cultos.index'));

        $this->assertEquals($antes - 3000, $this->dadoMisionesOctubreDeAna());
    }

    /** QE-006: corregir un sobre de un culto cerrado (aqui, asignarle persona) actualiza compromisos. */
    public function test_corregir_un_sobre_de_un_culto_cerrado_resincroniza_su_mes(): void
    {
        $culto = $this->crearCulto('2026-10-11');
        $sobre = $this->crearSobre($culto, null, ['misiones' => 2000]);
        $culto->update(['cerrado' => true]);
        $antes = $this->dadoMisionesOctubreDeAna();

        $this->actingAs($this->admin)
            ->patch(route('recuento.asignar-persona', $sobre), ['persona_nombre' => $this->ana->nombre])
            ->assertRedirect();

        $this->assertSame($this->ana->id, $sobre->fresh()->persona_id);
        $this->assertEquals($antes + 2000, $this->dadoMisionesOctubreDeAna());
    }

    /** QE-001: el PDF de recuento (el que se firma) se genera para admin y tesorero. */
    public function test_el_pdf_de_recuento_se_genera(): void
    {
        $culto = $this->crearCulto('2026-10-04');
        $this->crearSobre($culto, $this->ana, ['misiones' => 3000, 'diezmo' => 10000]);
        $this->crearSobre($culto, null, ['misiones' => 20], 'USD', 510.0);
        $culto->update(['cerrado' => true]);

        foreach ([$this->admin, $this->crearUsuarioConRol('tesorero')] as $usuario) {
            $this->actingAs($usuario)
                ->get(route('ingresos-asistencia.pdf-recuento-individual', $culto))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.pdf-recuento-transferencias', $culto))->assertOk();
    }

    /**
     * QE-010: la marca de la app instalada y del login es la de IBBLA. Vale para una instalacion con
     * varias iglesias, como ibbla.eu (con una sola, la entrada lleva la de esa iglesia: MarcaInstalacionTest).
     */
    public function test_la_marca_es_la_de_ibbla(): void
    {
        \App\Models\Tenant::create([
            'nombre' => 'Otra Iglesia', 'siglas' => 'OTRA', 'slug' => 'otra', 'color_theme' => 'blue',
            'timezone' => 'America/Costa_Rica', 'locale' => 'es', 'moneda_codigo' => 'CRC', 'moneda_simbolo' => '₡', 'activo' => true,
        ]);
        // Quien abre la pantalla de entrada todavia no tiene iglesia: se suelta la que enlaza el escenario.
        app()->forgetInstance('current_tenant');

        $manifiesto = json_decode((string) file_get_contents(public_path('manifest.json')), true);
        $this->assertSame('IBBLA', $manifiesto['short_name']);
        foreach ($manifiesto['icons'] as $icono) {
            $this->assertStringNotContainsString('angeda', strtolower($icono['src']));
            $this->assertFileExists(public_path($icono['src']));
        }

        $this->get('/login')->assertOk()->assertSee('IBBLA')->assertDontSee('IBBSC')->assertDontSee('Sistema de Administración');
    }
}

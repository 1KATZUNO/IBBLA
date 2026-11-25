<?php

namespace Tests\Feature\Dashboard;

use App\Models\Persona;
use App\Models\Promesa;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Reportes\CumplimientoPromesasService;
use App\Services\Reportes\Dinero;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\PeriodoReporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConDatosDeDashboard;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Dashboard de Promesas (/dashboard/promesas) con la regla del motor (QE-008): prometido = meses
 * COMPLETOS antes de hoy; el mes en curso va aparte y no entra en saldo ni porcentaje; dado = lo dado
 * en lo prometido. El rango se amplia a meses completos. Hoy fijado en 15/10/2026 (octubre en curso).
 *
 * Ana promete misiones ₡1.000 mensual y da 500 (ago), 800 (sep) y 1.200 (oct).
 */
class DashboardPromesasRangoTest extends TestCase
{
    use ConDatosDeDashboard, ConIglesia, RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    private Persona $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 09:00:00');

        $this->iglesia = $this->crearIglesia();
        app()->instance('current_tenant', $this->iglesia);
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');

        $this->ana = $this->personaConPromesa($this->iglesia, 'Ana', 'misiones', 1000);
        $this->sobre($this->culto($this->iglesia, '2026-08-02'), $this->ana, 'misiones', 500);
        $this->sobre($this->culto($this->iglesia, '2026-09-06'), $this->ana, 'misiones', 800);
        $this->sobre($this->culto($this->iglesia, '2026-10-04'), $this->ana, 'misiones', 1200);
    }

    private function dashboard(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('dashboard.promesas', $query));
    }

    public function test_el_mes_en_curso_va_aparte_y_no_entra_en_saldo_ni_porcentaje(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31'])->assertOk();

        // Se cobra solo septiembre; lo dado cuenta hasta hoy (incluido octubre).
        $this->assertSame(1000.0, $respuesta->viewData('prometido'));
        $this->assertSame(2000.0, $respuesta->viewData('dado'));
        $this->assertSame(1000.0, $respuesta->viewData('saldo'));
        $this->assertEquals(200.0, $respuesta->viewData('cumplimiento'));
        $this->assertSame(1000.0, $respuesta->viewData('resumen')->prometidoEnCurso());
        $respuesta->assertSee('Octubre (en curso, todavía no se cobra)')
            ->assertSee('₡1,000')
            ->assertDontSee('₡1,000.00');
    }

    public function test_un_rango_a_mitad_de_mes_incluye_los_meses_completos_y_lo_avisa(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-10-20'])->assertOk();

        $this->assertSame(1000.0, $respuesta->viewData('prometido'));
        $this->assertSame('2026-09-01', $respuesta->viewData('rangoMeses')->inicio->toDateString());
        $this->assertSame('2026-10-31', $respuesta->viewData('rangoMeses')->fin->toDateString());
        $respuesta->assertSee('Los compromisos se calculan por mes: se incluyen los meses completos (01/09/2026 – 31/10/2026).');
    }

    public function test_un_rango_de_meses_completos_no_muestra_el_aviso(): void
    {
        $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31'])
            ->assertDontSee('Los compromisos se calculan por mes');
    }

    public function test_sin_parametros_muestra_el_año_en_curso_con_meses_completos(): void
    {
        $respuesta = $this->dashboard();

        $this->assertSame('2026-01-01', $respuesta->viewData('rango')->inicio->toDateString());
        $this->assertSame(9000.0, $respuesta->viewData('prometido'), 'Enero a septiembre.');
        $this->assertSame(2500.0, $respuesta->viewData('dado'));
    }

    public function test_mes_y_año_se_traducen_a_ese_mes(): void
    {
        $respuesta = $this->dashboard(['mes' => 9, 'año' => 2026]);

        $this->assertSame(1000.0, $respuesta->viewData('prometido'));
        $this->assertSame(800.0, $respuesta->viewData('dado'));
        $this->assertSame(-200.0, $respuesta->viewData('saldo'));
        $this->assertEquals(80.0, $respuesta->viewData('cumplimiento'));
        $respuesta->assertDontSee('data-kpi="en-curso"', false);
    }

    public function test_un_rango_que_cruza_de_año(): void
    {
        $this->sobre($this->culto($this->iglesia, '2025-10-05'), $this->ana, 'misiones', 999);   // antes del rango
        $this->sobre($this->culto($this->iglesia, '2025-12-07'), $this->ana, 'misiones', 600);
        $this->sobre($this->culto($this->iglesia, '2026-01-04'), $this->ana, 'misiones', 400);
        $this->sobre($this->culto($this->iglesia, '2026-03-01'), $this->ana, 'misiones', 777);   // despues del rango

        $respuesta = $this->dashboard(['fecha_inicio' => '2025-11-01', 'fecha_fin' => '2026-02-28']);

        $this->assertSame(4000.0, $respuesta->viewData('prometido'));
        $this->assertSame(1000.0, $respuesta->viewData('dado'));
        $this->assertCount(4, $respuesta->viewData('serie'), 'nov, dic, ene y feb.');
    }

    public function test_la_serie_mensual_marca_el_mes_en_curso(): void
    {
        $serie = $this->dashboard(['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-10-31'])->viewData('serie');

        $this->assertSame([500.0, 800.0, 1200.0], $serie->pluck('dado')->all());
        $this->assertSame([1000.0, 1000.0, 1000.0], $serie->pluck('prometido')->all());
        $this->assertSame([false, false, true], $serie->pluck('en_curso')->all());
        $this->assertStringContainsString('(en curso)', $serie->last()['mes']);
    }

    public function test_el_top_de_contribuyentes_se_calcula_sobre_el_rango_y_es_de_10(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $persona = $this->personaConPromesa($this->iglesia, "Persona $i", 'misiones', 100);
            $this->sobre($this->culto($this->iglesia, '2026-09-13'), $persona, 'misiones', $i * 10);   // dado: 10, 20 ... 110
        }

        $top = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31'])->viewData('topContribuyentes');

        $this->assertCount(10, $top);
        $this->assertSame('Ana', $top[0]['nombre'], 'Ana dio 2000 entre septiembre y octubre.');
        $this->assertSame('Persona 11', $top[1]['nombre']);
        $this->assertSame(110.0, $top[1]['dado']);
        $this->assertSame(100.0, $top[1]['prometido']);
        $this->assertEquals(110.0, $top[1]['pct']);
        $this->assertNotContains('Persona 1', $top->pluck('nombre')->all(), 'La de 10 queda fuera del top 10.');
    }

    public function test_las_personas_de_otra_iglesia_no_cuentan(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        $ajena = $this->personaConPromesa($otra, 'Ajena', 'misiones', 5000);
        $this->sobre($this->culto($otra, '2026-09-06'), $ajena, 'misiones', 5000);

        $respuesta = $this->dashboard(['mes' => 9, 'año' => 2026]);

        $this->assertSame(1000.0, $respuesta->viewData('prometido'));
        $this->assertNotContains('Ajena', $respuesta->viewData('topContribuyentes')->pluck('nombre')->all());
    }

    public function test_el_prometido_es_el_le_correspondia_del_cumplimiento_al_mismo_corte(): void
    {
        // Mas variedad: promesa semanal, una persona inactiva (no cuenta) y una en dolares.
        $beto = $this->personaConPromesa($this->iglesia, 'Beto', 'misiones', 250, 'semanal');
        $this->sobre($this->culto($this->iglesia, '2026-03-01'), $beto, 'misiones', 3000);
        $inactiva = $this->personaConPromesa($this->iglesia, 'Inactiva', 'misiones', 7000);
        $inactiva->update(['activo' => false]);

        $dashboard = $this->dashboard();
        $cumplimiento = app(CumplimientoPromesasService::class)->generar(PeriodoReporte::alCorte('2026-10-15'), FiltroPersonas::todas())->resumen();

        $this->assertSame(Dinero::centimos($cumplimiento['le_correspondia']), Dinero::centimos($dashboard->viewData('prometido')));
        $this->assertSame(Dinero::centimos($cumplimiento['ha_dado']), Dinero::centimos($dashboard->viewData('dado')));
        $this->assertNotContains('Inactiva', $dashboard->viewData('topContribuyentes')->pluck('nombre')->all());
    }

    public function test_el_excel_solo_se_ofrece_para_un_mes_o_un_año_completo(): void
    {
        $this->assertSame(
            route('ingresos-asistencia.excel-promesas', ['año' => 2026, 'mes' => 9]),
            $this->dashboard(['mes' => 9, 'año' => 2026])->viewData('excelUrl')
        );
        $this->assertSame(
            route('ingresos-asistencia.excel-promesas', ['año' => 2026, 'mes' => 9]),
            $this->dashboard(['fecha_inicio' => '2026-09-10', 'fecha_fin' => '2026-09-20'])->viewData('excelUrl')
        );
        $this->assertSame(
            route('ingresos-asistencia.excel-promesas', ['año' => 2026]),
            $this->dashboard()->viewData('excelUrl')
        );

        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31']);
        $this->assertNull($respuesta->viewData('excelUrl'));
        $respuesta->assertSee('El Excel de promesas se descarga por mes o por año completo.')
            ->assertDontSee('Descargar Excel');
    }

    public function test_un_rango_invalido_se_informa_y_cae_al_año_en_curso(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-10-31', 'fecha_fin' => '2026-09-01'])->assertOk();

        $this->assertTrue($respuesta->viewData('rango')->errores->has('fecha_fin'));
        $this->assertSame('2026-01-01', $respuesta->viewData('rango')->inicio->toDateString());
        $respuesta->assertSee('La fecha de fin no puede ser anterior a la fecha de inicio.');
    }

    public function test_un_año_futuro_no_rompe(): void
    {
        $this->dashboard(['fecha_inicio' => '2027-01-01', 'fecha_fin' => '2027-03-31'])->assertOk();
    }

    public function test_solo_admin_y_tesorero(): void
    {
        $this->actingAs($this->crearUsuario($this->iglesia, 'tesorero'))->get(route('dashboard.promesas'))->assertOk();

        foreach (['asistente', 'miembro'] as $rol) {
            $this->actingAs($this->crearUsuario($this->iglesia, $rol))->get(route('dashboard.promesas'))->assertForbidden();
        }
    }
}

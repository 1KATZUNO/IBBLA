<?php

namespace Tests\Feature\Reportes;

use App\Models\AuditLog;
use App\Services\Reportes\AuditoriaReportes;
use App\Services\Reportes\CumplimientoPromesasService;
use App\Services\Reportes\Dinero;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\PeriodoReporte;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\Concerns\LeeReportes;
use Tests\TestCase;

/**
 * Reporte de promesas por categoria (pantalla, PDF del mes y anual, por clase) con la regla del motor
 * (QE-008): prometido = meses completos; el mes en curso va aparte y no entra en el faltante; dado =
 * lo que las personas con promesa dieron en lo que prometieron. PDF con la plantilla unica (HU-R14).
 * Hoy: 15/10/2026 (octubre en curso).
 */
class ReportePromesasTest extends TestCase
{
    use CreaEscenarioReportes;
    use LeeReportes;
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

    public function test_un_mes_completo_trae_prometido_y_dado_del_motor(): void
    {
        $totales = $this->actingAs($this->admin)->get(route('ingresos-asistencia.promesas', ['año' => 2026, 'mes' => 2]))
            ->assertOk()
            ->assertDontSee('Profit')
            ->assertDontSee('en curso, todavía no se cobra')
            ->viewData('totales');

        $porCategoria = collect($totales['categorias'])->keyBy('categoria');
        // Ana: misiones 10.000, construccion 2.000 x 4, seminario $5 x 2 x 500; Carla: campa 2.000 + 3.000 y micro 1.500 x 2.
        $this->assertEquals(31000, $totales['grand_total']['prometido']);
        $this->assertEquals(5000, $porCategoria['CAMPA']['total_prometido']);
        // Dado en lo prometido: misiones de Ana 10.000 + $20 x 510; construccion 2.000. La campa de Ana
        // (no la prometio) y las misiones de Beto (sin promesa) no son cumplimiento de promesas.
        $this->assertEquals(20200, $porCategoria['Misiones']['total_dado']);
        $this->assertEquals(22200, $totales['grand_total']['dado']);
        $this->assertEquals(0, $totales['grand_total']['en_curso']);
    }

    public function test_el_mes_en_curso_va_aparte_y_no_genera_faltante(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('ingresos-asistencia.promesas', ['año' => 2026, 'mes' => 10]))->assertOk();
        $totales = $respuesta->viewData('totales');

        $this->assertEquals(0, $totales['grand_total']['prometido']);
        $this->assertEquals(0, $totales['grand_total']['faltante']);
        $this->assertEquals(0, $totales['grand_total']['profit']);
        // Octubre: Ana 10.000 + 2.000 x 4 + $5 x 2 x 500 = 23.000; Carla 5.000 + 3.000 = 8.000.
        $this->assertEquals(31000, $totales['grand_total']['en_curso']);
        $respuesta->assertSee('Octubre (en curso, todavía no se cobra)')
            ->assertSee('₡31,000');
    }

    public function test_el_año_cuadra_con_el_cumplimiento_al_mismo_corte(): void
    {
        $this->actingAs($this->admin);
        $totales = $this->get(route('ingresos-asistencia.promesas', ['año' => 2026, 'mes' => 'todos']))->assertOk()->viewData('totales');
        $cumplimiento = app(CumplimientoPromesasService::class)->generar(PeriodoReporte::alCorte('2026-10-15'), FiltroPersonas::todas())->resumen();

        $this->assertSame(Dinero::centimos($cumplimiento['le_correspondia']), Dinero::centimos($totales['grand_total']['prometido']));
        $this->assertSame(Dinero::centimos($cumplimiento['ha_dado']), Dinero::centimos($totales['grand_total']['dado']));
        $this->assertSame(Dinero::centimos($cumplimiento['faltante'] > 0 ? $totales['grand_total']['faltante'] : 0), Dinero::centimos($totales['grand_total']['faltante']));
    }

    public function test_el_pdf_del_mes_usa_la_plantilla_unica_y_queda_auditado(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('ingresos-asistencia.pdf-promesas', ['año' => 2026, 'mes' => 10]));
        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('reporte_promesas_2026_10.pdf', $respuesta->headers->get('Content-Disposition'));

        $texto = $this->textoDelPdf($respuesta->getContent());
        $this->assertStringContainsString('Reporte de promesas', $texto);
        $this->assertStringContainsString('Octubre 2026', $texto);
        $this->assertStringContainsString('Personas activas con promesa', $texto);
        $this->assertStringContainsString('₡31,000', $texto);
        $this->assertStringContainsString('generado el 15/10/2026', $texto);
        $this->assertStringContainsString('Página 1 de 1', $texto);

        $detalles = json_decode(AuditLog::where('action', AuditoriaReportes::ACCION)->sole()->details, true);
        $this->assertSame('promesas', $detalles['reporte']);
    }

    public function test_el_pdf_anual_mes_a_mes_con_el_mes_en_curso_rotulado(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('ingresos-asistencia.pdf-promesas-anual', ['año' => 2026]));
        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $texto = $this->textoDelPdf($respuesta->getContent());
        $cumplimiento = app(CumplimientoPromesasService::class)->generar(PeriodoReporte::alCorte('2026-10-15'), FiltroPersonas::todas())->resumen();
        $this->assertStringContainsString('Reporte anual de promesas', $texto);
        $this->assertStringContainsString('Año 2026', $texto);
        $this->assertStringContainsString('en curso, todavía', $texto);
        $this->assertStringContainsString('Total (meses completos)', $texto);
        $this->assertStringContainsString(Dinero::formato($cumplimiento['le_correspondia']), $texto);
        $this->assertStringNotContainsString('Noviembre', $texto, 'Los meses futuros no salen.');
    }

    public function test_tesorero_si_y_asistente_no(): void
    {
        $tesorero = $this->crearUsuarioConRol('tesorero');
        $asistente = $this->crearUsuarioConRol('asistente');
        $this->actingAs($tesorero)->get(route('ingresos-asistencia.pdf-promesas-anual', ['año' => 2026]))->assertOk();
        $this->actingAs($tesorero)->get(route('ingresos-asistencia.promesas'))->assertOk();
        $this->actingAs($asistente)->get(route('ingresos-asistencia.promesas'))->assertForbidden();
        $this->actingAs($asistente)->get(route('ingresos-asistencia.pdf-promesas'))->assertForbidden();
    }

    public function test_por_clase_usa_el_motor(): void
    {
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.promesas-por-clase', ['año' => 2026, 'mes' => 2, 'clase_id' => 'capilla']))
            ->assertOk()
            ->assertSee('₡31,000');
    }
}

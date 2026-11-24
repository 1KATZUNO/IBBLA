<?php

namespace Tests\Feature\Reportes;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\TestCase;

/**
 * Reporte de promesas por categoria (pantalla y PDF mensual/anual): lo prometido sale del motor
 * (ultima promesa por categoria, dolares al fin de mes) y lo dado es todo lo que entro a la
 * categoria (anonimos incluidos), con los dolares convertidos con la regla unica.
 */
class ReportePromesasTest extends TestCase
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

    public function test_el_mes_trae_prometido_del_motor_y_todo_lo_dado_en_colones(): void
    {
        $totales = $this->actingAs($this->admin)->get(route('ingresos-asistencia.promesas', ['año' => 2026, 'mes' => 2]))
            ->assertOk()
            ->assertDontSee('Profit')
            ->viewData('totales');

        $porCategoria = collect($totales['categorias'])->keyBy('categoria');
        // Ana: misiones 10.000, construccion 2.000 x 4, seminario $5 x 2 x 500; Carla: campa 2.000 + 3.000 y micro 1.500 x 2.
        $this->assertEquals(31000, $totales['grand_total']['prometido']);
        $this->assertEquals(5000, $porCategoria['CAMPA']['total_prometido']);
        // Dado: misiones 10.000 + $20 x 510 + 5.000 de Beto; construccion 2.000; campa 3.000 (sin diezmo).
        $this->assertEquals(25200, $porCategoria['Misiones']['total_dado']);
        $this->assertEquals(30200, $totales['grand_total']['dado']);
    }

    public function test_el_pdf_anual_responde(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('ingresos-asistencia.pdf-promesas-anual', ['año' => 2026]));

        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}

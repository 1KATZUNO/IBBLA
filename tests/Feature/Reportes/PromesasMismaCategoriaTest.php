<?php

namespace Tests\Feature\Reportes;

use App\Models\Compromiso;
use App\Services\InformePersonaService;
use App\Services\Reportes\CumplimientoPromesasService;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Reportes\SincronizadorCompromisos;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\TestCase;

/**
 * Dos promesas vigentes en la misma categoria (hallado con ingresos:analizar): lo esperado es la
 * SUMA de las dos, cada una con su frecuencia y su moneda, y el detalle lista ambas.
 *
 * Elena: seminario ₡10.000 cada domingo + $20 mensual (dolar a ₡500). Hoy 15/03/2026: se cobran
 * enero (4 domingos) y febrero (4 domingos): (40.000 + 10.000) x 2 = 100.000.
 */
class PromesasMismaCategoriaTest extends TestCase
{
    use CreaEscenarioReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-15 10:00:00');
        $this->crearEscenarioReportes();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_las_dos_promesas_se_suman_en_el_estado_de_cuenta_el_cumplimiento_compromisos_y_la_tabla(): void
    {
        $elena = $this->crearPersona('Elena Vargas', '8000-0001');
        $this->promesa($elena, 'seminario', 10000, 'semanal');
        $this->promesa($elena, 'seminario', 20, 'mensual', 'USD');
        $this->crearSobre($this->crearCulto('2026-02-22'), $elena, ['seminario' => 60000]);
        $this->actingAs($this->admin);

        // 1. Estado de cuenta (el mismo de Compromisos y Mi perfil).
        $informe = app(InformePersonaService::class)->datos($elena);
        $this->assertCount(1, $informe['compromisos']);
        $this->assertSame('₡10,000.00 cada domingo + $20.00 mensual', $informe['compromisos'][0]['detalle']);
        $this->assertEquals(100000, $informe['compromisos'][0]['esperado']);
        $this->assertEquals(100000, $informe['total_esperado']);
        $this->assertEquals(-40000, $informe['diferencia']);
        // Marzo en curso: lo del mes (5 domingos x 10.000 + $20 al de hoy) solo informativo.
        $this->assertEquals(60000, $informe['detalle_mensual'][2]['categorias']['seminario']['del_mes']);

        // 2. Cumplimiento con corte hoy.
        $fila = app(CumplimientoPromesasService::class)
            ->generar(PeriodoReporte::alCorte('2026-03-15'), FiltroPersonas::desde(['personas' => (string) $elena->id]))
            ->filas[0];
        $this->assertEquals(100000, $fila->leCorresponde());
        $this->assertEquals(60000, $fila->haDado());
        $this->assertSame('PENDIENTE', $fila->estado);
        $this->assertStringContainsString('₡10,000.00 cada domingo + $20.00 mensual', $fila->promesa);

        // 3. Pantalla de Compromisos.
        $this->get(route('compromisos.show', $elena))->assertOk()
            ->assertSee('₡100,000.00')
            ->assertSee('₡10,000.00 cada domingo + $20.00 mensual');

        // 4. Tabla compromisos (compromisos:sincronizar): una fila por mes con la suma, en colones.
        app(SincronizadorCompromisos::class)->sincronizar($this->iglesia, 2026);
        $enero = Compromiso::where(['persona_id' => $elena->id, 'categoria' => 'seminario', 'año' => 2026, 'mes' => 1])->sole();
        $this->assertEquals(50000, (float) $enero->monto_prometido_crc);
        $this->assertEquals(50000, (float) $enero->monto_prometido);
        $this->assertSame('CRC', $enero->moneda_promesa);
        $this->assertEquals(500, (float) $enero->tipo_cambio_usado);
        $this->assertSame(1, Compromiso::where(['persona_id' => $elena->id, 'categoria' => 'seminario', 'año' => 2026, 'mes' => 2])->count());
    }
}

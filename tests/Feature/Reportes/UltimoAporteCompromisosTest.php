<?php

namespace Tests\Feature\Reportes;

use App\Services\Reportes\CumplimientoPromesasService;
use App\Services\Reportes\FilaCumplimiento;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\PeriodoReporte;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\Concerns\LeeReportes;
use Tests\TestCase;

/**
 * QE-011: en Cumplimiento, "Último aporte a sus compromisos" usa la misma base que "Ha dado" (solo las
 * categorias que la persona prometio), en pantalla, PDF y Excel. Hoy: 13/10/2026.
 *
 *   Luis  promete misiones; da misiones el 09/08 y construccion (no prometida) el 11/10 -> ultimo 09/08
 *   Rosa  promete prestamo; solo da construccion el 19/07                             -> ha dado 0, ultimo "-"
 */
class UltimoAporteCompromisosTest extends TestCase
{
    use CreaEscenarioReportes;
    use LeeReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->crearEscenarioReportes();

        $luis = $this->crearPersona('Luis Ulate', '8100-0001');
        $this->promesa($luis, 'misiones', 1000, 'mensual');
        $this->crearSobre($this->crearCulto('2026-08-09'), $luis, ['misiones' => 9000]);
        $this->crearSobre($this->crearCulto('2026-10-11'), $luis, ['construccion' => 5000, 'diezmo' => 10000]);

        $rosa = $this->crearPersona('Rosa Hernández', '8100-0002');
        $this->promesa($rosa, 'prestamo', 2000, 'mensual');
        $this->crearSobre($this->crearCulto('2026-07-19'), $rosa, ['construccion' => 4000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_el_ultimo_aporte_solo_mira_las_categorias_prometidas(): void
    {
        $this->actingAs($this->admin);
        $filas = collect(app(CumplimientoPromesasService::class)->generar(PeriodoReporte::alCorte('2026-10-13'), FiltroPersonas::todas())->filas)->keyBy('nombre');

        $this->assertSame('09/08/2026', $filas['Luis Ulate']->ultimoAporteTexto());
        $this->assertEquals(9000, $filas['Luis Ulate']->haDado());

        $rosa = $filas['Rosa Hernández'];
        $this->assertEquals(0, $rosa->haDado());
        $this->assertSame('-', $rosa->ultimoAporteTexto());
        $this->assertSame('sin aportes en 9 meses (Enero a Septiembre)', $rosa->observacion);

        // Sin promesa, la base es cualquier categoria de compromiso (como su "ha dado").
        $this->assertSame('02/08/2026', $filas['Beto Rojas']->ultimoAporteTexto());
    }

    public function test_pantalla_pdf_y_excel_rotulan_y_muestran_la_misma_fecha(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('reportes.cumplimiento'))->assertOk()
            ->assertSee(FilaCumplimiento::TITULO_ULTIMO_APORTE)
            ->assertSee('09/08/2026')
            ->assertDontSee('11/10/2026')
            ->assertDontSee('19/07/2026');

        $texto = $this->textoDelPdf($this->get(route('reportes.cumplimiento.pdf'))->assertOk()->getContent());
        $this->assertStringContainsString('Último aporte a', $texto);
        $this->assertStringContainsString('compromisos', $texto);
        $this->assertStringContainsString('09/08/2026', $texto);
        $this->assertStringNotContainsString('19/07/2026', $texto);
        $this->assertStringNotContainsString('11/10/2026', $texto);

        $hoja = $this->leerExcel($this->get(route('reportes.cumplimiento.excel'))->assertOk()->getContent())->getSheetByName('Resumen');
        $columna = null;
        $filaLuis = null;
        $filaRosa = null;
        foreach ($hoja->getRowIterator() as $fila) {
            foreach ($fila->getCellIterator() as $celda) {
                $valor = $celda->getValue();
                if ($valor === FilaCumplimiento::TITULO_ULTIMO_APORTE) {
                    $columna = $celda->getColumn();
                }
                if ($valor === 'Luis Ulate') {
                    $filaLuis = $celda->getRow();
                }
                if ($valor === 'Rosa Hernández') {
                    $filaRosa = $celda->getRow();
                }
            }
        }
        $this->assertNotNull($columna);
        $fecha = $hoja->getCell($columna.$filaLuis)->getFormattedValue();
        $this->assertStringContainsString('09', (string) $fecha);
        $this->assertNotNull($hoja->getCell($columna.$filaLuis)->getValue());
        $this->assertContains($hoja->getCell($columna.$filaRosa)->getValue(), [null, '', '-']);
    }
}

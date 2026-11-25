<?php

namespace Tests\Feature\Reportes;

use App\Models\Persona;
use App\Services\Reportes\CumplimientoPromesasService;
use App\Services\Reportes\Dinero;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\PeriodoReporte;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\Concerns\LeeReportes;
use Tests\TestCase;

/**
 * HU-R4: vista previa ordenable y paginada en Cumplimiento y Estados de cuenta, con la fila de
 * totales del conjunto completo, y contrato: lo que dice la vista previa es lo que dicen el PDF y el
 * Excel para el mismo filtro. Hoy: 13/10/2026.
 */
class VistaPreviaReportesTest extends TestCase
{
    use CreaEscenarioReportes;
    use LeeReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->crearEscenarioReportes();
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return list<int> ids de persona en el orden en que salen las filas de la vista previa */
    private function ordenDeFilas(string $html): array
    {
        preg_match_all('/data-persona="(\d+)"/', $html, $m);

        return array_map('intval', $m[1]);
    }

    /** Celdas (texto) de la fila de la vista previa de una persona. */
    private function celdas(string $html, int $personaId): array
    {
        $this->assertSame(1, preg_match('#<tr[^>]*data-persona="'.$personaId.'"[^>]*>(.*?)</tr>#s', $html, $m), "Fila de la persona $personaId");
        preg_match_all('#<td[^>]*>(.*?)</td>#s', $m[1], $celdas);

        return array_map(fn ($c) => trim(html_entity_decode(strip_tags($c))), $celdas[1]);
    }

    private function total(string $html, string $clave): string
    {
        $this->assertSame(1, preg_match('#data-total="'.$clave.'">(.*?)</td>#s', $html, $m), "Total $clave");

        return trim(html_entity_decode($m[1]));
    }

    public function test_cumplimiento_ordena_por_columna_y_el_orden_viaja_en_la_url(): void
    {
        $base = ['corte' => '2026-10-13'];
        // Por defecto: pendientes, al dia y sin promesa (el orden del PDF).
        $porDefecto = $this->ordenDeFilas($this->get(route('reportes.cumplimiento', $base))->assertOk()->getContent());
        $this->assertSame([$this->ana->id, $this->carla->id, $this->beto->id], $porDefecto);

        $porDado = $this->get(route('reportes.cumplimiento', $base + ['orden' => 'dado', 'dir' => 'desc']))->assertOk();
        // Ana 58.826,25 > Carla 13.000,10 > Beto 6.234,56
        $this->assertSame([$this->ana->id, $this->carla->id, $this->beto->id], $this->ordenDeFilas($porDado->getContent()));
        $porDado->assertSee('aria-sort="descending"', false);

        $porNombreDesc = $this->ordenDeFilas($this->get(route('reportes.cumplimiento', $base + ['orden' => 'persona', 'dir' => 'desc']))->getContent());
        $this->assertSame([$this->carla->id, $this->beto->id, $this->ana->id], $porNombreDesc);

        // Una columna desconocida no rompe: vuelve al orden por defecto.
        $this->assertSame($porDefecto, $this->ordenDeFilas($this->get(route('reportes.cumplimiento', $base + ['orden' => 'raro']))->assertOk()->getContent()));

        // Pestaña por estado (en la URL).
        $this->assertSame([$this->beto->id], $this->ordenDeFilas($this->get(route('reportes.cumplimiento', $base + ['ver' => 'sin-promesa']))->getContent()));
    }

    public function test_paginada_a_partir_de_100_con_el_total_de_todas_las_paginas(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            $persona = Persona::create(['nombre' => sprintf('Volumen %03d', $i), 'activo' => true, 'tenant_id' => $this->iglesia->id]);
            $this->promesa($persona, 'misiones', 1000, 'mensual');
        }
        $resumen = app(CumplimientoPromesasService::class)->generar(PeriodoReporte::alCorte('2026-10-13'), FiltroPersonas::todas())->resumen();

        $pagina1 = $this->get(route('reportes.cumplimiento', ['orden' => 'persona']))->assertOk()->getContent();
        $pagina2 = $this->get(route('reportes.cumplimiento', ['orden' => 'persona', 'pagina' => 2]))->assertOk()->getContent();

        $this->assertCount(100, $this->ordenDeFilas($pagina1));
        $this->assertCount(3, $this->ordenDeFilas($pagina2));
        $this->assertStringContainsString('Filas 101 a 103 de 103', $pagina2);
        $this->assertStringContainsString('pagina=2', $pagina1);
        foreach ([$pagina1, $pagina2] as $html) {
            $this->assertSame(Dinero::formato($resumen['le_correspondia']), $this->total($html, 'correspondia'));
            $this->assertSame(Dinero::formato($resumen['ha_dado']), $this->total($html, 'dado'));
        }

        // Estados de cuenta: igual, 104 personas activas.
        $ec1 = $this->get(route('reportes.estados-cuenta', ['periodo' => 'meses', 'anio' => 2026, 'mes_desde' => 1, 'mes_hasta' => 10]))->assertOk()->getContent();
        $ec2 = $this->get(route('reportes.estados-cuenta', ['periodo' => 'meses', 'anio' => 2026, 'mes_desde' => 1, 'mes_hasta' => 10, 'pagina' => 2]))->assertOk()->getContent();
        $this->assertCount(100, $this->ordenDeFilas($ec1));
        $this->assertCount(3, $this->ordenDeFilas($ec2));
        $this->assertSame($this->total($ec1, 'dado'), $this->total($ec2, 'dado'));
    }

    public function test_contrato_vista_previa_igual_a_pdf_y_excel(): void
    {
        $parametros = ['corte' => '2026-10-13', 'grupo' => 'todas'];
        $html = $this->get(route('reportes.cumplimiento', $parametros))->assertOk()->getContent();
        $pdf = $this->textoDelPdf($this->get(route('reportes.cumplimiento.pdf', $parametros))->assertOk()->getContent());
        $libro = $this->leerExcel($this->get(route('reportes.cumplimiento.excel', $parametros))->assertOk()->getContent());
        $hoja = $libro->getSheetByName('Resumen');

        foreach ([$this->ana, $this->carla] as $persona) {
            [, , $correspondia, $dado, $diferencia, $estado, $ultimo] = $this->celdas($html, $persona->id);
            $fila = $this->filaDe($hoja, 'B', $persona->nombre);
            $this->assertSame(Dinero::formato((float) $hoja->getCell('C'.$fila)->getValue()), $correspondia, $persona->nombre.': le correspondía');
            $this->assertSame(Dinero::formato((float) $hoja->getCell('D'.$fila)->getValue()), $dado, $persona->nombre.': ha dado');
            $this->assertSame(Dinero::formato((float) $hoja->getCell('E'.$fila)->getValue(), 0, true), $diferencia, $persona->nombre.': diferencia');
            $this->assertSame($hoja->getCell('F'.$fila)->getValue(), $estado, $persona->nombre.': estado');
            foreach ([$correspondia, $dado, $diferencia, $ultimo] as $valor) {
                $this->assertStringContainsString($valor, $pdf, $persona->nombre.": $valor en el PDF");
            }
        }

        // Totales: la fila de la vista previa = la fila TOTAL del Excel = el PDF.
        $filaTotal = $this->filaDe($hoja, 'B', 'TOTAL');
        $this->assertSame(Dinero::formato((float) $hoja->getCell('C'.$filaTotal)->getValue()), $this->total($html, 'correspondia'));
        $this->assertSame(Dinero::formato((float) $hoja->getCell('D'.$filaTotal)->getValue()), $this->total($html, 'dado'));

        // Estados de cuenta: vista previa = Excel (hoja Resumen) por persona y en el total.
        $lote = ['periodo' => 'meses', 'anio' => 2026, 'mes_desde' => 1, 'mes_hasta' => 10, 'grupo' => 'todas'];
        $htmlLote = $this->get(route('reportes.estados-cuenta', $lote))->assertOk()->getContent();
        $resumen = $this->leerExcel($this->get(route('reportes.estados-cuenta.excel', $lote))->assertOk()->getContent())->getSheetByName('Resumen');
        $columna = fn (string $titulo) => $this->columnaDe($resumen, $titulo);
        foreach ([$this->ana, $this->beto, $this->carla] as $persona) {
            $celdas = $this->celdas($htmlLote, $persona->id);
            $fila = $this->filaDe($resumen, 'A', $persona->nombre);
            $this->assertSame(Dinero::formato((float) $resumen->getCell($columna('Total contribuido').$fila)->getValue(), 2), $celdas[2], $persona->nombre.': total contribuido');
            if ($persona->id !== $this->beto->id) {
                $this->assertSame(Dinero::formato((float) $resumen->getCell($columna('Le corresponde').$fila)->getValue(), 2), $celdas[3]);
                $this->assertSame(Dinero::formato((float) $resumen->getCell($columna('Ha dado en compromisos').$fila)->getValue(), 2), $celdas[4]);
            }
        }
        $filaTotal = $this->filaDe($resumen, 'A', 'TOTAL');
        $this->assertSame(Dinero::formato((float) $resumen->getCell($columna('Total contribuido').$filaTotal)->getValue(), 2), $this->total($htmlLote, 'contribuido'));
        $this->assertSame(Dinero::formato((float) $resumen->getCell($columna('Le corresponde').$filaTotal)->getValue(), 2), $this->total($htmlLote, 'correspondia'));
        $this->assertSame(Dinero::formato((float) $resumen->getCell($columna('Ha dado en compromisos').$filaTotal)->getValue(), 2), $this->total($htmlLote, 'dado'));
    }

    private function filaDe(Worksheet $hoja, string $columna, string $valor): int
    {
        for ($fila = 1; $fila <= $hoja->getHighestRow(); $fila++) {
            if ($hoja->getCell($columna.$fila)->getValue() === $valor) {
                return $fila;
            }
        }
        $this->fail("No encontre \"$valor\" en la columna $columna de la hoja {$hoja->getTitle()}");
    }

    private function columnaDe(Worksheet $hoja, string $titulo): string
    {
        foreach ($hoja->getRowIterator() as $fila) {
            foreach ($fila->getCellIterator() as $celda) {
                if ($celda->getValue() === $titulo) {
                    return $celda->getColumn();
                }
            }
        }
        $this->fail("No encontre la columna \"$titulo\"");
    }
}

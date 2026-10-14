<?php

namespace Tests\Feature\Reportes;

use App\Models\Compromiso;
use App\Models\Persona;
use App\Services\InformePersonaService;
use App\Services\ReporteGeneralService;
use App\Services\Reportes\CumplimientoPromesasService;
use App\Services\Reportes\Dinero;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\MotorReportes;
use App\Services\Reportes\PeriodoReporte;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\Concerns\LeeReportes;
use Tests\TestCase;

/**
 * Contrato de los reportes de dinero: para las mismas 3 personas y el mismo periodo, TODAS las
 * salidas dicen los mismos números, al céntimo (HU-R7). Si una salida nueva calcula por su cuenta,
 * esta prueba la delata.
 *
 * Salidas: el motor, el Reporte General (datos y PDF), el estado de cuenta (datos y PDF), el
 * reporte de contribuciones (PDF), el cumplimiento de promesas con corte al ultimo dia del periodo
 * (datos, PDF y Excel) y la pantalla de Compromisos (tabla compromisos).
 * Periodos: enero a marzo y enero a octubre de 2026 (el "mes en curso" de cada reporte).
 */
class ContratoReportesTest extends TestCase
{
    use CreaEscenarioReportes;
    use LeeReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEscenarioReportes();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, array{0: string}> el "hoy" de cada periodo */
    public static function periodos(): array
    {
        return [
            'enero a marzo' => ['2026-03-20'],
            'enero a octubre' => ['2026-10-15'],
        ];
    }

    #[DataProvider('periodos')]
    public function test_todas_las_salidas_dan_los_mismos_numeros_al_centimo(string $hoy): void
    {
        Carbon::setTestNow($hoy.' 10:00:00');
        $this->actingAs($this->admin);
        $periodo = PeriodoReporte::deMeses(2026, 1, now()->month);
        $resultado = app(MotorReportes::class)->calcular($periodo, [$this->ana, $this->beto, $this->carla]);

        $reporteGeneral = collect(app(ReporteGeneralService::class)->generar(2026, now()->month)['reporteData'])->keyBy('nombre');
        $textoReporteGeneral = $this->textoDelPdf($this->get(route('personas.reporte-general', ['accion' => 'ver']))->assertOk()->getContent());
        $textoContribuciones = $this->textoDelPdf($this->get(route('personas.reporte-pdf', ['accion' => 'ver']))->assertOk()->getContent());

        // Cumplimiento con corte el ultimo dia del periodo (se cobran los mismos meses). El corte no
        // puede ser futuro: se pide "al dia siguiente" y se vuelve al hoy del periodo.
        $corte = $periodo->hasta->toDateString();
        Carbon::setTestNow($periodo->hasta->addDay()->setTime(10, 0));
        $cumplimiento = [];
        foreach (app(CumplimientoPromesasService::class)->generar(PeriodoReporte::alCorte($corte), FiltroPersonas::todas())->filas as $filaCumplimiento) {
            $cumplimiento[$filaCumplimiento->nombre] = $filaCumplimiento;
        }
        $textoCumplimiento = $this->textoDelPdf($this->get(route('reportes.cumplimiento.pdf', ['corte' => $corte]))->assertOk()->getContent());
        $excelCumplimiento = $this->leerExcel($this->get(route('reportes.cumplimiento.excel', ['corte' => $corte]))->assertOk()->getContent());
        Carbon::setTestNow($hoy.' 10:00:00');

        foreach ([$this->ana, $this->beto, $this->carla] as $persona) {
            $cuenta = $resultado->cuenta($persona->id);
            $nombre = $persona->nombre;

            // 1. Reporte General: totales y cada mes x categoría de compromiso.
            $fila = $reporteGeneral[$nombre];
            $this->assertCentimos($cuenta->dadoEnCompromisos(), $fila['totales']['dado'], "$nombre: dado del Reporte General");
            $this->assertCentimos($cuenta->esperado(), $fila['totales']['esperado'], "$nombre: esperado del Reporte General");
            foreach ($periodo->meses() as $i => $mes) {
                foreach ($fila['meses'][$i]['categorias'] as $categoria => $valores) {
                    $this->assertCentimos($cuenta->dado($mes->clave, [$categoria]), $valores['dado'], "$nombre {$mes->nombre} $categoria: dado");
                    $this->assertCentimos($cuenta->esperado($mes->clave, $categoria), $valores['esperado'], "$nombre {$mes->nombre} $categoria: esperado");
                }
            }
            // El PDF imprime los mismos totales (sin decimales).
            $this->assertStringContainsString('TOTAL '.mb_strtoupper($nombre), $textoReporteGeneral);
            $this->assertStringContainsString(number_format($cuenta->dadoEnCompromisos(), 0), $textoReporteGeneral, "$nombre: total dado en el PDF del Reporte General");

            // 2. Estado de cuenta (el informe que va por WhatsApp): datos y PDF.
            $informe = app(InformePersonaService::class)->datos($persona);
            $this->assertCentimos($cuenta->dado(), $informe['total_dado'], "$nombre: total contribuido del estado de cuenta");
            foreach ($cuenta->dadoPorCategoria() as $categoria => $monto) {
                $this->assertCentimos($monto, $informe['totales_categoria'][$categoria], "$nombre: $categoria en el estado de cuenta");
            }
            foreach ($periodo->meses() as $i => $mes) {
                $this->assertCentimos($cuenta->dado($mes->clave), $informe['meses'][$i]['total'], "$nombre {$mes->nombre}: total del mes en el estado de cuenta");
            }
            if ($cuenta->tienePromesa()) {
                $this->assertCentimos($cuenta->esperado(), $informe['total_esperado'], "$nombre: compromisos a la fecha");
                $this->assertCentimos($cuenta->dadoEnCompromisos(), $informe['dado_en_compromisos'], "$nombre: dado en compromisos");
                $this->assertCentimos($fila['totales']['diferencia'], $informe['diferencia'], "$nombre: diferencia del estado de cuenta = la del Reporte General");
                $this->assertCentimos($informe['dado_en_compromisos'], array_sum(array_column($informe['compromisos'], 'dado')), "$nombre: la tabla de compromisos cuadra con el resumen");
            }
            $textoInforme = $this->textoDelPdf(app(InformePersonaService::class)->pdf($persona)->output());
            $this->assertStringContainsString('₡'.number_format($cuenta->dado(), 2), $textoInforme, "$nombre: total en el PDF del estado de cuenta");

            // 3. Reporte de contribuciones: total por persona (todas las categorías de la iglesia).
            $categoriasIglesia = array_keys($resultado->categorias);
            $this->assertStringContainsString('₡'.number_format($cuenta->dado(null, $categoriasIglesia), 2), $textoContribuciones, "$nombre: total en el reporte de contribuciones");

            // 4. Cumplimiento de promesas: pantalla/servicio, PDF y Excel.
            $filaCumplimiento = $cumplimiento[$nombre];
            $this->assertCentimos($cuenta->dadoEnCompromisos(), $filaCumplimiento->haDado(), "$nombre: ha dado en el cumplimiento");
            $this->assertStringContainsString(Dinero::formato($filaCumplimiento->haDado()), $textoCumplimiento, "$nombre: ha dado en el PDF del cumplimiento");
            if ($cuenta->tienePromesa()) {
                $this->assertCentimos($cuenta->esperado(), $filaCumplimiento->leCorresponde(), "$nombre: le correspondia en el cumplimiento");
                $this->assertCentimos($fila['totales']['diferencia'], $filaCumplimiento->diferencia(), "$nombre: diferencia del cumplimiento = la del Reporte General");
                $hoja = $excelCumplimiento->getSheetByName('Resumen');
                $filaExcel = $this->filaDe($hoja, 'B', $nombre);
                $this->assertCentimos($filaCumplimiento->leCorresponde(), $hoja->getCell('C'.$filaExcel)->getValue(), "$nombre: le correspondia en el Excel");
                $this->assertCentimos($filaCumplimiento->haDado(), $hoja->getCell('D'.$filaExcel)->getValue(), "$nombre: ha dado en el Excel");
            } else {
                $hoja = $excelCumplimiento->getSheetByName('Sin promesa');
                $this->assertCentimos($filaCumplimiento->haDado(), $hoja->getCell('B'.$this->filaDe($hoja, 'A', $nombre))->getValue(), "$nombre: ha dado en el Excel (sin promesa)");
            }

            // 5. Compromisos: cada mes y categoría prometida, en colones.
            foreach ($periodo->meses() as $mes) {
                $this->get(route('compromisos.show', ['persona' => $persona, 'año' => $mes->anio, 'mes' => $mes->mes]))->assertOk();
                foreach ($cuenta->promesas() as $categoria => $promesa) {
                    $compromiso = Compromiso::where(['persona_id' => $persona->id, 'categoria' => $categoria, 'año' => $mes->anio, 'mes' => $mes->mes])->sole();
                    $this->assertCentimos($cuenta->esperado($mes->clave, $categoria), $compromiso->monto_prometido_crc, "$nombre {$mes->nombre} $categoria: prometido en Compromisos");
                    $this->assertCentimos($cuenta->dado($mes->clave, [$categoria]), $compromiso->monto_dado_crc, "$nombre {$mes->nombre} $categoria: dado en Compromisos");
                }
            }
        }
    }

    public function test_los_numeros_de_referencia_a_mano(): void
    {
        // Ancla independiente del motor (cuentas hechas a mano en CreaEscenarioReportes), enero a marzo.
        Carbon::setTestNow('2026-03-20 10:00:00');
        $this->actingAs($this->admin);
        $reporte = collect(app(ReporteGeneralService::class)->generar(2026, 3)['reporteData'])->keyBy('nombre');

        $this->assertEquals(['dado' => 38825.5, 'esperado' => 71000, 'diferencia' => -32174.5], $reporte['Ana Mora']['totales']);
        $this->assertEquals(['dado' => 5000, 'esperado' => 0, 'diferencia' => 5000], $reporte['Beto Rojas']['totales']);
        // Carla: campa 9.000,10 + micro 3.000; esperado (campa 3.000 + micro 3.000) x 3. pro_templo no es de compromiso.
        $this->assertEquals(['dado' => 12000.1, 'esperado' => 18000, 'diferencia' => -5999.9], $reporte['Carla Solano']['totales']);
        // Ni la inactiva ni la de otra iglesia.
        $this->assertSame(['Ana Mora', 'Beto Rojas', 'Carla Solano'], $reporte->keys()->all());
    }

    /** Fila de la hoja en que la columna $columna tiene $valor. */
    private function filaDe(Worksheet $hoja, string $columna, string $valor): int
    {
        for ($fila = 1; $fila <= $hoja->getHighestRow(); $fila++) {
            if ($hoja->getCell($columna.$fila)->getValue() === $valor) {
                return $fila;
            }
        }
        $this->fail("No encontre \"$valor\" en la columna $columna de la hoja {$hoja->getTitle()}");
    }

    private function assertCentimos(float|int|string $esperado, float|int|string $real, string $mensaje): void
    {
        $this->assertSame(number_format((float) $esperado, 2, '.', ''), number_format((float) $real, 2, '.', ''), $mensaje);
    }
}

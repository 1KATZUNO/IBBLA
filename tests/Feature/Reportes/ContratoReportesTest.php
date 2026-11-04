<?php

namespace Tests\Feature\Reportes;

use App\Models\Compromiso;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\InformePersonaService;
use App\Services\ReporteGeneralService;
use App\Services\Reportes\CumplimientoPromesasService;
use App\Services\Reportes\Dinero;
use App\Services\Reportes\EstadoCuentaLoteService;
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
 * Contrato de los reportes de dinero (HU-R7, QA-024): para las mismas 3 personas y el mismo dia,
 * TODAS las salidas dicen lo mismo, al centimo: le corresponde, ha dado, diferencia y estado. Si una
 * pantalla vuelve a calcular por su cuenta, esta prueba la delata.
 *
 * Salidas: el motor, el estado de cuenta (datos y PDF), el lote de estados de cuenta (datos y
 * Excel), el cumplimiento con corte (datos, pantalla, PDF y Excel), el Reporte General (datos y
 * PDF), la ficha, la pantalla de Compromisos, Mi perfil del miembro y el reporte de contribuciones (PDF).
 * Dias: 20/03/2026 (marzo en curso) y 15/10/2026 (octubre en curso); todo con corte hoy.
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

    /** @return array<string, array{0: string}> */
    public static function dias(): array
    {
        return [
            'marzo en curso' => ['2026-03-20'],
            'octubre en curso' => ['2026-10-15'],
        ];
    }

    #[DataProvider('dias')]
    public function test_todas_las_salidas_dan_los_mismos_numeros_al_centimo(string $hoy): void
    {
        Carbon::setTestNow($hoy.' 10:00:00');
        $this->actingAs($this->admin);
        $personas = [$this->ana, $this->beto, $this->carla];
        $resultado = app(MotorReportes::class)->calcular(PeriodoReporte::alCorte($hoy), $personas);
        $periodoAnio = PeriodoReporte::deMeses(2026, 1, now()->month);

        // Salidas de grupo: una vez para las tres personas.
        $reporteGeneral = collect(app(ReporteGeneralService::class)->generar(2026, now()->month)['reporteData'])->keyBy('nombre');
        $textoReporteGeneral = $this->textoDelPdf($this->get(route('personas.reporte-general', ['accion' => 'ver']))->assertOk()->getContent());
        $textoContribuciones = $this->textoDelPdf($this->get(route('personas.reporte-pdf', ['accion' => 'ver']))->assertOk()->getContent());

        $cumplimiento = [];
        foreach (app(CumplimientoPromesasService::class)->generar(PeriodoReporte::alCorte($hoy), FiltroPersonas::todas())->filas as $fila) {
            $cumplimiento[$fila->nombre] = $fila;
        }
        $pantallaCumplimiento = $this->get(route('reportes.cumplimiento'))->assertOk()->getContent();
        $textoCumplimiento = $this->textoDelPdf($this->get(route('reportes.cumplimiento.pdf'))->assertOk()->getContent());
        $excelCumplimiento = $this->leerExcel($this->get(route('reportes.cumplimiento.excel'))->assertOk()->getContent());

        $lote = app(EstadoCuentaLoteService::class)->generar($periodoAnio, FiltroPersonas::todas());
        $parametrosLote = ['periodo' => 'meses', 'anio' => 2026, 'mes_desde' => 1, 'mes_hasta' => now()->month];
        $excelLote = $this->leerExcel($this->get(route('reportes.estados-cuenta.excel', $parametrosLote))->assertOk()->getContent())->getSheetByName('Resumen');

        $compromisosAntes = Compromiso::count();

        foreach ($personas as $i => $persona) {
            $nombre = $persona->nombre;
            $cuenta = $resultado->cuenta($persona->id);
            $estado = $cuenta->estado();
            $leCorresponde = $estado->leCorresponde();
            $haDado = $estado->haDado();
            $diferencia = $estado->diferencia();

            // 1. Estado de cuenta (el PDF de la ficha, Compromisos, la lista, Mi perfil y WhatsApp).
            $informe = app(InformePersonaService::class)->datos($persona);
            $this->assertCentimos($cuenta->dado(), $informe['total_dado'], "$nombre: total contribuido del estado de cuenta");
            if ($cuenta->tienePromesa()) {
                $this->assertCentimos($leCorresponde, $informe['total_esperado'], "$nombre: le corresponde en el estado de cuenta");
                $this->assertCentimos($haDado, $informe['dado_en_compromisos'], "$nombre: ha dado en el estado de cuenta");
                $this->assertCentimos($diferencia, $informe['diferencia'], "$nombre: diferencia del estado de cuenta");
                $this->assertSame($estado->etiqueta(), $informe['estado'], "$nombre: estado del estado de cuenta");
                $this->assertCentimos($haDado, array_sum(array_column($informe['compromisos'], 'dado')), "$nombre: la tabla de compromisos cuadra con el resumen");
                $this->assertCentimos($leCorresponde, array_sum(array_column($informe['compromisos'], 'esperado')), "$nombre: lo que corresponde cuadra con el resumen");
            }
            $textoInforme = $this->textoDelPdf(app(InformePersonaService::class)->pdf($persona)->output());
            $this->assertStringContainsString('₡'.number_format($cuenta->dado(), 2), $textoInforme, "$nombre: total en el PDF del estado de cuenta");
            if ($cuenta->tienePromesa()) {
                $this->assertStringContainsString('₡'.number_format($leCorresponde, 2), $textoInforme, "$nombre: le corresponde en el PDF");
                $this->assertStringContainsString('₡'.number_format($haDado, 2), $textoInforme, "$nombre: ha dado en el PDF");
            }

            // 2. Lote de estados de cuenta (datos y Excel): lo mismo que el individual.
            $this->assertSame($nombre, $lote->estados[$i]['persona']->nombre);
            foreach (['total_dado', 'total_esperado', 'dado_en_compromisos', 'diferencia', 'estado'] as $clave) {
                $this->assertEquals($informe[$clave], $lote->estados[$i][$clave], "$nombre: $clave en el lote");
            }
            $filaLote = $this->filaDe($excelLote, 'A', $nombre);
            if ($cuenta->tienePromesa()) {
                $columnaCorresponde = $this->columnaDe($excelLote, 'Le corresponde');
                $columnaHaDado = $this->columnaDe($excelLote, 'Ha dado en compromisos');
                $this->assertCentimos($leCorresponde, $excelLote->getCell($columnaCorresponde.$filaLote)->getValue(), "$nombre: le corresponde en el Excel del lote");
                $this->assertCentimos($haDado, $excelLote->getCell($columnaHaDado.$filaLote)->getValue(), "$nombre: ha dado en el Excel del lote");
            }

            // 3. Cumplimiento con corte hoy: datos, pantalla, PDF y Excel.
            $fila = $cumplimiento[$nombre];
            $this->assertSame($estado->etiqueta(), $fila->estado, "$nombre: estado en el cumplimiento");
            $this->assertCentimos($haDado, $fila->haDado(), "$nombre: ha dado en el cumplimiento");
            $this->assertStringContainsString(Dinero::formato($fila->haDado()), $textoCumplimiento, "$nombre: ha dado en el PDF del cumplimiento");
            $this->assertStringContainsString(e(Dinero::formato($fila->haDado())), $pantallaCumplimiento, "$nombre: ha dado en la pantalla del cumplimiento");
            if ($cuenta->tienePromesa()) {
                $this->assertCentimos($leCorresponde, $fila->leCorresponde(), "$nombre: le correspondia en el cumplimiento");
                $this->assertCentimos($diferencia, $fila->diferencia(), "$nombre: diferencia del cumplimiento");
                $hoja = $excelCumplimiento->getSheetByName('Resumen');
                $filaExcel = $this->filaDe($hoja, 'B', $nombre);
                $this->assertCentimos($leCorresponde, $hoja->getCell('C'.$filaExcel)->getValue(), "$nombre: le correspondia en el Excel");
                $this->assertCentimos($haDado, $hoja->getCell('D'.$filaExcel)->getValue(), "$nombre: ha dado en el Excel");
            } else {
                $hoja = $excelCumplimiento->getSheetByName('Sin promesa');
                $this->assertCentimos($fila->haDado(), $hoja->getCell('B'.$this->filaDe($hoja, 'A', $nombre))->getValue(), "$nombre: ha dado en el Excel (sin promesa)");
            }

            // 4. Reporte General: totales y cada mes x categoria (solo quien tiene promesa).
            if ($cuenta->tienePromesa()) {
                $filaRg = $reporteGeneral[$nombre];
                $this->assertCentimos($haDado, $filaRg['totales']['dado'], "$nombre: dado del Reporte General");
                $this->assertCentimos($leCorresponde, $filaRg['totales']['esperado'], "$nombre: esperado del Reporte General");
                $this->assertCentimos($diferencia, $filaRg['totales']['diferencia'], "$nombre: diferencia del Reporte General");
                foreach ($resultado->periodo->meses() as $m => $mes) {
                    foreach ($filaRg['meses'][$m]['categorias'] as $categoria => $valores) {
                        $this->assertCentimos($cuenta->esperado($mes->clave, $categoria), $valores['esperado'], "$nombre {$mes->nombre} $categoria: esperado del Reporte General");
                    }
                }
                $this->assertStringContainsString('TOTAL '.mb_strtoupper($nombre), $textoReporteGeneral);
                $this->assertStringContainsString(number_format($haDado, 0), $textoReporteGeneral, "$nombre: total dado en el PDF del Reporte General");
            } else {
                $this->assertArrayNotHasKey($nombre, $reporteGeneral->all(), "$nombre: sin promesa no va en el Reporte General");
            }

            // 5. Reporte de contribuciones: total por persona (todas las categorias de la iglesia).
            $categoriasIglesia = array_keys($resultado->categorias);
            $this->assertStringContainsString('₡'.number_format($cuenta->dado(null, $categoriasIglesia), 2), $textoContribuciones, "$nombre: total en el reporte de contribuciones");

            // 6. Ficha de la persona: lo dado en el anio al corte (dolares convertidos, nada futuro).
            $ficha = $this->get(route('personas.show', $persona))->assertOk()->assertSee('₡'.number_format($cuenta->dado(), 2));
            if ($cuenta->tienePromesa()) {
                $ficha->assertSee('Compromisos: '.$estado->etiqueta());
            }

            // 7. Pantalla de Compromisos (admin) y 8. Mi perfil (el miembro): mismas cifras y estado.
            $pantallaCompromisos = $this->get(route('compromisos.show', $persona))->assertOk();
            $miPerfil = $this->actingAs($this->miembroDe($persona))->get(route('mi-perfil.index'))->assertOk();
            $this->actingAs($this->admin);
            foreach (['Compromisos' => $pantallaCompromisos, 'Mi perfil' => $miPerfil] as $pantalla => $respuesta) {
                if ($cuenta->tienePromesa()) {
                    $respuesta->assertSee('₡'.number_format($leCorresponde, 2))
                        ->assertSee('₡'.number_format($haDado, 2))
                        ->assertSee($estado->etiqueta());
                    $this->assertStringNotContainsString('Deuda pendiente', $respuesta->getContent(), "$nombre: $pantalla sin deudas por mes");
                } else {
                    $respuesta->assertSee('₡'.number_format($cuenta->dado(), 2));
                }
            }
        }

        // Ninguna de estas lecturas escribio en la tabla compromisos (QA-025).
        $this->assertSame($compromisosAntes, Compromiso::count());
    }

    public function test_los_numeros_de_referencia_a_mano(): void
    {
        // Ancla independiente del motor (cuentas hechas a mano en CreaEscenarioReportes), hoy 20/03:
        // se cobran enero y febrero; lo dado cuenta hasta hoy y solo en categorias prometidas.
        Carbon::setTestNow('2026-03-20 10:00:00');
        $this->actingAs($this->admin);
        $reporte = collect(app(ReporteGeneralService::class)->generar(2026, 3)['reporteData'])->keyBy('nombre');

        // Ana: esperado ene (10.000 + 2.000 x 4 + $10 x 500) + feb (10.000 + 2.000 x 4 + 5.000) = 46.000;
        // dado misiones 30.200,50 + construccion 2.000 + seminario 3.625 (sin campa ni diezmo).
        $this->assertEquals(['dado' => 35825.5, 'esperado' => 46000, 'diferencia' => -10174.5], $reporte['Ana Mora']['totales']);
        // Carla: campa 9.000,10 + micro 3.000; esperado (3.000 + 3.000) x 2. pro_templo no es de compromiso.
        $this->assertEquals(['dado' => 12000.1, 'esperado' => 12000, 'diferencia' => 0.1], $reporte['Carla Solano']['totales']);
        // Beto no tiene promesa; ni la inactiva ni la de otra iglesia.
        $this->assertSame(['Ana Mora', 'Carla Solano'], $reporte->keys()->all());
    }

    /** Usuario miembro ligado a la persona (para ver su Mi perfil). */
    private function miembroDe($persona): User
    {
        $rol = TenantRole::where('tenant_id', $this->iglesia->id)->where('slug', 'miembro')->firstOrFail();
        $usuario = User::firstOrCreate(['email' => 'miembro'.$persona->id.'@prueba.test'], [
            'name' => $persona->nombre,
            'password' => 'secreto-123',
            'rol' => 'miembro',
            'is_super_admin' => false,
            'tenant_id' => $this->iglesia->id,
            'tenant_role_id' => $rol->id,
        ]);
        $persona->forceFill(['user_id' => $usuario->id])->save();

        return $usuario;
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

    /** Letra de la columna cuyo encabezado es $titulo. */
    private function columnaDe(Worksheet $hoja, string $titulo): string
    {
        foreach ($hoja->getRowIterator() as $fila) {
            foreach ($fila->getCellIterator() as $celda) {
                if ($celda->getValue() === $titulo) {
                    return $celda->getColumn();
                }
            }
        }
        $this->fail("No encontre la columna \"$titulo\" en la hoja {$hoja->getTitle()}");
    }

    private function assertCentimos(float|int|string $esperado, float|int|string $real, string $mensaje): void
    {
        $this->assertSame(number_format((float) $esperado, 2, '.', ''), number_format((float) $real, 2, '.', ''), $mensaje);
    }
}

<?php

namespace Tests\Feature\Reportes;

use App\Models\AuditLog;
use App\Services\InformePersonaService;
use App\Services\Reportes\AuditoriaReportes;
use App\Services\Reportes\EstadoCuentaLoteService;
use App\Services\Reportes\Exportaciones\EstadoCuentaPdf;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\PeriodoReporte;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\Concerns\LeeReportes;
use Tests\TestCase;
use ZipArchive;

/**
 * Estados de cuenta en lote (HU-R5): un PDF con portada, indice y una seccion por persona, un ZIP
 * con un PDF por persona y un Excel con resumen, compromisos y detalle. Hoy simulado: 15/10/2026;
 * escenario en CreaEscenarioReportes (Ana, Beto y Carla).
 */
class EstadosCuentaLoteTest extends TestCase
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

    private function parametros(array $extra = []): array
    {
        return array_merge(['periodo' => 'meses', 'anio' => 2026, 'mes_desde' => 1, 'mes_hasta' => 3], $extra);
    }

    public function test_cada_persona_del_lote_trae_las_cifras_del_estado_de_cuenta_individual(): void
    {
        $this->actingAs($this->admin);
        $periodo = PeriodoReporte::deMeses(2026, 1, 10);
        $lote = app(EstadoCuentaLoteService::class)->generar($periodo, FiltroPersonas::todas());

        $this->assertSame(['Ana Mora', 'Beto Rojas', 'Carla Solano'], array_map(fn ($d) => $d['persona']->nombre, $lote->estados));
        foreach ([$this->ana, $this->beto, $this->carla] as $i => $persona) {
            $individual = app(InformePersonaService::class)->datos($persona);
            foreach (['total_dado', 'total_esperado', 'dado_en_compromisos', 'diferencia', 'totales_categoria', 'compromisos', 'meses', 'categorias'] as $clave) {
                $this->assertEquals($individual[$clave], $lote->estados[$i][$clave], "{$persona->nombre}: $clave");
            }
        }
    }

    public function test_la_pantalla_muestra_la_vista_previa_y_los_botones(): void
    {
        $this->actingAs($this->admin)->get(route('reportes.estados-cuenta', $this->parametros()))
            ->assertOk()
            ->assertSee('Enero - Marzo 2026 · Todas las personas activas · 3 personas.')
            ->assertSee('Ana Mora')
            ->assertSee('₡38,825.50')
            ->assertSee('-₡32,174.50')
            ->assertSee('SIN PROMESA')
            ->assertSee(e(route('reportes.estados-cuenta.zip', $this->parametros(['grupo' => 'todas']))), false)
            ->assertDontSee('Zoe Ajena');
    }

    public function test_el_pdf_tiene_portada_indice_y_una_seccion_por_persona(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('reportes.estados-cuenta.pdf', $this->parametros(['accion' => 'descargar'])));

        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $respuesta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('estados-de-cuenta_2026-01_2026-03.pdf', $respuesta->headers->get('Content-Disposition'));

        $pdf = $respuesta->getContent();
        $texto = $this->textoDelPdf($pdf);
        // Portada (1) + indice (2) + una pagina por persona.
        $this->assertSame(5, $this->paginasDelPdf($pdf));
        $this->assertStringContainsString('Estados de cuenta', $texto);
        $this->assertStringContainsString('3 personas', $texto);
        $this->assertStringContainsString('Índice', $texto);
        foreach (['Ana Mora', 'Beto Rojas', 'Carla Solano'] as $nombre) {
            $this->assertStringContainsString($nombre, $texto);
        }
        // Numeros de pagina del indice: Ana 3, Beto 4, Carla 5.
        $this->assertMatchesRegularExpression('/Ana Mora\n3\n/', $texto);
        $this->assertMatchesRegularExpression('/Carla Solano\n5\n/', $texto);
        // Cifras de Ana (enero a marzo): compromisos ₡71,000.00, dado en compromisos ₡38,825.50.
        $this->assertStringContainsString('₡71,000.00', $texto);
        $this->assertStringContainsString('₡38,825.50', $texto);
        $this->assertStringContainsString('-₡32,174.50', $texto);
        $this->assertStringContainsString('Página 5 de 5', $texto);

        $detalles = json_decode(AuditLog::where('action', AuditoriaReportes::ACCION)->sole()->details, true);
        $this->assertSame(['estados-de-cuenta', 'pdf', 3], [$detalles['reporte'], $detalles['formato'], $detalles['personas']]);
    }

    public function test_el_zip_trae_un_pdf_por_persona(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('reportes.estados-cuenta.zip', $this->parametros(['grupo' => 'con_promesa'])));

        $respuesta->assertOk();
        $this->assertStringContainsString('estados-de-cuenta_2026-01_2026-03.zip', $respuesta->headers->get('Content-Disposition'));

        $archivo = $respuesta->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivo));
        $nombres = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }
        $this->assertSame(['estado-cuenta_ana-mora_2026-01_2026-03.pdf', 'estado-cuenta_carla-solano_2026-01_2026-03.pdf'], $nombres);
        $texto = $this->textoDelPdf($zip->getFromName('estado-cuenta_ana-mora_2026-01_2026-03.pdf'));
        $zip->close();
        $this->assertStringContainsString('Estado de cuenta - Ana Mora', $texto);
        $this->assertStringContainsString('₡71,000.00', $texto);

        $this->assertSame('zip', json_decode(AuditLog::where('action', AuditoriaReportes::ACCION)->sole()->details, true)['formato']);
    }

    public function test_el_zip_tiene_tope_y_avisa(): void
    {
        $this->actingAs($this->admin);
        $tope = EstadoCuentaPdf::MAX_ZIP;
        for ($i = 0; $i < $tope - 2; $i++) {
            $this->crearPersona('Persona '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), null);
        }

        $this->get(route('reportes.estados-cuenta.zip', $this->parametros()))
            ->assertRedirect(route('reportes.estados-cuenta', $this->parametros()))
            ->assertSessionHas('error');
        $this->assertSame(0, AuditLog::where('action', AuditoriaReportes::ACCION)->count());
    }

    public function test_el_excel_tiene_resumen_compromisos_y_detalle(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('reportes.estados-cuenta.excel', $this->parametros()));

        $respuesta->assertOk();
        $this->assertStringContainsString('estados-de-cuenta_2026-01_2026-03.xlsx', $respuesta->headers->get('Content-Disposition'));
        $libro = $this->leerExcel($respuesta->getContent());
        $this->assertSame(['Resumen', 'Compromisos', 'Detalle'], $libro->getSheetNames());

        $resumen = $libro->getSheetByName('Resumen');
        $encabezado = $resumen->getAutoFilter()->getRange();
        $this->assertSame('A6:M9', $encabezado);
        // Nombre | Diezmo | Misiones | Seminario | Construccion | CAMPA | Micro | Pro Templo | Total | Esperado | Dado en compromisos | Diferencia | Estado
        $this->assertSame('Pro Templo', $resumen->getCell('H6')->getValue());
        $this->assertSame('Ana Mora', $resumen->getCell('A7')->getValue());
        $this->assertEquals(30200.5, $resumen->getCell('C7')->getValue());
        $this->assertEquals(71000, $resumen->getCell('J7')->getValue());
        $this->assertEquals(38825.5, $resumen->getCell('K7')->getValue());
        $this->assertSame('PENDIENTE', $resumen->getCell('M7')->getValue());
        $this->assertSame('SIN PROMESA', $resumen->getCell('M8')->getValue());
        $this->assertSame('TOTAL', $resumen->getCell('A10')->getValue());

        $compromisos = $libro->getSheetByName('Compromisos');
        $this->assertSame(['Ana Mora', 'Misiones', '₡10,000.00 mensual'], [$compromisos->getCell('A2')->getValue(), $compromisos->getCell('B2')->getValue(), $compromisos->getCell('C2')->getValue()]);

        $detalle = $libro->getSheetByName('Detalle');
        $usd = null;
        for ($fila = 2; $fila <= $detalle->getHighestRow(); $fila++) {
            if ($detalle->getCell('G'.$fila)->getValue() === 'USD' && $detalle->getCell('E'.$fila)->getValue() === 'Misiones') {
                $usd = $fila;
            }
        }
        $this->assertNotNull($usd);
        $this->assertSame('08/02/2026', $detalle->getCell('B'.$usd)->getFormattedValue());
        $this->assertEquals(20, $detalle->getCell('F'.$usd)->getValue());
        $this->assertEquals(510, $detalle->getCell('H'.$usd)->getValue());
        $this->assertEquals(10200, $detalle->getCell('I'.$usd)->getValue());
    }

    public function test_solo_admin_y_tesorero(): void
    {
        $this->actingAs($this->crearUsuarioConRol('tesorero'))->get(route('reportes.estados-cuenta'))->assertOk();
        $this->actingAs($this->crearUsuarioConRol('asistente'))->get(route('reportes.estados-cuenta.pdf'))->assertForbidden();
        $this->actingAs($this->crearUsuarioConRol('miembro'))->get(route('reportes.estados-cuenta.excel'))->assertForbidden();
    }

    public function test_un_periodo_invalido_vuelve_a_la_pantalla_con_el_motivo(): void
    {
        $parametros = ['periodo' => 'meses', 'anio' => 2026, 'mes_desde' => 9, 'mes_hasta' => 2];

        $this->actingAs($this->admin)->get(route('reportes.estados-cuenta.pdf', $parametros))
            ->assertRedirect(route('reportes.estados-cuenta', $parametros));
        $this->actingAs($this->admin)->get(route('reportes.estados-cuenta', $parametros))
            ->assertOk()
            ->assertSee('va después del mes final');
    }
}

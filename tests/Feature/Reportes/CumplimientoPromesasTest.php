<?php

namespace Tests\Feature\Reportes;

use App\Models\AuditLog;
use App\Models\Ministerio;
use App\Models\Persona;
use App\Services\Reportes\AuditoriaReportes;
use App\Services\Reportes\CumplimientoPromesasService;
use App\Services\Reportes\EstadoCuenta;
use App\Services\Reportes\FilaCumplimiento;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Reportes\ReporteCumplimiento;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\Concerns\LeeReportes;
use Tests\TestCase;

/**
 * Reporte "Cumplimiento de promesas con corte" (HU-R13): reglas del listado del 12/10, filtros,
 * pantalla, PDF, Excel, permisos y auditoria. Hoy simulado: 13/10/2026; escenario en
 * CreaEscenarioReportes, mas:
 *
 *   Elena   misiones ₡1.000 mensual; da ₡12.000 en julio          -> AL DÍA, adelantado
 *   Fabio   misiones ₡2.000 mensual; da ₡18.000 en septiembre     -> AL DÍA justo (sin observacion)
 *   Gabriel misiones ₡1.000 mensual; da ₡1.000 cada mes salvo jul -> PENDIENTE, sin aportes en Julio
 *   Hilda   misiones ₡3.000 mensual; da ₡1.000 todos los meses    -> PENDIENTE, aporta pero menos
 *   Ivan    activo sin promesa ni aportes                         -> no se lista (sin movimiento)
 */
class CumplimientoPromesasTest extends TestCase
{
    use CreaEscenarioReportes;
    use LeeReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->crearEscenarioReportes();

        $elena = $this->crearPersona('Elena Vargas', '8000-0001');
        $this->promesa($elena, 'misiones', 1000, 'mensual');
        $this->crearSobre($this->crearCulto('2026-07-05'), $elena, ['misiones' => 12000]);

        $fabio = $this->crearPersona('Fabio Quesada', '8000-0002');
        $this->promesa($fabio, 'misiones', 2000, 'mensual');
        $this->crearSobre($this->crearCulto('2026-09-13'), $fabio, ['misiones' => 18000]);

        $gabriel = $this->crearPersona('Gabriel Ureña', '8000-0003');
        $this->promesa($gabriel, 'misiones', 1000, 'mensual');
        foreach ([1, 2, 3, 4, 5, 6, 8, 9] as $mes) {
            $this->crearSobre($this->crearCulto(sprintf('2026-%02d-20', $mes)), $gabriel, ['misiones' => 1000]);
        }

        $hilda = $this->crearPersona('Hilda Jiménez', '8000-0004');
        $this->promesa($hilda, 'misiones', 3000, 'mensual');
        foreach (range(1, 9) as $mes) {
            $this->crearSobre($this->crearCulto(sprintf('2026-%02d-21', $mes)), $hilda, ['misiones' => 1000]);
        }

        $this->crearPersona('Ivan Sin Nada', '8000-0005');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function generar(string $corte = '2026-10-13', ?FiltroPersonas $filtro = null): ReporteCumplimiento
    {
        $this->actingAs($this->admin);

        return app(CumplimientoPromesasService::class)->generar(PeriodoReporte::alCorte($corte), $filtro ?? FiltroPersonas::todas());
    }

    /** @return array<string, FilaCumplimiento> */
    private function porNombre(ReporteCumplimiento $reporte): array
    {
        $filas = [];
        foreach ($reporte->filas as $fila) {
            $filas[$fila->nombre] = $fila;
        }

        return $filas;
    }

    // ------------------------------------------------------------------ reglas

    public function test_reglas_del_listado_estado_observacion_y_ultimo_aporte(): void
    {
        $filas = $this->porNombre($this->generar());

        $ana = $filas['Ana Mora'];
        $this->assertSame(EstadoCuenta::PENDIENTE, $ana->estado);
        $this->assertEquals(213000, $ana->leCorresponde());
        // Ha dado: solo categorias con promesa (sin diezmo y sin los 3.000 de campa, que no prometio).
        $this->assertEquals(58826.25, $ana->haDado());
        $this->assertEquals(-154173.75, $ana->diferencia());
        $this->assertSame('04/10/2026', $ana->ultimoAporteTexto());
        $this->assertSame('sin aportes en 5 meses (Abril a Septiembre)', $ana->observacion);
        $this->assertSame('Misiones ₡10,000.00 mensual · Seminario $5.00 cada quincena · Construccion ₡2,000.00 cada domingo', $ana->promesa);

        $this->assertSame('sin aportes en 6 meses (Febrero a Agosto)', $filas['Carla Solano']->observacion);
        $this->assertSame('sin aportes en Julio', $filas['Gabriel Ureña']->observacion);
        $this->assertSame('aporta, pero menos de lo prometido', $filas['Hilda Jiménez']->observacion);

        $this->assertSame(EstadoCuenta::AL_DIA, $filas['Elena Vargas']->estado);
        $this->assertSame('adelantado', $filas['Elena Vargas']->observacion);
        $this->assertEquals(3000, $filas['Elena Vargas']->diferencia());

        // Justo lo que correspondia: al dia y sin observacion.
        $this->assertSame(EstadoCuenta::AL_DIA, $filas['Fabio Quesada']->estado);
        $this->assertEquals(0, $filas['Fabio Quesada']->diferencia());
        $this->assertSame('', $filas['Fabio Quesada']->observacion);

        $this->assertSame(EstadoCuenta::SIN_PROMESA, $filas['Beto Rojas']->estado);
        $this->assertEquals(6234.56, $filas['Beto Rojas']->haDado());
        $this->assertSame('02/08/2026', $filas['Beto Rojas']->ultimoAporteTexto());
    }

    public function test_orden_pendientes_al_dia_y_sin_promesa_por_nombre_sin_tildes(): void
    {
        $reporte = $this->generar();

        $this->assertSame(
            ['Ana Mora', 'Carla Solano', 'Gabriel Ureña', 'Hilda Jiménez', 'Elena Vargas', 'Fabio Quesada', 'Beto Rojas'],
            array_map(fn (FilaCumplimiento $f) => $f->nombre, $reporte->filas),
        );
        // Ni la inactiva, ni la de otra iglesia, ni quien no tiene promesa ni aportes (se cuenta aparte).
        $this->assertSame(1, $reporte->sinMovimiento);
    }

    public function test_resumen_como_el_listado_del_pastor(): void
    {
        $resumen = $this->generar()->resumen();

        $this->assertSame(6, $resumen['con_promesa']);
        $this->assertSame(2, $resumen['al_dia']);
        $this->assertSame(4, $resumen['pendientes']);
        $this->assertSame(1, $resumen['sin_promesa']);
        // Le correspondia: Ana 213.000 + Carla 54.000 + Gabriel 9.000 + Hilda 27.000 + Elena 9.000 + Fabio 18.000
        $this->assertEquals(330000, $resumen['le_correspondia']);
        // Ha dado (con promesa): 58.826,25 + 13.000,10 + 8.000 + 9.000 + 12.000 + 18.000
        $this->assertEquals(118826.35, $resumen['ha_dado']);
        // Faltante de los pendientes: 154.173,75 + 40.999,90 + 1.000 + 18.000
        $this->assertEquals(214173.65, $resumen['faltante']);
        $this->assertEquals(6234.56, $resumen['dado_sin_promesa']);
    }

    public function test_si_el_corte_es_fin_de_mes_ese_mes_se_cobra(): void
    {
        $filas = $this->porNombre($this->generar('2026-09-30'));

        // Enero a septiembre igual que con el corte al 13/10, pero lo de octubre no se ha dado todavia.
        $this->assertEquals(213000, $filas['Ana Mora']->leCorresponde());
        $this->assertEquals(58826.25 - 8000.75, $filas['Ana Mora']->haDado());
        $this->assertStringContainsString('de enero a septiembre.', $this->generar('2026-09-30')->explicacion());
    }

    public function test_la_explicacion_dice_que_meses_se_cobran(): void
    {
        $reporte = $this->generar();

        $this->assertStringContainsString('Le correspondía = lo prometido de enero a septiembre (octubre está en curso y no se cobra)', $reporte->explicacion());
        $this->assertSame('Le correspondía (ene-sep)', $reporte->tituloCorrespondia());
    }

    // ------------------------------------------------------------------ filtros

    public function test_filtro_con_promesa(): void
    {
        $nombres = array_map(fn ($f) => $f->nombre, $this->generar(filtro: FiltroPersonas::desde(['grupo' => 'con_promesa']))->filas);

        $this->assertNotContains('Beto Rojas', $nombres);
        $this->assertCount(6, $nombres);
    }

    public function test_filtro_por_ministerio_o_cualquier_ministerio(): void
    {
        $alabanza = Ministerio::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Alabanza']);
        $ujieres = Ministerio::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Ujieres']);
        $this->ana->ministerios()->attach($alabanza->id, ['desde' => '2026-01-01']);
        $this->beto->ministerios()->attach($ujieres->id, ['desde' => '2026-01-01']);
        // Carla ya no sirve en Alabanza.
        $this->carla->ministerios()->attach($alabanza->id, ['desde' => '2025-01-01', 'hasta' => '2026-06-30']);

        $this->actingAs($this->admin);
        $filtro = FiltroPersonas::desde(['grupo' => 'ministerio', 'ministerio' => (string) $alabanza->id]);
        $this->assertSame('Ministerio: Alabanza', $filtro->descripcion());
        $this->assertSame(['Ana Mora'], array_map(fn ($f) => $f->nombre, $this->generar(filtro: $filtro)->filas));

        $servidores = FiltroPersonas::desde(['grupo' => 'ministerio', 'ministerio' => 'todos']);
        $this->assertSame(['Ana Mora', 'Beto Rojas'], array_map(fn ($f) => $f->nombre, $this->generar(filtro: $servidores)->filas));
    }

    public function test_seleccion_de_personas_aunque_esten_inactivas(): void
    {
        $diego = Persona::where('nombre', 'Diego Inactivo')->firstOrFail();
        $this->actingAs($this->admin);
        $filtro = FiltroPersonas::desde(['personas' => $this->beto->id.','.$diego->id]);

        $this->assertSame(FiltroPersonas::SELECCION, $filtro->grupo);
        $this->assertSame(['Diego Inactivo', 'Beto Rojas'], array_map(fn ($f) => $f->nombre, $this->generar(filtro: $filtro)->filas));
    }

    public function test_una_persona_de_otra_iglesia_no_entra_aunque_se_pida(): void
    {
        $ajena = Persona::withoutGlobalScopes()->where('nombre', 'Zoe Ajena')->firstOrFail();
        $this->actingAs($this->admin);

        $this->assertSame([], $this->generar(filtro: FiltroPersonas::desde(['personas' => (string) $ajena->id]))->filas);
    }

    // ------------------------------------------------------------------ pantalla, permisos y descargas

    public function test_la_pantalla_muestra_resumen_y_tabla(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('reportes.cumplimiento'));

        $respuesta->assertOk()
            ->assertSee('Cumplimiento de promesas')
            ->assertSee('Le correspondía (ene-sep)')
            ->assertSee('Ana Mora')
            ->assertSee('sin aportes en 5 meses (Abril a Septiembre)')
            ->assertSee('-₡154,174')
            ->assertSee('Corte al 13/10/2026')
            ->assertDontSee('Zoe Ajena')
            // Ivan (sin promesa ni aportes) no va en la tabla; solo se cuenta.
            ->assertSee('No se listan 1 persona sin promesa y sin aportes en el periodo.')
            ->assertSee(e(route('reportes.cumplimiento.excel', ['corte' => '2026-10-13', 'grupo' => 'todas'])), false);
    }

    public function test_tesorero_entra_y_miembro_o_asistente_no(): void
    {
        $this->actingAs($this->crearUsuarioConRol('tesorero'))->get(route('reportes.cumplimiento'))->assertOk();
        $this->actingAs($this->crearUsuarioConRol('asistente'))->get(route('reportes.cumplimiento'))->assertForbidden();
        $this->actingAs($this->crearUsuarioConRol('miembro'))->get(route('reportes.cumplimiento.pdf'))->assertForbidden();
    }

    public function test_sin_sesion_va_al_login(): void
    {
        $this->get(route('reportes.cumplimiento.excel'))->assertRedirect(route('login'));
    }

    public function test_un_corte_en_el_futuro_no_rompe_la_pantalla_y_explica_por_que(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reportes.cumplimiento', ['corte' => '2026-12-31']))
            ->assertOk()
            ->assertSee('no puede ser posterior a hoy')
            ->assertSee('Corte al 13/10/2026');

        $this->actingAs($this->admin)
            ->get(route('reportes.cumplimiento.pdf', ['corte' => '2026-12-31']))
            ->assertRedirect(route('reportes.cumplimiento', ['corte' => '2026-12-31']));
        $this->assertSame(0, AuditLog::where('action', AuditoriaReportes::ACCION)->count());
    }

    public function test_el_pdf_trae_el_listado_y_queda_auditado(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('reportes.cumplimiento.pdf', ['grupo' => 'todas', 'accion' => 'descargar']));

        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $respuesta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('cumplimiento-promesas_corte_2026-10-13.pdf', $respuesta->headers->get('Content-Disposition'));

        $texto = $this->textoDelPdf($respuesta->getContent());
        $this->assertStringContainsString('Cumplimiento de promesas - corte al 13/10/2026', $texto);
        $this->assertStringContainsString('Pendientes (4)', $texto);
        $this->assertStringContainsString('Al día (2)', $texto);
        $this->assertStringContainsString('Aportan sin promesa registrada (1)', $texto);
        $this->assertStringContainsString('Beto Rojas (₡6,235)', $texto);
        $this->assertStringContainsString('-₡154,174', $texto);
        $this->assertStringContainsString('₡330,000', $texto);
        $this->assertStringContainsString('Página 1 de 1', $texto);

        $auditoria = AuditLog::where('action', AuditoriaReportes::ACCION)->sole();
        $this->assertSame($this->iglesia->id, $auditoria->tenant_id);
        $this->assertSame($this->admin->id, $auditoria->user_id);
        $detalles = json_decode($auditoria->details, true);
        $this->assertSame('cumplimiento-promesas', $detalles['reporte']);
        $this->assertSame('pdf', $detalles['formato']);
        $this->assertSame(7, $detalles['personas']);
        $this->assertSame('2026-10-13', $detalles['periodo']['corte']);
        $this->assertSame('Todas las personas activas', $detalles['filtro']);
    }

    public function test_ver_el_pdf_lo_abre_en_linea(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('reportes.cumplimiento.pdf', ['accion' => 'ver']));

        $respuesta->assertOk();
        $this->assertStringContainsString('inline', $respuesta->headers->get('Content-Disposition'));
    }

    public function test_el_excel_tiene_resumen_mes_a_mes_y_sin_promesa(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('reportes.cumplimiento.excel'));

        $respuesta->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('cumplimiento-promesas_corte_2026-10-13.xlsx', $respuesta->headers->get('Content-Disposition'));

        $libro = $this->leerExcel($respuesta->getContent());
        $this->assertSame(['Resumen', 'Mes a mes', 'Sin promesa'], $libro->getSheetNames());

        $resumen = $libro->getSheetByName('Resumen');
        $this->assertSame('Cumplimiento de promesas - Corte al 13/10/2026', $resumen->getCell('A2')->getValue());
        // Tarjetas (filas 6 y 7) y tabla desde la fila 9.
        $this->assertSame('Personas con promesa', $resumen->getCell('A6')->getValue());
        $this->assertEquals(6, $resumen->getCell('A7')->getValue());
        $this->assertEquals(330000, $resumen->getCell('D7')->getValue());
        $this->assertSame('Le correspondía (ene-sep)', $resumen->getCell('C9')->getValue());
        $this->assertSame('Ana Mora', $resumen->getCell('B10')->getValue());
        $this->assertEquals(213000, $resumen->getCell('C10')->getValue());
        $this->assertEquals(58826.25, $resumen->getCell('D10')->getValue());
        $this->assertEquals(-154173.75, $resumen->getCell('E10')->getValue());
        $this->assertSame('PENDIENTE', $resumen->getCell('F10')->getValue());
        $this->assertSame('04/10/2026', $resumen->getCell('G10')->getFormattedValue());
        $this->assertSame('"₡"#,##0;[Red]-"₡"#,##0', $resumen->getStyle('C10')->getNumberFormat()->getFormatCode());
        $this->assertSame('TOTAL', $resumen->getCell('B16')->getValue());
        $this->assertEquals(330000, $resumen->getCell('C16')->getValue());
        $this->assertSame('A9:I15', $resumen->getAutoFilter()->getRange());
        $this->assertSame('A10', $resumen->getFreezePane());
        // AL DÍA / PENDIENTE con color por formato condicional.
        $this->assertCount(2, $resumen->getConditionalStyles('F10:F15'));

        $mesAMes = $libro->getSheetByName('Mes a mes');
        $this->assertSame('Enero dado', $mesAMes->getCell('C1')->getValue());
        $this->assertSame('Octubre prometido', $mesAMes->getCell('V1')->getValue());
        $this->assertSame('Ana Mora', $mesAMes->getCell('A2')->getValue());
        $this->assertEquals(10000.5, $mesAMes->getCell('C2')->getValue());
        $this->assertEquals(23000, $mesAMes->getCell('M2')->getValue());
        $this->assertSame('C2', $mesAMes->getFreezePane());
        $amarillo = $mesAMes->getConditionalStyles('P2:P8');
        $this->assertCount(1, $amarillo);
        $this->assertSame(Conditional::CONDITION_EXPRESSION, $amarillo[0]->getConditionType());
        $this->assertSame(['AND(P2>0,F2=0)'], $amarillo[0]->getConditions());

        $sinPromesa = $libro->getSheetByName('Sin promesa');
        $this->assertSame('Beto Rojas', $sinPromesa->getCell('A2')->getValue());
        $this->assertEquals(6234.56, $sinPromesa->getCell('B2')->getValue());

        $this->assertSame('excel', json_decode(AuditLog::where('action', AuditoriaReportes::ACCION)->sole()->details, true)['formato']);
    }

    public function test_un_nombre_que_empieza_con_igual_no_se_vuelve_formula_en_el_excel(): void
    {
        $tramposa = $this->crearPersona('=HYPERLINK("http://x","clic")', '8000-0009');
        $this->crearSobre($this->crearCulto('2026-05-03'), $tramposa, ['misiones' => 100]);

        $libro = $this->leerExcel($this->actingAs($this->admin)->get(route('reportes.cumplimiento.excel'))->getContent());

        $celda = $libro->getSheetByName('Sin promesa')->getCell('A2');
        $this->assertSame('=HYPERLINK("http://x","clic")', $celda->getValue());
        $this->assertFalse($celda->isFormula());
    }
}

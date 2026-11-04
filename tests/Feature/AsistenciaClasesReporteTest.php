<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\ClaseAsistencia;
use App\Services\ReporteAsistenciaClasesService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreaEscenarioAsistencia;
use Tests\TestCase;

/**
 * Reporte de asistencia por clase en un rango de fechas (pantalla, PDF, CSV y Excel) con promedio.
 *
 * Datos de prueba (rango 01/08/2026 - 30/09/2026), clase A = «Clase 2-6» y clase B = «Clase 7-8»:
 *
 *   fecha        culto              clase A (niños, niñas, maestros)   clase B
 *   2026-08-02   domingo            10, 5, 1+1 maestras = 17           4, 6, 1 = 11
 *   2026-08-09   domingo             8, 7, 2 = 17                      (sin detalle de B: no cuenta para B)
 *   2026-08-16   domingo            12, 3, 1 = 16                      6, 4, 0 = 10
 *   2026-08-23   domingo            (el culto no tiene asistencia)
 *   2026-08-30   domingo            (asistencia solo de capilla, sin detalle de clases)
 *   2026-09-06   miércoles           0, 0, 0 en A y B (el formulario guarda ceros en todas las clases)
 *   2026-09-13   domingo + PM        5, 5, 1 + 1, 2, 0 = 6, 7, 1 = 14  (dos cultos el mismo día: una sola fila)
 *
 *   Clase A: 4 fechas, totales 36/22/6/64, promedios 9,00/5,50/1,50/16,00
 *   Clase B: 2 fechas, totales 10/10/1/21, promedios 5,00/5,00/0,50/10,50
 *   General: 4 fechas con registro, totales 46/32/7/85, promedios 11,50/8,00/1,75/21,25
 */
class AsistenciaClasesReporteTest extends TestCase
{
    use CreaEscenarioAsistencia;
    use RefreshDatabase;

    private ClaseAsistencia $claseA;

    private ClaseAsistencia $claseB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearIglesiaConAdmin();
        $this->claseA = $this->crearClase('Clase 2-6 Años', 'clase_2_6', 1);
        $this->claseB = $this->crearClase('Clase 7-8 Años', 'clase_7_8', 2);

        $this->cargarEscenario();
    }

    private function cargarEscenario(): void
    {
        $d1 = $this->crearCulto('2026-08-02');
        $this->registrarClase($d1, $this->claseA, 10, 5, 1, 1);
        $this->registrarClase($d1, $this->claseB, 4, 6, 1);

        $this->registrarClase($this->crearCulto('2026-08-09'), $this->claseA, 8, 7, 2);

        $d3 = $this->crearCulto('2026-08-16');
        $this->registrarClase($d3, $this->claseA, 12, 3, 1);
        $this->registrarClase($d3, $this->claseB, 6, 4, 0);

        $this->crearCulto('2026-08-23'); // culto sin asistencia

        $solaCapilla = $this->crearCulto('2026-08-30');
        Asistencia::create(['culto_id' => $solaCapilla->id, 'tenant_id' => $this->iglesia->id, 'chapel_adultos_hombres' => 40, 'total_asistencia' => 40]);

        $miercoles = $this->crearCulto('2026-09-06', 'miércoles');
        $this->registrarClase($miercoles, $this->claseA, 0, 0, 0);
        $this->registrarClase($miercoles, $this->claseB, 0, 0, 0);

        $this->registrarClase($this->crearCulto('2026-09-13'), $this->claseA, 5, 5, 1);
        $this->registrarClase($this->crearCulto('2026-09-13', 'domingo_pm'), $this->claseA, 1, 2, 0);

        // Fuera del rango consultado: no deben contar
        $this->registrarClase($this->crearCulto('2026-07-26'), $this->claseA, 100, 100, 100);
        $this->registrarClase($this->crearCulto('2026-10-04'), $this->claseB, 100, 100, 100);

        // La capilla no es una clase de niños
        $capilla = $this->crearClase('Capilla', 'capilla', 0);
        $this->registrarClase($d1, $capilla, 50, 50, 0);

        // Datos de otra iglesia en las mismas fechas: no deben mezclarse
        $otra = $this->crearOtraIglesia();
        $claseOtra = $this->crearClase('Clase de otra iglesia', 'clase_otra', 1, ['tenant_id' => $otra->id]);
        $this->registrarClase($this->crearCulto('2026-08-02', 'domingo', $otra->id), $claseOtra, 77, 77, 7);
    }

    private function generar(bool $incluirCeros = false, ?int $claseId = null, string $desde = '2026-08-01', string $hasta = '2026-09-30'): array
    {
        $this->actingAs($this->admin);

        return app(ReporteAsistenciaClasesService::class)->generar(Carbon::parse($desde), Carbon::parse($hasta), $claseId, $incluirCeros);
    }

    private function parametros(array $extra = []): array
    {
        return array_merge(['desde' => '2026-08-01', 'hasta' => '2026-09-30'], $extra);
    }

    /** Contenido de una descarga (CSV o Excel), leído antes de que el framework borre el archivo temporal. */
    private function descarga(TestResponse $respuesta): string
    {
        return file_get_contents($respuesta->baseResponse->getFile()->getPathname());
    }

    public function test_calcula_totales_y_promedios_por_clase_y_en_general(): void
    {
        $reporte = $this->generar();

        $this->assertCount(2, $reporte['clases']);

        [$a, $b] = $reporte['clases'];
        $this->assertSame('Clase 2-6 Años', $a['nombre']);
        $this->assertSame(4, $a['fechas']);
        $this->assertSame(['ninos' => 36, 'ninas' => 22, 'maestros' => 6, 'total' => 64], $a['totales']);
        $this->assertSame(['ninos' => 9.0, 'ninas' => 5.5, 'maestros' => 1.5, 'total' => 16.0], $a['promedios']);

        $this->assertSame('Clase 7-8 Años', $b['nombre']);
        $this->assertSame(2, $b['fechas']);
        $this->assertSame(['ninos' => 10, 'ninas' => 10, 'maestros' => 1, 'total' => 21], $b['totales']);
        $this->assertSame(['ninos' => 5.0, 'ninas' => 5.0, 'maestros' => 0.5, 'total' => 10.5], $b['promedios']);

        $general = $reporte['general'];
        $this->assertSame(4, $general['fechas']);
        $this->assertSame(['ninos' => 46, 'ninas' => 32, 'maestros' => 7, 'total' => 85], $general['totales']);
        $this->assertSame(['ninos' => 11.5, 'ninas' => 8.0, 'maestros' => 1.75, 'total' => 21.25], $general['promedios']);
    }

    public function test_cada_fila_suma_ninos_ninas_y_maestros_incluidas_las_maestras(): void
    {
        $fila = $this->generar()['clases'][0]['filas'][0];

        $this->assertSame('2026-08-02', $fila['fecha']->toDateString());
        $this->assertSame(10, $fila['ninos']);
        $this->assertSame(5, $fila['ninas']);
        $this->assertSame(2, $fila['maestros']); // 1 maestro + 1 maestra
        $this->assertSame(17, $fila['total']);
    }

    public function test_las_fechas_sin_registro_no_bajan_el_promedio(): void
    {
        $reporte = $this->generar();
        [$a, $b] = $reporte['clases'];

        // Clase A tiene 4 fechas con registro aunque en el rango hay 7 fechas de culto
        $this->assertSame(4, $a['fechas']);
        // Clase B no tiene detalle el 09/08, ni el 13/09: su promedio es sobre 2 fechas, no sobre 4
        $this->assertSame(2, $b['fechas']);
        $this->assertSame(10.5, $b['promedios']['total']);
        $this->assertNotSame(round(21 / 4, 2), $b['promedios']['total']);

        // Culto sin asistencia, asistencia solo de capilla y miércoles en ceros: sin registro de clases
        $sinRegistro = array_map(fn (Carbon $fecha) => $fecha->toDateString(), $reporte['fechas_sin_registro']);
        $this->assertSame(['2026-08-23', '2026-08-30', '2026-09-06'], $sinRegistro);
        $this->assertSame(8, $reporte['cultos_en_rango']);
    }

    public function test_las_fechas_en_cero_se_ignoran_salvo_que_se_pida_contarlas(): void
    {
        $normal = $this->generar();
        $conCeros = $this->generar(incluirCeros: true);

        $this->assertSame(4, $normal['clases'][0]['fechas']);
        $this->assertSame(5, $conCeros['clases'][0]['fechas']);
        $this->assertSame(3, $conCeros['clases'][1]['fechas']);

        // Mismos totales, promedio más bajo porque el miércoles en 0 ahora cuenta
        $this->assertSame(64, $conCeros['clases'][0]['totales']['total']);
        $this->assertSame(12.8, $conCeros['clases'][0]['promedios']['total']); // 64 / 5
        $this->assertSame(7.0, $conCeros['clases'][1]['promedios']['total']); // 21 / 3
        $this->assertSame(5, $conCeros['general']['fechas']);
        $this->assertSame(17.0, $conCeros['general']['promedios']['total']); // 85 / 5
        $this->assertSame([], array_filter($conCeros['fechas_sin_registro'], fn (Carbon $f) => $f->toDateString() === '2026-09-06'));
    }

    public function test_dos_cultos_el_mismo_dia_se_suman_en_una_fila_y_cuentan_como_una_fecha(): void
    {
        $filas = $this->generar()['clases'][0]['filas'];
        $fila = collect($filas)->first(fn ($f) => $f['fecha']->toDateString() === '2026-09-13');

        $this->assertSame(6, $fila['ninos']);
        $this->assertSame(7, $fila['ninas']);
        $this->assertSame(1, $fila['maestros']);
        $this->assertSame(14, $fila['total']);
        $this->assertSame('Domingo AM + Domingo PM', $fila['cultos']);
        $this->assertCount(4, $filas);
    }

    public function test_respeta_el_rango_y_deja_los_limites_dentro(): void
    {
        // El 02/08 y el 16/08 de la clase B quedan dentro justo en los bordes del rango
        $reporte = $this->generar(desde: '2026-08-02', hasta: '2026-08-16');

        $this->assertSame(3, $reporte['clases'][0]['fechas']); // 02/08, 09/08, 16/08
        $this->assertSame(2, $reporte['clases'][1]['fechas']); // 02/08, 16/08
        $this->assertSame(3, $reporte['general']['fechas']);

        $vacio = $this->generar(desde: '2026-01-01', hasta: '2026-01-31');
        $this->assertSame([], $vacio['clases']);
        $this->assertSame(0, $vacio['general']['fechas']);
        $this->assertSame(0.0, $vacio['general']['promedios']['total']);
    }

    public function test_filtra_por_clase(): void
    {
        $reporte = $this->generar(claseId: $this->claseB->id);

        $this->assertCount(1, $reporte['clases']);
        $this->assertSame('Clase 7-8 Años', $reporte['clase_nombre']);
        $this->assertSame(2, $reporte['general']['fechas']);
        $this->assertSame(21, $reporte['general']['totales']['total']);
    }

    public function test_no_incluye_la_capilla_ni_datos_de_otra_iglesia(): void
    {
        $reporte = $this->generar();

        $nombres = array_column($reporte['clases'], 'nombre');
        $this->assertNotContains('Capilla', $nombres);
        $this->assertNotContains('Clase de otra iglesia', $nombres);
        $this->assertSame(85, $reporte['general']['totales']['total']);
    }

    public function test_las_clases_inactivas_con_historia_aparecen_en_el_reporte(): void
    {
        $vieja = $this->crearClase('Clase antigua', 'clase_antigua', 9, ['activa' => false]);
        $this->registrarClase($this->crearCulto('2026-08-20', 'especial'), $vieja, 3, 4, 0);

        $reporte = $this->generar();

        $this->assertSame('Clase antigua', $reporte['clases'][2]['nombre']);
        $this->assertFalse($reporte['clases'][2]['activa']);
        $this->assertSame(7, $reporte['clases'][2]['totales']['total']);
        $this->assertSame(5, $reporte['general']['fechas']); // 4 fechas + el 20/08
    }

    public function test_la_pantalla_el_pdf_el_csv_y_el_excel_responden_200(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('ingresos-asistencia.asistencia-clases', $this->parametros()))
            ->assertOk()
            ->assertSee('Asistencia por clase')
            ->assertSee('Cómo se calcula el promedio')
            ->assertSee('Promedio Clase 2-6 Años', false)
            ->assertSee('16,00')
            ->assertSee('Promedio general');

        $pdf = $this->get(route('ingresos-asistencia.asistencia-clases.pdf', $this->parametros()))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString('asistencia_por_clase_2026-08-01_a_2026-09-30.pdf', $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $csv = $this->get(route('ingresos-asistencia.asistencia-clases.csv', $this->parametros()))->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $this->assertStringContainsString('asistencia_por_clase_2026-08-01_a_2026-09-30.csv', $csv->headers->get('Content-Disposition'));

        $excel = $this->get(route('ingresos-asistencia.asistencia-clases.excel', $this->parametros()))->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $excel->headers->get('Content-Type'));
        $this->assertStringContainsString('asistencia_por_clase_2026-08-01_a_2026-09-30.xlsx', $excel->headers->get('Content-Disposition'));
    }

    public function test_un_rango_sin_registros_tambien_se_puede_descargar(): void
    {
        $this->actingAs($this->admin);
        $vacio = ['desde' => '2026-01-01', 'hasta' => '2026-01-31'];

        $this->get(route('ingresos-asistencia.asistencia-clases', $vacio))
            ->assertOk()
            ->assertSee('No hay asistencia por clase registrada en este rango.');

        $pdf = $this->get(route('ingresos-asistencia.asistencia-clases.pdf', $vacio))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $csv = $this->descarga($this->get(route('ingresos-asistencia.asistencia-clases.csv', $vacio))->assertOk());
        $this->assertStringContainsString('Sin asistencia por clase registrada en este rango.', $csv);
        $this->assertStringNotContainsString('PROMEDIO', $csv);

        $this->get(route('ingresos-asistencia.asistencia-clases.excel', $vacio))->assertOk();
    }

    public function test_el_csv_trae_los_promedios_ya_calculados(): void
    {
        $contenido = $this->descarga(
            $this->actingAs($this->admin)->get(route('ingresos-asistencia.asistencia-clases.csv', $this->parametros()))->assertOk()
        );

        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido, 'El CSV lleva BOM para que Excel lea bien los acentos.');

        $lineas = preg_split('/\r\n|\n/', trim(substr($contenido, 3)));
        $this->assertSame('"Clase";"Fecha";"Culto";"Niños";"Niñas";"Maestros";"Total"', $lineas[0]);
        $this->assertContains('"Clase 2-6 Años";"02/08/2026";"Domingo AM";"10";"5";"2";"17"', $lineas);
        $this->assertContains('"Clase 2-6 Años";"13/09/2026";"Domingo AM + Domingo PM";"6";"7";"1";"14"', $lineas);
        $this->assertContains('"Clase 2-6 Años";"TOTAL (4 fechas)";"";"36";"22";"6";"64"', $lineas);
        $this->assertContains('"Clase 2-6 Años";"PROMEDIO";"por fecha con registro";"9,00";"5,50";"1,50";"16,00"', $lineas);
        $this->assertContains('"Clase 7-8 Años";"16/08/2026";"Domingo AM";"6";"4";"0";"10"', $lineas, 'El 0 de maestros no se pierde.');
        $this->assertContains('"Clase 7-8 Años";"PROMEDIO";"por fecha con registro";"5,00";"5,00";"0,50";"10,50"', $lineas);
        $this->assertContains('"TODAS LAS CLASES";"TOTAL GENERAL (4 fechas)";"";"46";"32";"7";"85"', $lineas);
        $this->assertContains('"TODAS LAS CLASES";"PROMEDIO GENERAL";"por fecha con registro";"11,50";"8,00";"1,75";"21,25"', $lineas);
    }

    public function test_el_excel_trae_los_promedios_como_numeros_y_los_ceros_visibles(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('ingresos-asistencia.asistencia-clases.excel', $this->parametros()))->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'asistencia').'.xlsx';
        file_put_contents($ruta, $this->descarga($respuesta));

        try {
            $hoja = IOFactory::load($ruta)->getActiveSheet();
        } finally {
            @unlink($ruta);
        }

        $filas = $hoja->toArray(null, false, false, false);

        // Título con el rango y la explicación del promedio, antes de la tabla
        $this->assertStringContainsString('del 01/08/2026 al 30/09/2026', $filas[1][0]);
        $this->assertStringContainsString('Las fechas sin registro no cuentan', $filas[3][0]);

        $indice = array_key_first(array_filter($filas, fn ($fila) => $fila[0] === 'Clase' && $fila[3] === 'Niños'));
        $tabla = array_slice($filas, $indice);

        $promedioA = collect($tabla)->first(fn ($f) => $f[0] === 'Clase 2-6 Años' && $f[1] === 'PROMEDIO');
        $this->assertSame([9.0, 5.5, 1.5, 16.0], array_map('floatval', array_slice($promedioA, 3, 4)));
        $this->assertIsNumeric($promedioA[6]);

        $general = collect($tabla)->first(fn ($f) => $f[1] === 'PROMEDIO GENERAL');
        $this->assertSame([11.5, 8.0, 1.75, 21.25], array_map('floatval', array_slice($general, 3, 4)));

        // El 0 de «maestros» de una fila de clase no se pierde como celda vacía
        $filaSinMaestros = collect($tabla)->first(fn ($f) => $f[0] === 'Clase 7-8 Años' && $f[1] === '16/08/2026');
        $this->assertSame([6, 4, 0, 10], array_slice($filaSinMaestros, 3, 4));
    }

    public function test_el_pdf_trae_el_promedio_calculado(): void
    {
        $this->actingAs($this->admin);

        $html = view('pdfs.asistencia-clases-rango', ['reporte' => $this->generar()])->render();

        $this->assertStringContainsString('PROMEDIO (total ÷ 4 fechas)', $html);
        $this->assertStringContainsString('16,00', $html);
        $this->assertStringContainsString('PROMEDIO GENERAL (total ÷ 4 fechas)', $html);
        $this->assertStringContainsString('21,25', $html);
        $this->assertStringContainsString('23/08/2026, 30/08/2026, 06/09/2026', $html);
    }

    public function test_sin_fechas_usa_los_ultimos_dos_meses(): void
    {
        Carbon::setTestNow('2026-10-11 10:00:00');

        try {
            $this->actingAs($this->admin)
                ->get(route('ingresos-asistencia.asistencia-clases'))
                ->assertOk()
                ->assertSee('value="2026-08-11"', false)
                ->assertSee('value="2026-10-11"', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_un_rango_invertido_o_una_clase_inexistente_muestran_el_error(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('ingresos-asistencia.asistencia-clases', ['desde' => '2026-09-30', 'hasta' => '2026-08-01']))
            ->assertOk()
            ->assertSee('La fecha «Hasta» no puede ser anterior a «Desde».');

        $this->get(route('ingresos-asistencia.asistencia-clases', $this->parametros(['clase_id' => 99999])))
            ->assertOk()
            ->assertSee('La clase elegida no existe.');

        // Las descargas no generan archivos con filtros inválidos: vuelven a la pantalla con el motivo
        $this->get(route('ingresos-asistencia.asistencia-clases.pdf', ['desde' => 'no-es-fecha', 'hasta' => '2026-08-01']))
            ->assertRedirect(route('ingresos-asistencia.asistencia-clases'))
            ->assertSessionHasErrors('desde');
    }

    public function test_la_clase_de_otra_iglesia_no_se_puede_elegir(): void
    {
        $claseOtra = ClaseAsistencia::withoutGlobalScopes()->where('slug', 'clase_otra')->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('ingresos-asistencia.asistencia-clases', $this->parametros(['clase_id' => $claseOtra->id])))
            ->assertOk()
            ->assertSee('La clase elegida no existe.');
    }

    public function test_entran_admin_tesorero_y_asistente_pero_no_el_miembro(): void
    {
        $url = route('ingresos-asistencia.asistencia-clases', $this->parametros());

        $this->get($url)->assertRedirect(route('login'));

        // Regla 2.5 de 04-DECISIONES.md: los reportes de asistencia tambien son del tesorero.
        $this->actingAs($this->crearUsuarioConRol('tesorero'))->get($url)->assertOk();
        $this->actingAs($this->crearUsuarioConRol('miembro'))->get($url)->assertForbidden();
        $this->actingAs($this->crearUsuarioConRol('asistente'))->get($url)->assertOk();
        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    public function test_los_formatos_de_descarga_respetan_la_clase_y_las_fechas_en_cero(): void
    {
        $this->actingAs($this->admin);

        $contenido = $this->descarga(
            $this->get(route('ingresos-asistencia.asistencia-clases.csv', $this->parametros(['clase_id' => $this->claseA->id, 'incluir_ceros' => 1])))->assertOk()
        );

        $this->assertStringContainsString('"Clase 2-6 Años";"TOTAL (5 fechas)";"";"36";"22";"6";"64"', $contenido);
        $this->assertStringContainsString('"Clase 2-6 Años";"PROMEDIO";"por fecha con registro";"7,20";"4,40";"1,20";"12,80"', $contenido);
        $this->assertStringNotContainsString('Clase 7-8 Años', $contenido);
        $this->assertStringNotContainsString('TODAS LAS CLASES', $contenido, 'Con una sola clase no se repite el general.');
    }
}

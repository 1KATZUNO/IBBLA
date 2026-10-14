<?php

namespace Tests\Feature\Dashboard;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConDatosDeDashboard;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Dashboard general (/dashboard) calculado sobre un rango de fechas: acceso rápido mes + año
 * (por defecto el mes actual) o fecha_inicio / fecha_fin.
 *
 * "Hoy" se fija en 15/10/2026 para que el mes actual sea octubre de 2026.
 */
class DashboardRangoFechasTest extends TestCase
{
    use ConDatosDeDashboard, ConIglesia, RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 09:00:00');

        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');

        // fuera del rango de septiembre-octubre
        $this->culto($this->iglesia, '2026-08-30', ['diezmo' => 1000, 'misiones' => 100, 'suelto' => 50], 50);
        // dentro
        $this->culto($this->iglesia, '2026-09-06', ['diezmo' => 3000, 'misiones' => 600, 'suelto' => 100], 100);
        $this->culto($this->iglesia, '2026-09-20', ['diezmo' => 2000, 'misiones' => 400, 'suelto' => 200], 120);
        $this->culto($this->iglesia, '2026-10-04', ['diezmo' => 4000, 'misiones' => 800, 'suelto' => 150], 140);
        // fuera
        $this->culto($this->iglesia, '2026-11-01', ['diezmo' => 9000, 'misiones' => 900, 'suelto' => 90], 999);
    }

    /** Etiqueta de mes como la arma el dashboard (en español Carbon pone punto: "sep. 26"). */
    private function mes(int $anio, int $mes): string
    {
        return \Carbon\Carbon::create($anio, $mes, 1)->locale('es')->translatedFormat('M y');
    }

    private function dashboard(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('dashboard', $query));
    }

    // ================================================================ el KPI sobre un rango

    public function test_un_kpi_calculado_sobre_un_rango_de_dos_meses_da_la_suma_correcta(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31'])->assertOk();

        $totales = $respuesta->viewData('totalesPeriodo');

        // Septiembre (3700 + 2600) + octubre (4950); agosto y noviembre quedan fuera.
        $this->assertEquals(11250, $totales['total_general']);
        $this->assertEquals(9000, $totales['diezmo']);
        $this->assertEquals(1800, $totales['misiones']);
        $this->assertEquals(450, $totales['total_suelto']);
        $this->assertSame(3, $respuesta->viewData('cultosEnRango'));

        // y es lo que se ve en pantalla
        $respuesta->assertSee('₡11,250');
    }

    public function test_los_extremos_del_rango_entran_completos(): void
    {
        // 6 de septiembre ... 4 de octubre: ambos cultos caen justo en el primer y el último día.
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-06', 'fecha_fin' => '2026-10-04']);

        $this->assertEquals(3700 + 2600 + 4950, $respuesta->viewData('totalesPeriodo')['total_general']);

        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-07', 'fecha_fin' => '2026-10-03']);
        $this->assertEquals(2600, $respuesta->viewData('totalesPeriodo')['total_general'], 'Sin los cultos de los extremos.');
    }

    public function test_un_rango_sin_cultos_da_ceros_sin_romper_la_pantalla(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-01-31'])->assertOk();

        $this->assertEquals(0, $respuesta->viewData('totalesPeriodo')['total_general']);
        $this->assertSame([], $respuesta->viewData('graficoCultos')['labels']);
        $this->assertEquals(0, $respuesta->viewData('comparativo')['porcentaje']);
    }

    public function test_los_cultos_de_otra_iglesia_no_cuentan(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        $this->culto($otra, '2026-09-13', ['diezmo' => 777777], 500);

        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31']);

        $this->assertEquals(11250, $respuesta->viewData('totalesPeriodo')['total_general']);
    }

    // ================================================================ acceso rápido mes + año

    public function test_mes_y_año_se_traducen_al_rango_de_ese_mes(): void
    {
        $respuesta = $this->dashboard(['mes' => 9, 'año' => 2026])->assertOk();

        $rango = $respuesta->viewData('rango');
        $this->assertSame('2026-09-01', $rango->inicio->toDateString());
        $this->assertSame('2026-09-30', $rango->fin->toDateString());
        $this->assertEquals(3700 + 2600, $respuesta->viewData('totalesPeriodo')['total_general']);
    }

    public function test_sin_parametros_muestra_el_mes_actual(): void
    {
        $respuesta = $this->dashboard()->assertOk();

        $rango = $respuesta->viewData('rango');
        $this->assertSame('2026-10-01', $rango->inicio->toDateString());
        $this->assertSame('2026-10-31', $rango->fin->toDateString());
        $this->assertEquals(4950, $respuesta->viewData('totalesPeriodo')['total_general']);
    }

    public function test_solo_el_año_o_mes_vacio_muestra_el_año_completo(): void
    {
        foreach ([['año' => 2026], ['mes' => '', 'año' => 2026]] as $query) {
            $respuesta = $this->dashboard($query)->assertOk();

            $this->assertSame('2026-01-01', $respuesta->viewData('rango')->inicio->toDateString());
            $this->assertSame('2026-12-31', $respuesta->viewData('rango')->fin->toDateString());
            // todo 2026 salvo nada: agosto + sept + oct + noviembre
            $this->assertEquals(1150 + 3700 + 2600 + 4950 + 9990, $respuesta->viewData('totalesPeriodo')['total_general']);
        }
    }

    public function test_el_mes_sin_año_usa_el_año_actual(): void
    {
        $respuesta = $this->dashboard(['mes' => 9]);

        $this->assertSame('2026-09-01', $respuesta->viewData('rango')->inicio->toDateString());
    }

    public function test_las_fechas_tienen_prioridad_sobre_mes_y_año(): void
    {
        $respuesta = $this->dashboard(['mes' => 8, 'año' => 2026, 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-31']);

        $this->assertEquals(4950, $respuesta->viewData('totalesPeriodo')['total_general']);
    }

    // ================================================================ comparativo con el período anterior

    public function test_para_dos_meses_completos_se_compara_con_los_dos_meses_anteriores(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31']);

        $comparativo = $respuesta->viewData('comparativo');
        // julio + agosto: solo el culto del 30/08 (1150).
        $this->assertEquals(1150, $comparativo['periodo_anterior_total']);
        $this->assertEquals(round((11250 - 1150) / 1150 * 100, 1), $comparativo['porcentaje']);
        $this->assertSame('01/07/2026 – 31/08/2026', $comparativo['periodo_anterior_nombre']);

        // asistencia promedio: (100 + 120 + 140) / 3 = 120 contra 50 del anterior
        $this->assertEquals(120, $comparativo['asistencia_actual']);
        $this->assertEquals(50, $comparativo['asistencia_anterior']);
        $this->assertEquals(140.0, $comparativo['asistencia_diff']);
    }

    public function test_para_un_mes_se_compara_con_el_mes_anterior_con_su_nombre(): void
    {
        $respuesta = $this->dashboard(['mes' => 9, 'año' => 2026]);

        $comparativo = $respuesta->viewData('comparativo');
        $this->assertSame('agosto 2026', $comparativo['periodo_anterior_nombre']);
        $this->assertEquals(1150, $comparativo['periodo_anterior_total']);
    }

    public function test_para_un_rango_libre_se_compara_con_los_mismos_dias_justo_antes(): void
    {
        // 15/09 - 20/10 son 36 días: el anterior es 10/08 - 14/09 (incluye 30/08 y 06/09).
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-10-20']);

        $comparativo = $respuesta->viewData('comparativo');
        $this->assertSame('10/08/2026 – 14/09/2026', $comparativo['periodo_anterior_nombre']);
        $this->assertEquals(1150 + 3700, $comparativo['periodo_anterior_total']);
        $this->assertEquals(2600 + 4950, $respuesta->viewData('totalesPeriodo')['total_general']);
    }

    public function test_las_alertas_hablan_del_periodo_y_no_del_mes_cuando_el_rango_es_libre(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31']);

        $mensajes = $respuesta->viewData('alertas')->pluck('mensaje')->implode(' | ');
        $this->assertStringContainsString(
            'Los ingresos subieron 878.3% respecto al período anterior (01/07/2026 – 31/08/2026)',
            $mensajes
        );

        // para un mes completo sí se nombra el mes
        $mensajes = $this->dashboard(['mes' => 9, 'año' => 2026])->viewData('alertas')->pluck('mensaje')->implode(' | ');
        $this->assertStringContainsString('respecto a agosto 2026', $mensajes);
    }

    // ================================================================ gráficos

    public function test_el_grafico_por_culto_trae_solo_los_cultos_del_rango_en_orden(): void
    {
        $grafico = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31'])->viewData('graficoCultos');

        $this->assertSame(['06/09', '20/09', '04/10'], $grafico['labels']);
        $this->assertSame([3700.0, 2600.0, 4950.0], $grafico['ingresos']);
        $this->assertSame([100, 120, 140], $grafico['asistencia']);
    }

    public function test_un_rango_que_cruza_de_año_pone_el_año_en_las_etiquetas(): void
    {
        $this->culto($this->iglesia, '2025-12-28', ['diezmo' => 10], 10);

        $grafico = $this->dashboard(['fecha_inicio' => '2025-12-01', 'fecha_fin' => '2026-09-30'])->viewData('graficoCultos');

        $this->assertSame('28/12/25', $grafico['labels'][0]);
    }

    public function test_la_distribucion_por_categorias_es_la_del_periodo(): void
    {
        $distribucion = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31'])->viewData('distribucion');

        $this->assertEquals(9000, $distribucion['diezmo']);
        $this->assertEquals(1800, $distribucion['misiones']);
        $this->assertEquals(450, $distribucion['suelto']);
    }

    public function test_la_tendencia_mensual_dura_12_meses_y_resalta_los_del_rango(): void
    {
        $tendencia = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31'])->viewData('tendencia');

        $this->assertCount(12, $tendencia);
        $this->assertSame($this->mes(2025, 11), $tendencia->first()['label']);
        $this->assertSame($this->mes(2026, 10), $tendencia->last()['label']);
        $this->assertSame(
            [$this->mes(2026, 9), $this->mes(2026, 10)],
            $tendencia->where('enRango', true)->pluck('label')->values()->all()
        );

        // y trae el dato de cada mes, p. ej. septiembre = 3700 + 2600
        $septiembre = $tendencia->firstWhere('label', $this->mes(2026, 9));
        $this->assertEquals(6300, $septiembre['ingresos']);
        $this->assertEquals(110, $septiembre['asistencia']);
    }

    public function test_si_el_rango_es_mas_largo_que_12_meses_la_tendencia_lo_cubre_completo(): void
    {
        $tendencia = $this->dashboard(['fecha_inicio' => '2025-01-01', 'fecha_fin' => '2026-10-31'])->viewData('tendencia');

        $this->assertCount(22, $tendencia);
        $this->assertSame($this->mes(2025, 1), $tendencia->first()['label']);
        $this->assertCount(22, $tendencia->where('enRango', true));
    }

    // ================================================================ promesas (meses completos)

    public function test_las_promesas_se_evaluan_por_meses_completos_multiplicando_el_monto_por_los_meses(): void
    {
        $septiembre = $this->culto($this->iglesia, '2026-09-27', ['misiones' => 1]);
        $octubre = $this->culto($this->iglesia, '2026-10-11', ['misiones' => 1]);

        $ana = $this->personaConPromesa($this->iglesia, 'Ana', 'misiones', 1000);
        $this->sobre($septiembre, $ana, 'misiones', 1000);
        $this->sobre($octubre, $ana, 'misiones', 500);

        $estado = fn (array $query) => $this->dashboard($query)->viewData('promesasStatus');

        // Septiembre solo: 1000 >= 1000
        $this->assertSame(['cumplidas' => 1, 'pendientes' => 0], $estado(['mes' => 9, 'año' => 2026]));
        // Octubre solo: 500 < 1000
        $this->assertSame(['cumplidas' => 0, 'pendientes' => 1], $estado(['mes' => 10, 'año' => 2026]));
        // Septiembre + octubre: 1500 < 2 x 1000
        $this->assertSame(['cumplidas' => 0, 'pendientes' => 1], $estado(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31']));
        // A mitad de mes se amplía a los meses completos: da lo mismo que septiembre + octubre
        $this->assertSame(['cumplidas' => 0, 'pendientes' => 1], $estado(['fecha_inicio' => '2026-09-28', 'fecha_fin' => '2026-10-02']));
    }

    public function test_las_categorias_excluidas_de_promesas_y_las_personas_de_otra_iglesia_no_cuentan(): void
    {
        $culto = $this->culto($this->iglesia, '2026-10-11', ['misiones' => 1]);
        $otra = $this->crearIglesia('otra', 'OTRA');
        $cultoAjeno = $this->culto($otra, '2026-10-11', ['misiones' => 1]);

        // diezmo está marcado excluir_de_promesas en las categorías por defecto
        $this->sobre($culto, $this->personaConPromesa($this->iglesia, 'Diezmista', 'diezmo', 100), 'diezmo', 100);
        $this->sobre($cultoAjeno, $this->personaConPromesa($otra, 'De otra iglesia', 'misiones', 100), 'misiones', 100);
        $this->sobre($culto, $this->personaConPromesa($this->iglesia, 'Cumplidora', 'misiones', 100), 'misiones', 100);

        $this->assertSame(
            ['cumplidas' => 1, 'pendientes' => 0],
            $this->dashboard(['mes' => 10, 'año' => 2026])->viewData('promesasStatus')
        );
    }

    // ================================================================ validación y URL compartible

    #[\PHPUnit\Framework\Attributes\DataProvider('consultasInvalidas')]
    public function test_un_filtro_invalido_se_informa_y_se_muestra_el_mes_actual(array $query, string $campo, string $mensaje): void
    {
        $respuesta = $this->dashboard($query)->assertOk();

        $rango = $respuesta->viewData('rango');
        $this->assertTrue($rango->errores->has($campo), 'Debería haber error en '.$campo.': '.json_encode($rango->errores->all()));
        $this->assertSame('2026-10-01', $rango->inicio->toDateString(), 'Cae al mes actual por defecto.');
        $this->assertSame('2026-10-31', $rango->fin->toDateString());
        $respuesta->assertSee($mensaje);
    }

    public static function consultasInvalidas(): array
    {
        return [
            'fin anterior al inicio' => [['fecha_inicio' => '2026-10-31', 'fecha_fin' => '2026-09-01'], 'fecha_fin', 'La fecha de fin no puede ser anterior a la fecha de inicio.'],
            'solo inicio' => [['fecha_inicio' => '2026-09-01'], 'fecha_fin', 'Indica también la fecha de fin.'],
            'solo fin' => [['fecha_fin' => '2026-09-01'], 'fecha_inicio', 'Indica también la fecha de inicio.'],
            'fecha inexistente' => [['fecha_inicio' => '2026-02-30', 'fecha_fin' => '2026-03-05'], 'fecha_inicio', 'La fecha de inicio no es válida.'],
            'formato incorrecto' => [['fecha_inicio' => '01/09/2026', 'fecha_fin' => '31/10/2026'], 'fecha_inicio', 'La fecha de inicio no es válida.'],
            'antes del 2000' => [['fecha_inicio' => '1999-12-31', 'fecha_fin' => '2000-01-02'], 'fecha_inicio', 'La fecha de inicio no puede ser anterior al año 2000.'],
            'rango de mas de 10 años' => [['fecha_inicio' => '2010-01-01', 'fecha_fin' => '2026-01-01'], 'fecha_fin', 'El rango no puede superar 10 años.'],
            'mes 13' => [['mes' => 13, 'año' => 2026], 'mes', 'El mes debe estar entre 1 y 12.'],
            'año absurdo' => [['mes' => 5, 'año' => 1850], 'año', 'El año debe estar entre 2000 y 2100.'],
            'mes no numerico' => [['mes' => 'abril', 'año' => 2026], 'mes', 'El mes no es válido.'],
        ];
    }

    public function test_si_las_fechas_son_invalidas_pero_el_mes_y_año_no_se_usan_estos(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => 'x', 'fecha_fin' => 'y', 'mes' => 9, 'año' => 2026]);

        $this->assertTrue($respuesta->viewData('rango')->errores->has('fecha_inicio'));
        $this->assertSame('2026-09-01', $respuesta->viewData('rango')->inicio->toDateString());
    }

    public function test_los_parametros_como_arreglo_no_rompen_la_pantalla(): void
    {
        $this->dashboard(['fecha_inicio' => ['2026-09-01'], 'fecha_fin' => ['x'], 'mes' => ['9']])->assertOk();
    }

    public function test_la_url_con_el_rango_es_compartible_el_formulario_y_los_atajos_la_reflejan(): void
    {
        $html = $this->dashboard(['fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-10-20'])
            ->assertOk()
            ->assertSee('Mostrando')
            ->assertSee('15/09/2026 – 20/10/2026')
            ->getContent();

        // el formulario de rango trae las fechas puestas (se puede copiar la URL o volver a aplicar)
        $this->assertStringContainsString('name="fecha_inicio" id="fecha_inicio" value="2026-09-15"', $html);
        $this->assertStringContainsString('name="fecha_fin" id="fecha_fin" value="2026-10-20"', $html);
        // y los dos formularios son GET independientes
        $this->assertSame(2, substr_count($html, 'method="GET" action="'.route('dashboard').'"'));
        // los atajos llevan fechas explícitas
        $this->assertStringContainsString('fecha_inicio=2026-10-01&amp;fecha_fin=2026-10-31', $html);   // Este mes
        $this->assertStringContainsString('fecha_inicio=2026-09-01&amp;fecha_fin=2026-09-30', $html);   // Mes anterior
        $this->assertStringContainsString('fecha_inicio=2026-08-01&amp;fecha_fin=2026-10-31', $html);   // Últimos 3 meses
        $this->assertStringContainsString('fecha_inicio=2026-01-01&amp;fecha_fin=2026-12-31', $html);   // Este año
    }

    public function test_los_tres_dashboards_se_enlazan_conservando_el_periodo(): void
    {
        $html = $this->dashboard(['fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-10-20'])->getContent();
        $periodo = '?fecha_inicio=2026-09-15&amp;fecha_fin=2026-10-20';

        $this->assertStringContainsString('href="'.route('dashboard.asistencia').$periodo.'"', $html);
        $this->assertStringContainsString('href="'.route('dashboard.promesas').$periodo.'"', $html);
        $this->assertStringContainsString('href="'.route('dashboard').$periodo.'"', $html);

        // el dashboard en el que se está aparece resaltado y los otros no
        $this->assertMatchesRegularExpression('/class="tab tab-active"\s+aria-current="page">General<\/a>/', $html);
        $this->assertMatchesRegularExpression('/class="tab "\s*>Asistencia<\/a>/', $html);
        $this->assertMatchesRegularExpression('/class="tab "\s*>Promesas<\/a>/', $html);
    }

    public function test_el_acceso_rapido_conserva_el_mes_y_el_año_elegidos(): void
    {
        $html = $this->dashboard(['mes' => 3, 'año' => 2026])->getContent();

        $this->assertMatchesRegularExpression('/<option value="3" selected>Marzo<\/option>/', $html);
        $this->assertMatchesRegularExpression('/<option value="2026" selected>2026<\/option>/', $html);
    }

    public function test_un_filtro_invalido_conserva_lo_escrito_en_el_formulario(): void
    {
        $html = $this->dashboard(['fecha_inicio' => '2026-10-31', 'fecha_fin' => '2026-09-01'])->getContent();

        $this->assertStringContainsString('name="fecha_inicio" id="fecha_inicio" value="2026-10-31"', $html);
        $this->assertStringContainsString('name="fecha_fin" id="fecha_fin" value="2026-09-01"', $html);
    }

    public function test_avisa_cuando_las_promesas_se_calculan_por_meses_completos(): void
    {
        $this->dashboard(['fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-10-20'])
            ->assertSee('Las promesas se evalúan por meses completos: 01/09/2026 – 31/10/2026.');

        $this->dashboard(['mes' => 9, 'año' => 2026])->assertDontSee('Las promesas se evalúan por meses completos');
    }

    // ================================================================ permisos

    public function test_admin_y_tesorero_ven_el_dashboard_y_otros_roles_no(): void
    {
        $this->actingAs($this->crearUsuario($this->iglesia, 'tesorero'))->get(route('dashboard'))->assertOk();

        foreach (['asistente', 'miembro', 'servidor'] as $rol) {
            $this->actingAs($this->crearUsuario($this->iglesia, $rol))->get(route('dashboard'))->assertForbidden();
        }
    }

    public function test_un_visitante_va_al_login(): void
    {
        $this->get(route('dashboard', ['mes' => 9]))->assertRedirect('login');
    }
}

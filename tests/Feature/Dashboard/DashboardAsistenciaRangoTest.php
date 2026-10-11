<?php

namespace Tests\Feature\Dashboard;

use App\Models\Asistencia;
use App\Models\AsistenciaClaseDetalle;
use App\Models\ClaseAsistencia;
use App\Models\Culto;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConDatosDeDashboard;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Dashboard de Asistencia (/dashboard/asistencia) sobre un rango de fechas.
 * Su valor por defecto sigue siendo el año en curso (hoy fijado en 15/10/2026).
 */
class DashboardAsistenciaRangoTest extends TestCase
{
    use ConDatosDeDashboard, ConIglesia, RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    private ClaseAsistencia $claseA;

    private ClaseAsistencia $claseB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 09:00:00');

        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');

        $this->claseA = $this->clase('Clase A', 'clase-a', 1);
        $this->claseB = $this->clase('Clase B', 'clase-b', 2);

        // enero: total 100, capilla 70, clase A 10 alumnos, 3 visitas, 1 salvo
        $this->asistencia('2026-01-11', 100, capilla: [30, 40], clases: [[$this->claseA, 5, 5]], extra: ['visitas_nino' => 3, 'salvos_nino' => 1]);
        // febrero: total 120, capilla 80, clase A 15 + clase B 10, 4 visitas, 2 salvos, 1 bautismo
        $this->asistencia('2026-02-08', 120, capilla: [50, 30], clases: [[$this->claseA, 8, 7], [$this->claseB, 6, 4]], extra: ['visitas_nino' => 4, 'salvos_nino' => 2, 'bautismos_nino' => 1]);
        // marzo: total 80, capilla 50
        $this->asistencia('2026-03-08', 80, capilla: [20, 30], clases: [[$this->claseB, 3, 2]]);
        // abril: total 200, capilla 150
        $this->asistencia('2026-04-12', 200, capilla: [70, 80]);
        // un culto sin asistencia registrada (no debe contar en promedios)
        $this->culto($this->iglesia, '2026-02-15');
    }

    private function clase(string $nombre, string $slug, int $orden): ClaseAsistencia
    {
        return ClaseAsistencia::create([
            'nombre' => $nombre, 'slug' => $slug, 'color' => '#336699', 'orden' => $orden,
            'activa' => true, 'tiene_maestros' => true, 'tenant_id' => $this->iglesia->id,
        ]);
    }

    /**
     * @param  array{0: int, 1: int}  $capilla  [hombres, mujeres]
     * @param  list<array{0: ClaseAsistencia, 1: int, 2: int}>  $clases  [clase, hombres, mujeres]
     */
    private function asistencia(string $fecha, int $total, array $capilla = [0, 0], array $clases = [], array $extra = []): Culto
    {
        $culto = Culto::create(['fecha' => $fecha, 'hora' => '10:00:00', 'tipo_culto' => 'domingo', 'tenant_id' => $this->iglesia->id]);
        $asistencia = Asistencia::create([
            'culto_id' => $culto->id,
            'tenant_id' => $this->iglesia->id,
            'total_asistencia' => $total,
            'chapel_adultos_hombres' => $capilla[0],
            'chapel_adultos_mujeres' => $capilla[1],
        ] + $extra);

        foreach ($clases as [$clase, $hombres, $mujeres]) {
            AsistenciaClaseDetalle::create([
                'asistencia_id' => $asistencia->id,
                'clase_asistencia_id' => $clase->id,
                'hombres' => $hombres,
                'mujeres' => $mujeres,
            ]);
        }

        return $culto;
    }

    private function dashboard(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('dashboard.asistencia', $query));
    }

    private function mes(int $anio, int $mes, string $formato = 'M'): string
    {
        return Carbon::create($anio, $mes, 1)->locale('es')->translatedFormat($formato);
    }

    public function test_sin_parametros_muestra_el_año_en_curso_como_siempre(): void
    {
        $respuesta = $this->dashboard()->assertOk();

        $rango = $respuesta->viewData('rango');
        $this->assertSame('2026-01-01', $rango->inicio->toDateString());
        $this->assertSame('2026-12-31', $rango->fin->toDateString());

        $this->assertSame(100 + 120 + 80 + 200, $respuesta->viewData('totalPeriodo'));
        $this->assertSame(4, $respuesta->viewData('cultosEnRango'), 'El culto sin asistencia no cuenta.');
        $this->assertEquals(125, $respuesta->viewData('promedio'));
    }

    public function test_los_kpis_se_calculan_sobre_el_rango(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-28'])->assertOk();

        $this->assertSame(220, $respuesta->viewData('totalPeriodo'));
        $this->assertEquals(110, $respuesta->viewData('promedio'));
        $this->assertSame(70 + 80, $respuesta->viewData('capilla'));
        $this->assertSame(10 + 15 + 10, $respuesta->viewData('ninos'));
        $this->assertSame(3 + 4, $respuesta->viewData('visitas'));
        $this->assertSame(1 + 2, $respuesta->viewData('salvos'));
        $this->assertSame(1, $respuesta->viewData('bautismos'));
    }

    public function test_mes_y_año_se_traducen_al_rango_de_ese_mes(): void
    {
        $respuesta = $this->dashboard(['mes' => 2, 'año' => 2026]);

        $this->assertSame(120, $respuesta->viewData('totalPeriodo'));
        $this->assertSame(1, $respuesta->viewData('cultosEnRango'));
        $this->assertSame('2026-02-28', $respuesta->viewData('rango')->fin->toDateString());
    }

    public function test_la_asistencia_por_clase_solo_suma_los_cultos_del_rango(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-03-31']);

        $porClase = $respuesta->viewData('porClase')->keyBy('slug');
        $this->assertSame(15, $porClase['clase-a']['total']);          // solo febrero
        $this->assertSame(10 + 5, $porClase['clase-b']['total']);      // febrero + marzo
    }

    public function test_un_rango_de_uno_o_dos_meses_se_grafica_culto_por_culto(): void
    {
        $serie = $this->dashboard(['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-28'])->viewData('serie');

        $this->assertSame('culto', $serie['modo']);
        $this->assertSame(
            [['etiqueta' => '11/01', 'total' => 100, 'cultos' => 1], ['etiqueta' => '08/02', 'total' => 120, 'cultos' => 1]],
            $serie['filas']
        );
    }

    public function test_un_rango_de_tres_o_mas_meses_se_grafica_por_mes_dentro_del_rango(): void
    {
        $serie = $this->dashboard(['fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-04-30'])->viewData('serie');

        $this->assertSame('mes', $serie['modo']);
        $this->assertSame(
            [
                ['etiqueta' => $this->mes(2026, 2), 'total' => 120, 'cultos' => 1],
                ['etiqueta' => $this->mes(2026, 3), 'total' => 80, 'cultos' => 1],
                ['etiqueta' => $this->mes(2026, 4), 'total' => 200, 'cultos' => 1],
            ],
            $serie['filas']
        );
    }

    public function test_el_año_completo_se_grafica_con_sus_12_meses(): void
    {
        $serie = $this->dashboard(['año' => 2026])->viewData('serie');

        $this->assertSame('mes', $serie['modo']);
        $this->assertCount(12, $serie['filas']);
        $this->assertSame([100, 120, 80, 200, 0, 0, 0, 0, 0, 0, 0, 0], array_column($serie['filas'], 'total'));
    }

    public function test_un_rango_que_cruza_de_año_pone_el_año_en_las_etiquetas_mensuales(): void
    {
        $this->asistencia('2025-12-14', 60);

        $serie = $this->dashboard(['fecha_inicio' => '2025-12-01', 'fecha_fin' => '2026-02-28'])->viewData('serie');

        $this->assertSame($this->mes(2025, 12, 'M y'), $serie['filas'][0]['etiqueta']);
        $this->assertSame(60, $serie['filas'][0]['total']);
    }

    public function test_los_cultos_de_otra_iglesia_no_cuentan(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        $this->culto($otra, '2026-01-18', ['diezmo' => 1], 5000);

        $this->assertSame(500, $this->dashboard()->viewData('totalPeriodo'));
    }

    public function test_el_excel_de_asistencia_se_descarga_con_el_mismo_rango(): void
    {
        $url = route('ingresos-asistencia.excel-asistencia', ['fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-03-31']);

        $respuesta = $this->dashboard(['fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-03-31']);

        $this->assertSame($url, $respuesta->viewData('excelUrl'));
        $respuesta->assertSee(e($url), false);
    }

    public function test_sin_datos_en_el_rango_avisa_en_vez_de_dibujar_un_grafico_vacio(): void
    {
        $this->dashboard(['fecha_inicio' => '2025-01-01', 'fecha_fin' => '2025-01-31'])
            ->assertOk()
            ->assertSee('No hay asistencias registradas en este período.');
    }

    public function test_un_rango_invalido_se_informa_y_cae_al_año_en_curso(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-05-01', 'fecha_fin' => '2026-01-01'])->assertOk();

        $this->assertTrue($respuesta->viewData('rango')->errores->has('fecha_fin'));
        $this->assertSame('2026-01-01', $respuesta->viewData('rango')->inicio->toDateString());
        $this->assertSame('2026-12-31', $respuesta->viewData('rango')->fin->toDateString());
        $respuesta->assertSee('La fecha de fin no puede ser anterior a la fecha de inicio.');
    }

    public function test_muestra_el_filtro_de_periodo_con_acceso_rapido_y_rango(): void
    {
        $this->dashboard()
            ->assertSee('Mes y año')
            ->assertSee('Rango de fechas')
            ->assertSee('Todo el año')
            ->assertSee('Atajos:');
    }

    public function test_solo_admin_y_tesorero(): void
    {
        $this->actingAs($this->crearUsuario($this->iglesia, 'tesorero'))->get(route('dashboard.asistencia'))->assertOk();

        foreach (['asistente', 'miembro'] as $rol) {
            $this->actingAs($this->crearUsuario($this->iglesia, $rol))->get(route('dashboard.asistencia'))->assertForbidden();
        }
    }
}

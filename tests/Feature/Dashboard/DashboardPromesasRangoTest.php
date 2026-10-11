<?php

namespace Tests\Feature\Dashboard;

use App\Models\Persona;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConDatosDeDashboard;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Dashboard de Promesas (/dashboard/promesas) sobre un rango de fechas. Los compromisos se
 * calculan por mes, así que el rango se amplía a meses completos. Por defecto, el año en curso
 * (hoy fijado en 15/10/2026).
 */
class DashboardPromesasRangoTest extends TestCase
{
    use ConDatosDeDashboard, ConIglesia, RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    private Persona $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 09:00:00');

        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');

        $this->ana = $this->personaConPromesa($this->iglesia, 'Ana', 'misiones', 1000);
        $this->compromiso($this->ana, 2026, 8, 500, 500);
        $this->compromiso($this->ana, 2026, 9, 1000, 800);
        $this->compromiso($this->ana, 2026, 10, 1000, 1200);
        $this->compromiso($this->ana, 2026, 11, 1000, 1000);
    }

    private function dashboard(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('dashboard.promesas', $query));
    }

    public function test_los_kpis_suman_los_compromisos_de_los_meses_del_rango(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31'])->assertOk();

        $this->assertSame(2000.0, $respuesta->viewData('prometido'));
        $this->assertSame(2000.0, $respuesta->viewData('dado'));
        $this->assertSame(0.0, $respuesta->viewData('saldo'));
        $this->assertEquals(100.0, $respuesta->viewData('cumplimiento'));
    }

    public function test_un_rango_a_mitad_de_mes_incluye_los_meses_completos_y_lo_avisa(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-10-20'])->assertOk();

        $this->assertSame(2000.0, $respuesta->viewData('prometido'));
        $this->assertSame(2000.0, $respuesta->viewData('dado'));
        $this->assertSame('2026-09-01', $respuesta->viewData('rangoMeses')->inicio->toDateString());
        $this->assertSame('2026-10-31', $respuesta->viewData('rangoMeses')->fin->toDateString());
        $respuesta->assertSee('Los compromisos se calculan por mes: se incluyen los meses completos (01/09/2026 – 31/10/2026).');
    }

    public function test_un_rango_de_meses_completos_no_muestra_el_aviso(): void
    {
        $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31'])
            ->assertDontSee('Los compromisos se calculan por mes');
    }

    public function test_sin_parametros_muestra_el_año_en_curso(): void
    {
        $respuesta = $this->dashboard();

        $this->assertSame('2026-01-01', $respuesta->viewData('rango')->inicio->toDateString());
        $this->assertSame(500.0 + 1000 + 1000 + 1000, $respuesta->viewData('prometido'));
        $this->assertSame(500.0 + 800 + 1200 + 1000, $respuesta->viewData('dado'));
    }

    public function test_mes_y_año_se_traducen_a_ese_mes(): void
    {
        $respuesta = $this->dashboard(['mes' => 9, 'año' => 2026]);

        $this->assertSame(1000.0, $respuesta->viewData('prometido'));
        $this->assertSame(800.0, $respuesta->viewData('dado'));
        $this->assertSame(-200.0, $respuesta->viewData('saldo'));
        $this->assertEquals(80.0, $respuesta->viewData('cumplimiento'));
    }

    public function test_un_rango_que_cruza_de_año_filtra_bien_por_año_y_mes(): void
    {
        $this->compromiso($this->ana, 2025, 10, 999, 999);   // antes del rango
        $this->compromiso($this->ana, 2025, 12, 1000, 600);
        $this->compromiso($this->ana, 2026, 1, 1000, 400);
        $this->compromiso($this->ana, 2026, 3, 777, 777);    // después del rango

        $respuesta = $this->dashboard(['fecha_inicio' => '2025-11-01', 'fecha_fin' => '2026-02-28']);

        $this->assertSame(2000.0, $respuesta->viewData('prometido'));
        $this->assertSame(1000.0, $respuesta->viewData('dado'));
        $this->assertCount(4, $respuesta->viewData('serie'), 'nov, dic, ene y feb.');
    }

    public function test_la_serie_mensual_trae_prometido_y_dado_de_cada_mes_del_rango(): void
    {
        $serie = $this->dashboard(['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-10-31'])->viewData('serie');

        $this->assertSame(
            [
                ['mes' => Carbon::create(2026, 8, 1)->locale('es')->translatedFormat('M'), 'prometido' => 500.0, 'dado' => 500.0],
                ['mes' => Carbon::create(2026, 9, 1)->locale('es')->translatedFormat('M'), 'prometido' => 1000.0, 'dado' => 800.0],
                ['mes' => Carbon::create(2026, 10, 1)->locale('es')->translatedFormat('M'), 'prometido' => 1000.0, 'dado' => 1200.0],
            ],
            $serie->all()
        );
    }

    public function test_el_top_de_contribuyentes_se_calcula_sobre_el_rango_y_es_de_10(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $persona = Persona::create(['nombre' => "Persona $i", 'tenant_id' => $this->iglesia->id, 'activo' => true]);
            $this->compromiso($persona, 2026, 9, 100, $i * 10);   // dado: 10, 20 ... 110
            $this->compromiso($persona, 2026, 12, 100, 9999);     // fuera del rango
        }

        $top = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31'])->viewData('topContribuyentes');

        $this->assertCount(10, $top);
        $this->assertSame('Ana', $top[0]['nombre'], 'Ana dio 2000 entre septiembre y octubre.');
        $this->assertSame('Persona 11', $top[1]['nombre']);
        $this->assertSame(110.0, $top[1]['dado']);
        $this->assertSame(100.0, $top[1]['prometido']);
        $this->assertEquals(110.0, $top[1]['pct']);
        $this->assertNotContains('Persona 1', $top->pluck('nombre')->all(), 'La de 10 queda fuera del top 10.');
    }

    public function test_los_compromisos_de_personas_de_otra_iglesia_no_cuentan(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        $ajena = $this->personaConPromesa($otra, 'Ajena', 'misiones', 5000);
        $this->compromiso($ajena, 2026, 9, 5000, 5000);

        $respuesta = $this->dashboard(['mes' => 9, 'año' => 2026]);

        $this->assertSame(1000.0, $respuesta->viewData('prometido'));
        $this->assertNotContains('Ajena', $respuesta->viewData('topContribuyentes')->pluck('nombre')->all());
    }

    public function test_el_excel_solo_se_ofrece_para_un_mes_o_un_año_completo(): void
    {
        // un mes: año + mes
        $this->assertSame(
            route('ingresos-asistencia.excel-promesas', ['año' => 2026, 'mes' => 9]),
            $this->dashboard(['mes' => 9, 'año' => 2026])->viewData('excelUrl')
        );
        // un rango libre que equivale a un mes
        $this->assertSame(
            route('ingresos-asistencia.excel-promesas', ['año' => 2026, 'mes' => 9]),
            $this->dashboard(['fecha_inicio' => '2026-09-10', 'fecha_fin' => '2026-09-20'])->viewData('excelUrl')
        );
        // el año completo (también el valor por defecto)
        $this->assertSame(
            route('ingresos-asistencia.excel-promesas', ['año' => 2026]),
            $this->dashboard()->viewData('excelUrl')
        );

        // cualquier otra cosa: sin enlace y con una nota
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31']);
        $this->assertNull($respuesta->viewData('excelUrl'));
        $respuesta->assertSee('El Excel de promesas se descarga por mes o por año completo.')
            ->assertDontSee('Descargar Excel');
    }

    public function test_un_rango_invalido_se_informa_y_cae_al_año_en_curso(): void
    {
        $respuesta = $this->dashboard(['fecha_inicio' => '2026-10-31', 'fecha_fin' => '2026-09-01'])->assertOk();

        $this->assertTrue($respuesta->viewData('rango')->errores->has('fecha_fin'));
        $this->assertSame('2026-01-01', $respuesta->viewData('rango')->inicio->toDateString());
        $respuesta->assertSee('La fecha de fin no puede ser anterior a la fecha de inicio.');
    }

    public function test_solo_admin_y_tesorero(): void
    {
        $this->actingAs($this->crearUsuario($this->iglesia, 'tesorero'))->get(route('dashboard.promesas'))->assertOk();

        foreach (['asistente', 'miembro'] as $rol) {
            $this->actingAs($this->crearUsuario($this->iglesia, $rol))->get(route('dashboard.promesas'))->assertForbidden();
        }
    }
}

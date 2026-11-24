<?php

namespace Tests\Feature\Reportes;

use App\Models\Ministerio;
use App\Models\Persona;
use App\Services\Reportes\CandidatasSelector;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\PeriodoReporte;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\Concerns\LeeReportes;
use Tests\TestCase;

/**
 * HU-R2: selector de personas con filtros rapidos combinables (estado, ministerios, con promesa,
 * aporto en el periodo) que viajan en la URL, sin romper los enlaces de siempre. Hoy: 13/10/2026.
 *
 * Escenario (CreaEscenarioReportes): Ana (promesa, aporta), Beto (sin promesa, aporta), Carla
 * (promesa, aporta), Diego Inactivo (promesa, no aporta) y, aqui, Ivan (activo, sin nada).
 */
class SelectorPersonasTest extends TestCase
{
    use CreaEscenarioReportes;
    use LeeReportes;
    use RefreshDatabase;

    private Ministerio $alabanza;

    private Ministerio $ujieres;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->crearEscenarioReportes();
        $this->crearPersona('Ivan Sin Nada', '8000-0005');

        $this->alabanza = Ministerio::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Alabanza']);
        $this->ujieres = Ministerio::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Ujieres']);
        $this->ana->ministerios()->attach($this->alabanza->id, ['desde' => '2026-01-01']);
        $this->beto->ministerios()->attach($this->ujieres->id, ['desde' => '2026-01-01']);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $parametros */
    private function nombres(array $parametros): array
    {
        return FiltroPersonas::desde($parametros)->personas(PeriodoReporte::alCorte('2026-10-13'))->pluck('nombre')->all();
    }

    public function test_los_enlaces_de_siempre_dan_lo_mismo(): void
    {
        $this->assertSame(['grupo' => 'todas'], FiltroPersonas::desde([])->aParametros());
        $this->assertSame(['grupo' => 'con_promesa'], FiltroPersonas::desde(['grupo' => 'con_promesa'])->aParametros());
        $this->assertSame(['Ana Mora', 'Carla Solano'], $this->nombres(['grupo' => 'con_promesa']));
        $this->assertSame(['Ana Mora'], $this->nombres(['grupo' => 'ministerio', 'ministerio' => (string) $this->alabanza->id]));
        $this->assertSame(['grupo' => 'ministerio', 'ministerio' => $this->alabanza->id], FiltroPersonas::desde(['grupo' => 'ministerio', 'ministerio' => (string) $this->alabanza->id])->aParametros());
        $this->assertSame(['Ana Mora', 'Beto Rojas'], $this->nombres(['grupo' => 'ministerio', 'ministerio' => 'todos']));
        $this->assertSame(['Beto Rojas', 'Diego Inactivo'], $this->nombres(['grupo' => 'seleccion', 'personas' => $this->beto->id.','.Persona::where('nombre', 'Diego Inactivo')->value('id')]));
        $this->assertSame('Personas activas con promesa', FiltroPersonas::desde(['grupo' => 'con_promesa'])->descripcion());
    }

    public function test_filtros_que_equivalen_a_un_grupo_de_siempre_se_normalizan(): void
    {
        $this->assertSame(FiltroPersonas::TODAS, FiltroPersonas::desde(['grupo' => 'filtros', 'estado' => 'activos', 'promesa' => 'todos', 'aporto' => 'todos'])->grupo);
        $this->assertSame(FiltroPersonas::CON_PROMESA, FiltroPersonas::desde(['grupo' => 'filtros', 'promesa' => 'si'])->grupo);
        $ministerio = FiltroPersonas::desde(['grupo' => 'filtros', 'ministerios' => (string) $this->ujieres->id]);
        $this->assertSame(FiltroPersonas::MINISTERIO, $ministerio->grupo);
        $this->assertSame('Ministerio: Ujieres', $ministerio->descripcion());
    }

    public function test_filtros_combinables(): void
    {
        $this->assertSame(['Ana Mora', 'Carla Solano', 'Diego Inactivo'], $this->nombres(['grupo' => 'filtros', 'estado' => 'todos', 'promesa' => 'si']));
        $this->assertSame(['Diego Inactivo'], $this->nombres(['grupo' => 'filtros', 'estado' => 'inactivos']));
        $this->assertSame(['Beto Rojas', 'Ivan Sin Nada'], $this->nombres(['grupo' => 'filtros', 'promesa' => 'no']));
        $this->assertSame(['Ivan Sin Nada'], $this->nombres(['grupo' => 'filtros', 'promesa' => 'no', 'aporto' => 'no']));
        $this->assertSame(['Ana Mora', 'Beto Rojas', 'Carla Solano'], $this->nombres(['grupo' => 'filtros', 'aporto' => 'si']));
        $this->assertSame(['Ana Mora', 'Beto Rojas'], $this->nombres(['grupo' => 'filtros', 'ministerios' => $this->alabanza->id.','.$this->ujieres->id]));
        $this->assertSame(['Ana Mora'], $this->nombres(['grupo' => 'filtros', 'ministerios' => $this->alabanza->id.','.$this->ujieres->id, 'promesa' => 'si']));

        // "Aportó en el periodo" depende del periodo: en noviembre-diciembre de 2025 solo Ana (28/12/2025).
        $diciembre = FiltroPersonas::desde(['grupo' => 'filtros', 'aporto' => 'si'])->personas(PeriodoReporte::deMeses(2025, 11, 12));
        $this->assertSame(['Ana Mora'], $diciembre->pluck('nombre')->all());

        $filtro = FiltroPersonas::desde(['grupo' => 'filtros', 'estado' => 'todos', 'promesa' => 'si', 'aporto' => 'no']);
        $this->assertSame('Personas activas e inactivas, con promesa, que no aportaron en el periodo', $filtro->descripcion());
        $this->assertSame(['grupo' => 'filtros', 'estado' => 'todos', 'promesa' => 'si', 'aporto' => 'no'], $filtro->aParametros());
    }

    public function test_un_valor_invalido_no_rompe_y_se_explica(): void
    {
        $filtro = FiltroPersonas::desde(['grupo' => 'filtros', 'estado' => 'raro', 'promesa' => 'si']);

        $this->assertSame(FiltroPersonas::CON_PROMESA, $filtro->grupo);
        $this->assertTrue($filtro->tieneErrores());
    }

    public function test_la_seleccion_recuerda_los_filtros_pero_entran_las_elegidas(): void
    {
        $filtro = FiltroPersonas::desde(['grupo' => 'seleccion', 'personas' => (string) $this->beto->id, 'estado' => 'todos', 'promesa' => 'no']);

        $this->assertSame(['Beto Rojas'], $filtro->personas(PeriodoReporte::alCorte('2026-10-13'))->pluck('nombre')->all());
        $this->assertSame(['estado' => 'todos', 'ministerios' => [], 'promesa' => 'no', 'aporto' => 'todos'], $filtro->filtrosRapidos());
    }

    public function test_datos_del_selector_para_filtrar_en_el_navegador(): void
    {
        $datos = app(CandidatasSelector::class)->datos(PeriodoReporte::alCorte('2026-10-13'));
        $porNombre = collect($datos['personas'])->keyBy('nombre');

        $this->assertArrayNotHasKey('Zoe Ajena', $porNombre->all());
        $this->assertSame([$this->alabanza->id], $porNombre['Ana Mora']['ministerios']);
        $this->assertTrue($porNombre['Ana Mora']['promesa']);
        $this->assertTrue($porNombre['Ana Mora']['aporto']);
        $this->assertFalse($porNombre['Beto Rojas']['promesa']);
        $this->assertFalse($porNombre['Diego Inactivo']['activo']);
        $this->assertFalse($porNombre['Diego Inactivo']['aporto']);
        $this->assertSame(['Alabanza', 'Ujieres'], array_column($datos['ministerios'], 'nombre'));
    }

    public function test_las_pantallas_aplican_los_filtros_de_la_url_y_los_llevan_a_las_descargas(): void
    {
        $parametros = ['grupo' => 'filtros', 'estado' => 'todos', 'promesa' => 'si', 'aporto' => 'no'];

        $this->get(route('reportes.cumplimiento', $parametros + ['corte' => '2026-10-13']))->assertOk()
            ->assertSee('Personas activas e inactivas, con promesa, que no aportaron en el periodo')
            ->assertSee('Diego Inactivo')
            ->assertDontSee('data-persona="'.$this->ana->id.'"', false)
            ->assertSee('Seleccionar todos los filtrados')
            ->assertSee('Quitar todos')
            ->assertSee(e(route('reportes.cumplimiento.excel', ['corte' => '2026-10-13'] + $parametros)), false);

        $texto = $this->textoDelPdf($this->get(route('reportes.cumplimiento.pdf', $parametros))->assertOk()->getContent());
        $this->assertStringContainsString('Diego Inactivo', $texto);
        $this->assertStringNotContainsString('Ana Mora', $texto);

        $this->get(route('reportes.estados-cuenta', $parametros + ['periodo' => 'meses', 'anio' => 2026, 'mes_desde' => 1, 'mes_hasta' => 10]))->assertOk()
            ->assertSee('Diego Inactivo')
            ->assertDontSee('data-persona="'.$this->ana->id.'"', false)
            ->assertSee('Seleccionar todos los filtrados');
    }

    public function test_tesorero_usa_el_selector_y_el_asistente_no(): void
    {
        $this->actingAs($this->crearUsuarioConRol('tesorero'))->get(route('reportes.estados-cuenta', ['grupo' => 'filtros', 'promesa' => 'no']))->assertOk()->assertSee('Beto Rojas');
        $this->actingAs($this->crearUsuarioConRol('asistente'))->get(route('reportes.cumplimiento', ['grupo' => 'filtros', 'promesa' => 'no']))->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Reportes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioAsistencia;
use Tests\TestCase;

/**
 * HU-R12: los reportes de asistencia del centro (por culto y por clase) usan el mismo rango de
 * fechas y se enlazan entre si; el tesorero los consulta (decision 5) sin los botones de registrar.
 */
class AsistenciaEnCentroTest extends TestCase
{
    use CreaEscenarioAsistencia;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearIglesiaConAdmin();
        $clase = $this->crearClase('Clase 2-6 Años', 'clase_2_6', 1);
        $this->registrarClase($this->crearCulto('2026-08-02'), $clase, 10, 5, 1);
    }

    public function test_asistencia_por_clase_entiende_las_fechas_de_los_demas_reportes(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->get(route('ingresos-asistencia.asistencia-clases', ['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Del 01/08/2026 al 31/08/2026');

        $this->assertSame('2026-08-01', $respuesta->viewData('filtros')['desde']->toDateString());
        // Y enlaza a la asistencia por culto de las mismas fechas
        $respuesta->assertSee(e(route('ingresos-asistencia.asistencia', ['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-31'])), false);
    }

    public function test_asistencia_por_culto_enlaza_a_la_de_clase_con_las_mismas_fechas(): void
    {
        $this->actingAs($this->admin)
            ->get(route('ingresos-asistencia.asistencia', ['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Ver la asistencia por clase de estas fechas')
            ->assertSee(e(route('ingresos-asistencia.asistencia-clases', ['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-31'])), false);
    }

    public function test_el_tesorero_consulta_sin_botones_de_editar_y_el_asistente_si_los_ve(): void
    {
        $filtro = ['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-31'];

        $this->actingAs($this->crearUsuarioConRol('tesorero'))->get(route('ingresos-asistencia.asistencia', $filtro))
            ->assertOk()
            ->assertSee('02/08/2026')
            ->assertDontSee('>Editar</a>', false);
        $this->actingAs($this->crearUsuarioConRol('asistente'))->get(route('ingresos-asistencia.asistencia', $filtro))
            ->assertOk()
            ->assertSee('>Editar</a>', false);
    }
}

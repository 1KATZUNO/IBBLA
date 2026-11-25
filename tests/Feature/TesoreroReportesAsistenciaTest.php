<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioAsistencia;
use Tests\TestCase;

/**
 * QE-012: docs/ROLES.md decía que el tesorero no ve los reportes de asistencia, pero la decisión 5
 * (04-DECISIONES.md) los da a admin, tesorero y asistente. Manda la decisión: el tesorero ve y
 * descarga los reportes de asistencia y el Excel del Dashboard de asistencia.
 */
class TesoreroReportesAsistenciaTest extends TestCase
{
    use CreaEscenarioAsistencia;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearIglesiaConAdmin();
        $this->crearClase('Clase 2-6 Años', 'clase_2_6');
        $this->crearCulto('2026-11-01');
    }

    public function test_el_tesorero_ve_el_boton_de_excel_del_dashboard_de_asistencia_y_lo_descarga(): void
    {
        $tesorero = $this->crearUsuarioConRol('tesorero');

        $respuesta = $this->actingAs($tesorero)
            ->get(route('dashboard.asistencia', ['fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-30']))
            ->assertOk()
            ->assertSee('Descargar Excel');

        $excelUrl = $respuesta->viewData('excelUrl');
        $this->assertStringContainsString(route('ingresos-asistencia.excel-asistencia'), $excelUrl);

        $descarga = $this->actingAs($tesorero)->get($excelUrl)->assertOk();
        $this->assertStringContainsString('attachment', (string) $descarga->headers->get('content-disposition'));
    }

    public function test_el_tesorero_ve_los_reportes_de_asistencia_y_descarga_su_pdf(): void
    {
        $tesorero = $this->crearUsuarioConRol('tesorero');

        $this->actingAs($tesorero)->get(route('ingresos-asistencia.asistencia'))->assertOk();
        $this->actingAs($tesorero)->get(route('ingresos-asistencia.asistencia-clases'))->assertOk();
        $this->actingAs($tesorero)
            ->get(route('ingresos-asistencia.excel-asistencia-mes', ['mes' => 11, 'año' => 2026]))
            ->assertOk();
    }

    public function test_el_miembro_sigue_sin_reportes_de_asistencia(): void
    {
        $miembro = $this->crearUsuarioConRol('miembro');

        $this->actingAs($miembro)->get(route('dashboard.asistencia'))->assertForbidden();
        $this->actingAs($miembro)->get(route('ingresos-asistencia.excel-asistencia'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Reportes;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\TestCase;

/**
 * QA-010 con la regla 2.5 de 04-DECISIONES.md: reportes y descargas financieras para admin y
 * tesorero; de asistencia para admin, tesorero y asistente; el miembro, solo lo suyo (Mi perfil).
 */
class PermisosDescargasTest extends TestCase
{
    use CreaEscenarioReportes;
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

    /** @return array<string, array{0: string, 1: array<string, mixed>}> */
    public static function financieras(): array
    {
        return [
            'Excel de ingresos' => ['ingresos-asistencia.excel-ingresos', ['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-03-31']],
            'Reporte General' => ['personas.reporte-general', ['accion' => 'ver']],
            'Reporte de contribuciones' => ['personas.reporte-pdf', ['accion' => 'ver']],
            'Cumplimiento' => ['reportes.cumplimiento', []],
            'Estados de cuenta en lote' => ['reportes.estados-cuenta', []],
        ];
    }

    #[DataProvider('financieras')]
    public function test_financieras_admin_y_tesorero_si_asistente_y_miembro_no(string $ruta, array $parametros): void
    {
        $this->actingAs($this->admin)->get(route($ruta, $parametros))->assertOk();
        $this->actingAs($this->crearUsuarioConRol('tesorero'))->get(route($ruta, $parametros))->assertOk();
        $this->actingAs($this->crearUsuarioConRol('asistente'))->get(route($ruta, $parametros))->assertForbidden();
        $this->actingAs($this->crearUsuarioConRol('miembro'))->get(route($ruta, $parametros))->assertForbidden();
    }

    public function test_estado_de_cuenta_de_otra_persona_admin_y_tesorero(): void
    {
        $ruta = route('personas.informe', ['persona' => $this->ana, 'accion' => 'ver']);

        $this->actingAs($this->crearUsuarioConRol('tesorero'))->get($ruta)->assertOk();
        $this->actingAs($this->crearUsuarioConRol('asistente'))->get($ruta)->assertForbidden();
        $this->actingAs($this->crearUsuarioConRol('miembro'))->get($ruta)->assertForbidden();
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>}> */
    public static function deAsistencia(): array
    {
        return [
            'Reporte de asistencia' => ['ingresos-asistencia.asistencia', []],
            'Excel de asistencia' => ['ingresos-asistencia.excel-asistencia', ['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-03-31']],
            'Asistencia por clase' => ['ingresos-asistencia.asistencia-clases', []],
        ];
    }

    #[DataProvider('deAsistencia')]
    public function test_asistencia_admin_tesorero_y_asistente_si_miembro_no(string $ruta, array $parametros): void
    {
        foreach (['tesorero', 'asistente'] as $rol) {
            $this->actingAs($this->crearUsuarioConRol($rol))->get(route($ruta, $parametros))->assertOk();
        }
        $this->actingAs($this->admin)->get(route($ruta, $parametros))->assertOk();
        $this->actingAs($this->crearUsuarioConRol('miembro'))->get(route($ruta, $parametros))->assertForbidden();
    }

    public function test_registrar_asistencia_sigue_siendo_de_admin_y_asistente(): void
    {
        $this->actingAs($this->crearUsuarioConRol('tesorero'))->get(route('asistencia.index'))->assertForbidden();
        $this->actingAs($this->crearUsuarioConRol('asistente'))->get(route('asistencia.index'))->assertOk();
    }
}

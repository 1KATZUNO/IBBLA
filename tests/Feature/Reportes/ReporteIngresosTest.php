<?php

namespace Tests\Feature\Reportes;

use App\Models\AuditLog;
use App\Services\Reportes\AuditoriaReportes;
use App\Services\Reportes\Cultos\FiltroCultos;
use App\Services\Reportes\Cultos\GeneradorReporteCultos;
use App\Services\Reportes\Ingresos\ReporteIngresos;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\TestCase;

/**
 * HU-R11 Reporte de ingresos: comparativo con el periodo anterior de igual duracion (monto y %,
 * sin dividir entre cero), mismas cifras que el Reporte de cultos, rutas viejas que siguen
 * respondiendo y permisos por rol.
 */
class ReporteIngresosTest extends TestCase
{
    use CreaEscenarioReportes;
    use RefreshDatabase;

    private const FEBRERO = ['fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-02-28'];

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

    public function test_compara_febrero_con_enero(): void
    {
        $reporte = ReporteIngresos::generar($this->filtro('2026-02-01', '2026-02-28'));

        // Febrero: 15.000 + $20 a 510 (10.200) + 25.000. Enero: 40.000,50 + 6.000 + 7.000 (anónimo)
        $total = $reporte->total();
        $this->assertEqualsWithDelta(50200, $total['actual'], 0.001);
        $this->assertEqualsWithDelta(53000.50, $total['anterior'], 0.001);
        $this->assertEqualsWithDelta(-2800.50, $total['diferencia'], 0.001);
        $this->assertSame(-5.3, $total['porcentaje']);
        $this->assertSame('del 01/01/2026 al 31/01/2026', $reporte->etiquetaAnterior());

        $misiones = collect($reporte->porCategoria())->firstWhere('nombre', 'Misiones');
        $this->assertEqualsWithDelta(10000 + 10200 + 5000, $misiones['actual'], 0.001);
        $this->assertEqualsWithDelta(10000.50 + 7000, $misiones['anterior'], 0.001);

        // Categorías y métodos suman lo mismo que el total
        $this->assertEqualsWithDelta($total['actual'], array_sum(array_column($reporte->porCategoria(), 'actual')), 0.001);
        $this->assertEqualsWithDelta($total['anterior'], array_sum(array_column($reporte->porMetodo(), 'anterior')), 0.001);
    }

    public function test_sin_ingresos_antes_no_divide_entre_cero(): void
    {
        $reporte = ReporteIngresos::generar($this->filtro('2025-12-01', '2025-12-31'));

        $this->assertEqualsWithDelta(99999, $reporte->total()['actual'], 0.001);
        $this->assertNull($reporte->total()['porcentaje']);
        $this->assertSame('sin datos antes', ReporteIngresos::textoPorcentaje(null));
        $this->assertSame('+12.5 %', ReporteIngresos::textoPorcentaje(12.5));
    }

    public function test_un_rango_que_no_son_meses_completos_se_compara_con_los_mismos_dias_antes(): void
    {
        $reporte = ReporteIngresos::generar($this->filtro('2026-02-10', '2026-02-23'));

        $this->assertSame('del 27/01/2026 al 09/02/2026', $reporte->etiquetaAnterior());
    }

    public function test_cuadra_con_el_reporte_de_cultos(): void
    {
        $filtro = $this->filtro('2026-01-01', '2026-10-15');
        $cultos = app(GeneradorReporteCultos::class)->generar($filtro);

        $this->assertEqualsWithDelta($cultos->totales()['ingresos'], ReporteIngresos::generar($filtro)->total()['actual'], 0.001);
    }

    public function test_pantalla_pdf_y_excel_por_rol_y_auditados(): void
    {
        $tesorero = $this->crearUsuarioConRol('tesorero');
        $asistente = $this->crearUsuarioConRol('asistente');
        $miembro = $this->crearUsuarioConRol('miembro');

        $this->actingAs($tesorero)->get(route('reportes.ingresos', self::FEBRERO))
            ->assertOk()
            ->assertSee('Ingresos por categoría')
            ->assertSee('Ingresos por método de pago')
            ->assertSee('del 01/01/2026 al 31/01/2026')
            ->assertSee('-5.3 %');
        foreach (['reportes.ingresos', 'reportes.ingresos.pdf', 'reportes.ingresos.excel'] as $ruta) {
            $this->actingAs($this->admin)->get(route($ruta, self::FEBRERO))->assertOk();
            $this->actingAs($tesorero)->get(route($ruta, self::FEBRERO))->assertOk();
            $this->actingAs($asistente)->get(route($ruta, self::FEBRERO))->assertForbidden();
            $this->actingAs($miembro)->get(route($ruta, self::FEBRERO))->assertForbidden();
        }

        $formatos = AuditLog::where('action', AuditoriaReportes::ACCION)->get()
            ->map(fn ($a) => json_decode($a->details, true))->where('reporte', 'ingresos')->pluck('formato')->all();
        $this->assertSame(['pdf', 'pdf', 'excel', 'excel'], array_values($formatos));
    }

    public function test_las_rutas_viejas_llevan_al_reporte_nuevo_con_las_mismas_fechas(): void
    {
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.ingresos', self::FEBRERO + ['tipo_reporte' => 'mes']))
            ->assertRedirect(route('reportes.ingresos', self::FEBRERO));
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.pdf-ingresos', self::FEBRERO))
            ->assertRedirect(route('reportes.ingresos.pdf', self::FEBRERO + ['accion' => 'ver']));
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.excel-ingresos', self::FEBRERO))
            ->assertRedirect(route('reportes.ingresos.excel', self::FEBRERO));
        // Sin fechas: el reporte nuevo con su periodo por defecto
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.ingresos'))->assertRedirect(route('reportes.ingresos'));
        // Solo una fecha: se completa el rango
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.ingresos', ['fecha_inicio' => '2026-03-01']))
            ->assertRedirect(route('reportes.ingresos', ['fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-10-15']));

        // El PDF de transferencias sigue funcionando tal cual
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.pdf-ingresos-transferencias', self::FEBRERO))->assertOk();

        // Los enlaces viejos siguen cerrados para el asistente
        $this->actingAs($this->crearUsuarioConRol('asistente'))->get(route('ingresos-asistencia.ingresos'))->assertForbidden();
    }

    private function filtro(string $desde, string $hasta): FiltroCultos
    {
        // El alcance por iglesia sale del usuario con sesión, como en una petición
        $this->actingAs($this->admin);

        return FiltroCultos::deRango(CarbonImmutable::parse($desde), CarbonImmutable::parse($hasta));
    }
}

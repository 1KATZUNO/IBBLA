<?php

namespace Tests\Feature;

use App\Models\Culto;
use App\Models\Egreso;
use App\Models\OfrendaSuelta;
use App\Models\Tenant;
use App\Services\Reportes\Dinero;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\TestCase;

/**
 * Correcciones del QA final de la E2 (docs/plan-oct-2026/QA-FINAL.md): QF-001, QF-002, QF-004 y QF-005.
 * Escenario de reportes con "hoy" = 15/10/2026.
 */
class CorreccionesQaFinalTest extends TestCase
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

    /** QF-001: la plantilla de Excel para importar asistencia daba error 500. */
    public function test_la_plantilla_de_importacion_de_asistencia_se_descarga(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('asistencia.importar.plantilla'));

        $respuesta->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $respuesta->headers->get('content-type'));
    }

    /**
     * QF-002: la columna de efectivo del reporte de cultos muestra solo los sobres en efectivo, de modo que
     * sobres en efectivo + transferencias + suelto - egresos da el total que se ve al lado.
     */
    public function test_las_columnas_del_reporte_de_cultos_suman_el_total(): void
    {
        $culto = Culto::whereDate('fecha', '2026-02-15')->firstOrFail();
        $transferencia = $this->crearSobre($culto, $this->ana, ['diezmo' => 7000]);
        $transferencia->update(['metodo_pago' => 'transferencia']);
        OfrendaSuelta::create(['culto_id' => $culto->id, 'monto' => 2500, 'moneda' => 'CRC', 'metodo_pago' => 'efectivo']);
        Egreso::create(['culto_id' => $culto->id, 'monto' => 1500, 'moneda' => 'CRC']);

        $respuesta = $this->actingAs($this->crearUsuarioConRol('tesorero'))
            ->get(route('reportes.cultos', ['fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-02-28']))
            ->assertOk()
            ->assertSee('Sobres en efectivo')
            ->assertSee('Total = sobres en efectivo + transferencias + suelto − egresos.')
            ->assertDontSee('Total = efectivo (');

        $filas = collect($respuesta->viewData('reporte')->filas);
        $this->assertNotEmpty($filas);
        foreach ($filas as $fila) {
            $this->assertEqualsWithDelta(
                $fila->total,
                $fila->sobresEfectivo + $fila->transferencias + $fila->sueltos - $fila->egresos,
                0.01,
                'Las columnas del culto del '.$fila->culto->fecha->format('d/m/Y').' deben sumar su total'
            );
        }

        $fila = $filas->first(fn ($f) => $f->culto->fecha->toDateString() === '2026-02-15');
        $this->assertGreaterThan(0, $fila->sueltos);
        $this->assertGreaterThan(0, $fila->egresos);
        // Lo que se pinta en la columna es el efectivo de los sobres, no el neto con suelto y egresos
        $this->assertNotEquals(round($fila->sobresEfectivo, 2), round($fila->efectivo, 2));
        $respuesta->assertSee(Dinero::formato($fila->sobresEfectivo));
    }

    /** QF-004: el enlace viejo del PDF de ingresos conserva "descargar" al redirigir al reporte nuevo. */
    public function test_el_enlace_viejo_del_pdf_de_ingresos_conserva_descargar(): void
    {
        $rango = ['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-03-31'];

        $this->actingAs($this->admin)
            ->get(route('ingresos-asistencia.pdf-ingresos', $rango + ['accion' => 'descargar']))
            ->assertRedirect(route('reportes.ingresos.pdf', $rango + ['accion' => 'descargar']));
        $this->actingAs($this->admin)
            ->get(route('ingresos-asistencia.pdf-ingresos', $rango))
            ->assertRedirect(route('reportes.ingresos.pdf', $rango + ['accion' => 'ver']));
        $this->actingAs($this->admin)
            ->get(route('ingresos-asistencia.pdf-ingresos', $rango + ['accion' => 'otra-cosa']))
            ->assertRedirect(route('reportes.ingresos.pdf', $rango + ['accion' => 'ver']));
    }

    /** QF-005: una iglesia nueva nace con las categorias escritas con tilde y los mismos slugs. */
    public function test_las_categorias_por_defecto_llevan_tilde_y_conservan_el_slug(): void
    {
        $porSlug = collect(Tenant::defaultCategories())->pluck('nombre', 'slug');

        $this->assertSame('Construcción', $porSlug['construccion']);
        $this->assertSame('Préstamo', $porSlug['prestamo']);
    }
}

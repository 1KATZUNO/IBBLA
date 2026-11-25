<?php

namespace Tests\Feature\Reportes;

use App\Models\AuditLog;
use App\Models\Culto;
use App\Models\Egreso;
use App\Models\OfrendaSuelta;
use App\Services\Reportes\AuditoriaReportes;
use App\Services\Reportes\Cultos\FiltroCultos;
use App\Services\Reportes\Cultos\GeneradorReporteCultos;
use App\Services\Reportes\Exportaciones\CultosExcel;
use App\Support\ResumenCulto;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\TestCase;

/**
 * HU-R10 Reporte de cultos: permisos por rol, filtros, cifras iguales al recuento de cada culto
 * (ResumenCulto) al centimo, y PDF/Excel con la plantilla unica y auditados.
 */
class ReporteCultosTest extends TestCase
{
    use CreaEscenarioReportes;
    use RefreshDatabase;

    private const PRIMER_TRIMESTRE = ['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-03-31'];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->crearEscenarioReportes();

        // Lo que complica un culto: transferencia, suelto en dolares, egreso y firmas
        $culto = Culto::whereDate('fecha', '2026-02-15')->firstOrFail();
        $transferencia = $this->crearSobre($culto, $this->carla, ['diezmo' => 12345.67]);
        $transferencia->update(['metodo_pago' => 'transferencia']);
        OfrendaSuelta::create(['culto_id' => $culto->id, 'monto' => 10, 'moneda' => 'USD', 'tipo_cambio_venta' => 505, 'metodo_pago' => 'efectivo']);
        OfrendaSuelta::create(['culto_id' => $culto->id, 'monto' => 1500.25, 'moneda' => 'CRC', 'metodo_pago' => 'efectivo']);
        Egreso::create(['culto_id' => $culto->id, 'monto' => 2000, 'moneda' => 'CRC']);
        $culto->update([
            'cerrado' => true,
            'firma_pastor' => 'Pastor', 'firma_pastor_imagen' => 'data:image/png;base64,AAA',
            'firmas_tesoreros_imagenes' => [['nombre' => 'Tesorero', 'imagen' => 'data:image/png;base64,BBB']],
        ]);
        // Firmó solo el pastor: incompleto
        Culto::whereDate('fecha', '2026-01-04')->firstOrFail()->update(['firma_pastor_imagen' => 'data:image/png;base64,CCC', 'firmas_tesoreros_imagenes' => [['nombre' => 'T', 'imagen' => '']]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_y_tesorero_entran_asistente_y_miembro_no(): void
    {
        $tesorero = $this->crearUsuarioConRol('tesorero');
        $asistente = $this->crearUsuarioConRol('asistente');
        $miembro = $this->crearUsuarioConRol('miembro');
        foreach (['reportes.cultos', 'reportes.cultos.pdf', 'reportes.cultos.excel'] as $ruta) {
            $this->actingAs($this->admin)->get(route($ruta, self::PRIMER_TRIMESTRE))->assertOk();
            $this->actingAs($tesorero)->get(route($ruta, self::PRIMER_TRIMESTRE))->assertOk();
            $this->actingAs($asistente)->get(route($ruta, self::PRIMER_TRIMESTRE))->assertForbidden();
            $this->actingAs($miembro)->get(route($ruta, self::PRIMER_TRIMESTRE))->assertForbidden();
        }
    }

    public function test_cada_culto_cuadra_al_centimo_con_el_recuento(): void
    {
        $reporte = app(GeneradorReporteCultos::class)->generar($this->filtro('2026-01-01', '2026-12-31'));
        $categorias = tenant_categories(['es_ofrenda_suelta' => false]);

        $this->assertCount(13, $reporte->filas, 'Los 13 cultos de 2026 de esta iglesia (no los de otra ni de 2025)');
        foreach ($reporte->filas as $fila) {
            $culto = Culto::findOrFail($fila->culto->id);
            $esperado = ResumenCulto::calcular($culto->sobres()->with('detalles')->get(), $culto->ofrendasSueltas, $culto->egresos, $categorias);

            $this->assertSame($esperado->cantidadSobres, $fila->sobres);
            $this->assertEqualsWithDelta($esperado->total(), $fila->total, 0.001, 'Total del culto '.$culto->fecha->format('d/m/Y'));
            $this->assertEqualsWithDelta($esperado->efectivo(), $fila->efectivo, 0.001);
            $this->assertEqualsWithDelta($esperado->transferencias(), $fila->transferencias, 0.001);
            $this->assertEqualsWithDelta($esperado->suelto, $fila->sueltos, 0.001);
            $this->assertEqualsWithDelta($esperado->egresos, $fila->egresos, 0.001);
            foreach ($esperado->porCategoria as $slug => $monto) {
                $this->assertEqualsWithDelta($monto, $fila->porCategoria[$slug], 0.001);
            }
        }

        // El culto del 15/02 con todo: 25.000 efectivo + 12.345,67 transferencia + $10 a 505 + 1.500,25 suelto - 2.000
        $fila = collect($reporte->filas)->first(fn ($f) => $f->culto->fecha->toDateString() === '2026-02-15');
        $this->assertEqualsWithDelta(25000 + 5050 + 1500.25 - 2000, $fila->efectivo, 0.001);
        $this->assertEqualsWithDelta(12345.67, $fila->transferencias, 0.001);
        $this->assertEqualsWithDelta(25000 + 12345.67 + 5050 + 1500.25 - 2000, $fila->total, 0.001);
        $this->assertTrue($fila->firmasCompletas);
        $this->assertFalse(collect($reporte->filas)->first(fn ($f) => $f->culto->fecha->toDateString() === '2026-01-04')->firmasCompletas, 'Solo firmó el pastor');

        // Los desgloses suman los ingresos del rango (pro_templo va a "Otras categorías")
        $t = $reporte->totales();
        $this->assertEqualsWithDelta($t['ingresos'], array_sum(array_column($reporte->porCategoria(), 'monto')), 0.001);
        $this->assertEqualsWithDelta($t['ingresos'], array_sum(array_column($reporte->porMetodo(), 'monto')), 0.001);
        $this->assertContains('Otras categorías (ya no activas)', array_column($reporte->porCategoria(), 'nombre'));
        $this->assertEqualsWithDelta($t['ingresos'] - $t['egresos'], $t['total'], 0.001);
    }

    public function test_filtra_por_tipo_y_por_cultos_elegidos(): void
    {
        $todos = app(GeneradorReporteCultos::class)->generar($this->filtro('2026-01-01', '2026-03-31'));
        $this->assertCount(8, $todos->filas);

        $miercoles = Culto::whereDate('fecha', '2026-03-04')->firstOrFail();
        $elegido = $this->actingAs($this->admin)->get(route('reportes.cultos', self::PRIMER_TRIMESTRE + ['cultos' => [$miercoles->id]]))
            ->assertOk()
            ->assertSee('Elegir cultos (1 elegidos)')
            ->assertSee('1 culto elegido');
        $filas = $elegido->viewData('reporte')->filas;
        $this->assertCount(1, $filas);
        $this->assertSame('2026-03-04', $filas[0]->culto->fecha->toDateString());

        // Tipo domingo: el culto de miercoles queda fuera
        $soloDomingo = $this->actingAs($this->admin)->get(route('reportes.cultos', self::PRIMER_TRIMESTRE + ['tipos' => ['domingo']]))->assertOk();
        $this->assertSame(7, $soloDomingo->viewData('reporte')->totales()['cultos']);
    }

    public function test_sin_cultos_muestra_que_hacer(): void
    {
        $this->actingAs($this->admin)->get(route('reportes.cultos', ['fecha_inicio' => '2024-01-01', 'fecha_fin' => '2024-01-31']))
            ->assertOk()
            ->assertSee('No hay cultos en este rango con los filtros elegidos.')
            ->assertSee('Amplíe las fechas');
    }

    public function test_un_rango_de_mas_de_un_anio_se_recorta_con_aviso(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('reportes.cultos', ['fecha_inicio' => '2024-01-01', 'fecha_fin' => '2026-10-15']))->assertOk();

        $respuesta->assertSee('El rango máximo es de un año');
        $this->assertSame(FiltroCultos::MAX_DIAS, $respuesta->viewData('filtro')->rango->dias());
    }

    public function test_pdf_y_excel_dicen_lo_mismo_que_la_pantalla_y_quedan_auditados(): void
    {
        $pantalla = $this->actingAs($this->admin)->get(route('reportes.cultos', self::PRIMER_TRIMESTRE))->assertOk();
        $total = $pantalla->viewData('reporte')->totales()['total'];

        $pdf = $this->actingAs($this->admin)->get(route('reportes.cultos.pdf', self::PRIMER_TRIMESTRE + ['accion' => 'descargar']))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString('reporte_cultos_2026-01-01_a_2026-03-31.pdf', $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $excel = $this->actingAs($this->admin)->get(route('reportes.cultos.excel', self::PRIMER_TRIMESTRE))->assertOk();
        $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($archivo, $excel->getContent());
        $libro = IOFactory::load($archivo);
        @unlink($archivo);
        $this->assertSame(['Resumen', 'Por categoría y método', 'Detalle'], $libro->getSheetNames());
        $resumen = $libro->getSheetByName('Resumen');
        $filaTotal = null;
        foreach ($resumen->getRowIterator() as $fila) {
            if ($resumen->getCell('A'.$fila->getRowIndex())->getValue() === 'TOTAL') {
                $filaTotal = $fila->getRowIndex();
            }
        }
        $this->assertNotNull($filaTotal);
        $this->assertEqualsWithDelta($total, (float) $resumen->getCell('I'.$filaTotal)->getValue(), 0.001);

        $auditorias = AuditLog::where('action', AuditoriaReportes::ACCION)->get()->map(fn ($a) => json_decode($a->details, true));
        $this->assertSame(['pdf', 'excel'], $auditorias->where('reporte', 'cultos')->pluck('formato')->values()->all());
        $this->assertSame(8, $auditorias->first()['cultos']);
    }

    public function test_excel_sin_cultos_no_falla(): void
    {
        $reporte = app(GeneradorReporteCultos::class)->generar($this->filtro('2024-01-01', '2024-01-31'));
        $this->assertNotSame('', app(CultosExcel::class)->generar($reporte)->contenido());
    }

    private function filtro(string $desde, string $hasta): FiltroCultos
    {
        // El alcance por iglesia sale del usuario con sesión, como en una petición
        $this->actingAs($this->admin);

        return FiltroCultos::deRango(CarbonImmutable::parse($desde), CarbonImmutable::parse($hasta));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Promesa;
use App\Models\TipoCambio;
use App\Services\InformePersonaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * Cifras del informe individual. Hoy simulado: 15/10/2026 (periodo enero - octubre 2026).
 *
 * Datos de Ana (ventas de 2026 salvo indicación), dólar a ₡500 hoy:
 *   04/01 CRC   diezmo 30.000 + misiones 10.000
 *   01/02 CRC   misiones 10.000 + construccion 2.000
 *   08/02 USD   misiones $20 con tipo de cambio ₡510 del sobre = ₡10.200
 *   04/10 CRC   diezmo 40.000
 *   fuera:      28/12/2025 (otro año), 01/11/2026 (después del mes en curso), sobre de otra persona
 *
 * Promesas de Ana: misiones ₡10.000 mensual, construccion ₡2.000 semanal (43 domingos de enero a
 * octubre de 2026), seminario $5 quincenal (a ₡500: ₡5.000 por mes).
 */
class InformePersonaServiceTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->crearIglesiaConAdmin();
        $this->crearCategoriasPorDefecto();
        TipoCambio::create(['fecha' => '2026-10-15', 'compra' => 495, 'venta' => 500, 'source' => 'prueba']);
        app()->instance('current_tenant', $this->iglesia);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function servicio(): InformePersonaService
    {
        return app(InformePersonaService::class);
    }

    private function cargarAna(): Persona
    {
        $ana = $this->crearPersona('Ana Mora');
        $otra = $this->crearPersona('Beto Rojas', '7777-6666');

        $this->crearSobre($this->crearCulto('2026-01-04'), $ana, ['diezmo' => 30000, 'misiones' => 10000]);
        $this->crearSobre($this->crearCulto('2026-02-01'), $ana, ['misiones' => 10000, 'construccion' => 2000]);
        $this->crearSobre($this->crearCulto('2026-02-08'), $ana, ['misiones' => 20], 'USD', 510);
        $this->crearSobre($this->crearCulto('2026-10-04'), $ana, ['diezmo' => 40000]);
        $this->crearSobre($this->crearCulto('2025-12-28'), $ana, ['diezmo' => 99999]);
        $this->crearSobre($this->crearCulto('2026-11-01'), $ana, ['diezmo' => 88888]);
        $this->crearSobre($this->crearCulto('2026-03-01'), $otra, ['diezmo' => 77777]);

        Promesa::create(['persona_id' => $ana->id, 'categoria' => 'misiones', 'monto' => 10000, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);
        Promesa::create(['persona_id' => $ana->id, 'categoria' => 'construccion', 'monto' => 2000, 'moneda' => 'CRC', 'frecuencia' => 'semanal']);
        Promesa::create(['persona_id' => $ana->id, 'categoria' => 'seminario', 'monto' => 5, 'moneda' => 'USD', 'frecuencia' => 'quincenal']);

        return $ana;
    }

    public function test_suma_lo_dado_por_mes_y_categoria_solo_del_anio_y_la_persona(): void
    {
        $datos = $this->servicio()->datos($this->cargarAna());

        $this->assertSame(2026, $datos['anio']);
        $this->assertSame('Enero - Octubre 2026', $datos['periodo']);
        $this->assertCount(10, $datos['meses']);

        // Enero: diezmo y misiones. Febrero: misiones (₡10.000 + $20 x 510) y construcción.
        $this->assertEquals(30000, $datos['meses'][0]['por_categoria']['diezmo']);
        $this->assertEquals(10000, $datos['meses'][0]['por_categoria']['misiones']);
        $this->assertEquals(40000, $datos['meses'][0]['total']);
        $this->assertEquals(20200, $datos['meses'][1]['por_categoria']['misiones']);
        $this->assertEquals(2000, $datos['meses'][1]['por_categoria']['construccion']);
        $this->assertEquals(22200, $datos['meses'][1]['total']);
        $this->assertEquals(0, $datos['meses'][2]['total']);
        $this->assertEquals(40000, $datos['meses'][9]['total']);

        $this->assertEquals(70000, $datos['totales_categoria']['diezmo']);
        $this->assertEquals(30200, $datos['totales_categoria']['misiones']);
        $this->assertEquals(2000, $datos['totales_categoria']['construccion']);
        $this->assertEquals(102200, $datos['total_dado']);
        $this->assertTrue($datos['hay_usd']);
    }

    public function test_las_columnas_son_las_categorias_usadas_o_prometidas_en_el_orden_de_la_iglesia(): void
    {
        $datos = $this->servicio()->datos($this->cargarAna());

        // diezmo, misiones, seminario (prometida, aunque no ha dado), construccion; sin campa/micro/etc.
        $this->assertSame(['diezmo', 'misiones', 'seminario', 'construccion'], array_keys($datos['categorias']));
        $this->assertSame('Diezmo', $datos['categorias']['diezmo']);
    }

    public function test_calcula_lo_esperado_de_cada_compromiso_segun_su_frecuencia_y_moneda(): void
    {
        $datos = $this->servicio()->datos($this->cargarAna());
        $porCategoria = collect($datos['compromisos'])->keyBy('categoria');

        // Mensual: 10 meses x 10.000. Semanal: 43 domingos x 2.000. Quincenal en dólares: 2 x $5 x ₡500 x 10 meses.
        $this->assertEquals(100000, $porCategoria['misiones']['esperado']);
        $this->assertEquals(30200, $porCategoria['misiones']['dado']);
        $this->assertEquals(-69800, $porCategoria['misiones']['diferencia']);
        $this->assertFalse($porCategoria['misiones']['al_dia']);

        $this->assertEquals(86000, $porCategoria['construccion']['esperado']);
        $this->assertEquals(2000, $porCategoria['construccion']['dado']);

        $this->assertEquals(50000, $porCategoria['seminario']['esperado']);
        $this->assertEquals(0, $porCategoria['seminario']['dado']);

        $this->assertSame('₡10,000.00 mensual', $porCategoria['misiones']['detalle']);
        $this->assertSame('₡2,000.00 cada domingo', $porCategoria['construccion']['detalle']);
        $this->assertSame('$5.00 cada quincena', $porCategoria['seminario']['detalle']);

        $this->assertTrue($datos['tiene_compromisos']);
        $this->assertEquals(236000, $datos['total_esperado']);
        // La diferencia compara solo lo dado en categorías prometidas: el diezmo no la infla.
        $this->assertEquals(32200, $datos['dado_en_compromisos']);
        $this->assertEquals(-203800, $datos['diferencia']);
    }

    public function test_un_compromiso_cumplido_aparece_al_dia(): void
    {
        $ana = $this->crearPersona('Ana Mora');
        Promesa::create(['persona_id' => $ana->id, 'categoria' => 'misiones', 'monto' => 1000, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);
        $this->crearSobre($this->crearCulto('2026-03-01'), $ana, ['misiones' => 10000]);

        $datos = $this->servicio()->datos($ana);

        $this->assertTrue($datos['compromisos'][0]['al_dia']);
        $this->assertEquals(0, $datos['diferencia']);
    }

    public function test_sin_promesas_no_hay_compromisos_pero_si_el_total_contribuido(): void
    {
        $ana = $this->crearPersona('Ana Mora');
        $this->crearSobre($this->crearCulto('2026-05-03'), $ana, ['diezmo' => 5000]);

        $datos = $this->servicio()->datos($ana);

        $this->assertFalse($datos['tiene_compromisos']);
        $this->assertSame([], $datos['compromisos']);
        $this->assertEquals(5000, $datos['total_dado']);
        $this->assertTrue($datos['hay_datos']);
    }

    public function test_sin_sobres_ni_promesas_no_hay_datos(): void
    {
        $datos = $this->servicio()->datos($this->crearPersona('Ana Mora'));

        $this->assertFalse($datos['hay_datos']);
        $this->assertSame([], $datos['categorias']);
        $this->assertEquals(0, $datos['total_dado']);
    }

    public function test_una_categoria_que_ya_no_existe_se_muestra_y_los_totales_cuadran(): void
    {
        $ana = $this->crearPersona('Ana Mora');
        $this->crearSobre($this->crearCulto('2026-05-03'), $ana, ['diezmo' => 1000, 'pro_templo' => 500]);

        $datos = $this->servicio()->datos($ana);

        $this->assertSame(['diezmo', 'pro_templo'], array_keys($datos['categorias']));
        $this->assertSame('Pro Templo', $datos['categorias']['pro_templo']);
        $this->assertEquals(1500, $datos['total_dado']);
    }

    public function test_un_sobre_en_dolares_sin_tipo_de_cambio_usa_el_de_hoy(): void
    {
        $ana = $this->crearPersona('Ana Mora');
        $this->crearSobre($this->crearCulto('2026-05-03'), $ana, ['misiones' => 10], 'USD', null);

        $this->assertEquals(5000, $this->servicio()->datos($ana)['total_dado']);
    }

    public function test_el_nombre_del_archivo_lleva_la_persona_y_el_anio(): void
    {
        $this->assertSame('informe-ana-mora-2026.pdf', $this->servicio()->nombreArchivo($this->crearPersona('Ana Mora')));
        $this->assertSame('informe-persona-2026.pdf', $this->servicio()->nombreArchivo($this->crearPersona('???')));
    }

    public function test_el_pdf_se_genera_con_datos_y_sin_datos(): void
    {
        foreach ([$this->cargarAna(), $this->crearPersona('Sin Datos', '8888-0000')] as $persona) {
            $salida = $this->servicio()->pdf($persona)->output();

            $this->assertStringStartsWith('%PDF', $salida);
        }
    }
}

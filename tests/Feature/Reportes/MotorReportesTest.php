<?php

namespace Tests\Feature\Reportes;

use App\Models\Persona;
use App\Models\TipoCambio;
use App\Services\Reportes\EstadoCuenta;
use App\Services\Reportes\MotorReportes;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Reportes\ResultadoMotor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\TestCase;

/**
 * Motor único de reportes: lo dado (con dólares convertidos), lo esperado por promesa y el estado
 * con corte. Escenario en CreaEscenarioReportes.
 *
 *   DB_CONNECTION=mysql DB_DATABASE=ibbla_x_test php vendor/bin/phpunit --filter MotorReportesTest
 */
class MotorReportesTest extends TestCase
{
    use CreaEscenarioReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->crearEscenarioReportes();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function calcular(PeriodoReporte $periodo, bool $conDetalle = false): ResultadoMotor
    {
        return app(MotorReportes::class)->calcular($periodo, [$this->ana, $this->beto, $this->carla], $conDetalle);
    }

    public function test_lo_dado_convierte_los_dolares_con_el_tipo_de_cambio_del_sobre_o_el_de_hoy(): void
    {
        $ana = $this->calcular(PeriodoReporte::deMeses(2026, 1, 3))->cuenta($this->ana->id);

        // misiones: 10.000,50 + 10.000 + $20 x 510 del sobre; seminario: $7,25 x 500 de hoy (el sobre no lo guardó)
        $this->assertSame(['diezmo' => 30000.0, 'misiones' => 30200.5, 'construccion' => 2000.0, 'campa' => 3000.0, 'seminario' => 3625.0], $ana->dadoPorCategoria());
        $this->assertEquals(10000.5, $ana->dado('2026-01', ['misiones']));
        $this->assertEquals(40000.5, $ana->dado('2026-01'));
        $this->assertTrue($ana->hayUsd);

        // Para el estado cuenta solo lo dado en las categorías en que prometió (regla 2.1): ni el
        // diezmo ni la campa (categoría de compromiso, pero sin promesa de Ana).
        $this->assertEquals(35825.5, $ana->dadoEnPromesas());
        $this->assertEquals(35825.5, $ana->estado()->haDado());
        // Informativo: todo lo dado en categorías de compromiso.
        $this->assertEquals(38825.5, $ana->dadoEnCompromisos());
    }

    public function test_sin_ningun_tipo_de_cambio_los_dolares_quedan_tal_cual(): void
    {
        TipoCambio::query()->delete();

        $ana = $this->calcular(PeriodoReporte::deMeses(2026, 3, 3))->cuenta($this->ana->id);

        $this->assertEquals(7.25, $ana->dado(null, ['seminario']));
    }

    public function test_lo_esperado_sigue_la_frecuencia_y_convierte_las_promesas_en_dolares(): void
    {
        $ana = $this->calcular(PeriodoReporte::deMeses(2026, 1, 3))->cuenta($this->ana->id);

        // Enero: misiones 10.000 mensual, construcción 2.000 x 4 domingos, seminario $5 x 2 x ₡500.
        $this->assertEquals(10000, $ana->esperado('2026-01', 'misiones'));
        $this->assertEquals(8000, $ana->esperado('2026-01', 'construccion'));
        $this->assertEquals(5000, $ana->esperado('2026-01', 'seminario'));
        // Marzo tiene 5 domingos.
        $this->assertEquals(10000, $ana->esperado('2026-03', 'construccion'));
        // Enero a marzo: 30.000 + 2.000 x 13 + 15.000.
        $this->assertEquals(71000, $ana->esperado());
        $this->assertSame(['misiones', 'seminario', 'construccion'], array_keys($ana->promesas()));
    }

    public function test_las_promesas_de_una_categoria_se_suman_y_las_excluidas_no_cuentan(): void
    {
        $carla = $this->calcular(PeriodoReporte::deMeses(2026, 1, 1))->cuenta($this->carla->id);

        // campa: 2.000 y 3.000 -> 5.000. El diezmo prometido no se espera. micro quincenal: 1.500 x 2.
        $this->assertSame(['campa', 'micro'], array_keys($carla->promesas()));
        $this->assertEquals(5000, $carla->esperado('2026-01', 'campa'));
        $this->assertSame('₡2,000.00 mensual + ₡3,000.00 mensual', $carla->promesas()['campa']->descripcion());
        $this->assertEquals(0, $carla->esperado('2026-01', 'diezmo'));
        $this->assertEquals(8000, $carla->esperado('2026-01'));
    }

    public function test_al_corte_el_mes_en_curso_no_se_cobra_pero_lo_dado_hasta_el_corte_si_cuenta(): void
    {
        $resultado = $this->calcular(PeriodoReporte::alCorte('2026-10-13'));
        $estado = $resultado->cuenta($this->ana->id)->estado();

        // Se cobra enero a septiembre: misiones 9 x 10.000 + construcción 2.000 x 39 domingos + seminario 9 x 5.000.
        $this->assertEquals(213000, $estado->leCorresponde());
        // Dado hasta el 13/10 (incluido el 04/10) en las categorías prometidas; el 01/11 no.
        $this->assertEquals(58826.25, $estado->haDado());
        $this->assertEquals(-154173.75, $estado->diferencia());
        $this->assertSame(EstadoCuenta::PENDIENTE, $estado->etiqueta());

        // Octubre se muestra (lo del mes, informativo) pero no se cobra.
        $this->assertEquals(0, $resultado->cuenta($this->ana->id)->esperado('2026-10'));
        $this->assertEquals(10000 + 8000 + 5000, $resultado->cuenta($this->ana->id)->esperadoDelMes('2026-10'));
        $this->assertSame('2026-10', $resultado->periodo->mesEnCurso()?->clave);
    }

    public function test_si_el_corte_es_el_ultimo_dia_del_mes_ese_mes_si_se_cobra(): void
    {
        Carbon::setTestNow('2026-11-01 10:00:00');
        $carla = $this->calcular(PeriodoReporte::alCorte('2026-10-31'))->cuenta($this->carla->id);

        $this->assertEquals(10 * 8000, $carla->estado()->leCorresponde());
        $this->assertEquals(13000.1, $carla->estado()->haDado());
    }

    public function test_quien_da_lo_que_le_corresponde_esta_al_dia_aunque_falte_un_mes(): void
    {
        $persona = $this->crearPersona('Elena Vargas', '8000-0000');
        $this->promesa($persona, 'misiones', 1000, 'mensual');
        // Nada en enero; en febrero repone los dos meses.
        $this->crearSobre($this->crearCulto('2026-02-22'), $persona, ['misiones' => 2000]);

        $estado = app(MotorReportes::class)->calcular(PeriodoReporte::alCorte('2026-03-10'), [$persona])->cuenta($persona->id)->estado();

        $this->assertEquals(2000, $estado->leCorresponde());
        $this->assertTrue($estado->alDia());
        $this->assertSame(EstadoCuenta::AL_DIA, $estado->etiqueta());
    }

    public function test_nada_posterior_a_hoy_cuenta(): void
    {
        // Hoy 13/10: el anio completo se recorta a hoy; ni el sobre del 01/11 ni noviembre-diciembre.
        $resultado = $this->calcular(PeriodoReporte::deMeses(2026, 1, 12));
        $ana = $resultado->cuenta($this->ana->id);

        $this->assertSame('2026-10-13', $resultado->periodo->hasta->toDateString());
        $this->assertSame('Corte al 13/10/2026', $resultado->periodo->etiqueta());
        $this->assertEquals(0, $ana->dado('2026-11'));
        $this->assertEquals(213000, $ana->estado()->leCorresponde());
        $this->assertEquals($this->calcular(PeriodoReporte::alCorte('2026-10-13'))->cuenta($this->ana->id)->estado(), $ana->estado());
    }

    public function test_al_dia_con_tolerancia_de_un_colon(): void
    {
        $persona = $this->crearPersona('Elena Vargas', '8000-0000');
        $this->promesa($persona, 'misiones', 1000, 'mensual');
        $this->crearSobre($this->crearCulto('2026-01-11'), $persona, ['misiones' => 999.01]);
        $estado = app(MotorReportes::class)->calcular(PeriodoReporte::alCorte('2026-02-10'), [$persona])->cuenta($persona->id)->estado();
        $this->assertSame(EstadoCuenta::AL_DIA, $estado->etiqueta());

        $this->crearSobre($this->crearCulto('2026-03-08'), $persona, ['misiones' => 999.98]);
        $estado = app(MotorReportes::class)->calcular(PeriodoReporte::alCorte('2026-03-31'), [$persona])->cuenta($persona->id)->estado();
        // Le correspondían 3.000 y dio 1.998,99: falta más de un colón.
        $this->assertSame(EstadoCuenta::PENDIENTE, $estado->etiqueta());
    }

    public function test_dolares_sin_tipo_de_cambio_en_el_sobre_usan_el_de_la_fecha_del_culto_y_las_promesas_el_de_fin_de_mes(): void
    {
        TipoCambio::create(['fecha' => '2026-02-28', 'compra' => 515, 'venta' => 520, 'source' => 'prueba']);
        TipoCambio::create(['fecha' => '2026-03-05', 'compra' => 525, 'venta' => 530, 'source' => 'prueba']);
        TipoCambio::create(['fecha' => '2026-10-10', 'compra' => 535, 'venta' => 540, 'source' => 'prueba']);

        $ana = $this->calcular(PeriodoReporte::alCorte('2026-10-13'), conDetalle: true)->cuenta($this->ana->id);

        // Sobre del 04/03 sin tipo de cambio: el vigente ese dia (28/02, ₡520), no el de hoy (₡540).
        $this->assertEquals(7.25 * 520, $ana->dado('2026-03', ['seminario']));
        $this->assertEquals(520, collect($ana->detalle)->firstWhere('categoria', 'seminario')['tipo_cambio']);
        // El sobre que guardó su tipo de cambio (₡510) lo conserva.
        $this->assertEquals(20200, $ana->dado('2026-02', ['misiones']));
        // Promesa de $5 quincenal: enero al 31/01 (₡500), febrero al 28/02 (₡520), marzo al 31/03 (₡530),
        // octubre en curso al de hoy (₡540, solo informativo).
        $this->assertEquals(5000, $ana->esperado('2026-01', 'seminario'));
        $this->assertEquals(5200, $ana->esperado('2026-02', 'seminario'));
        $this->assertEquals(5300, $ana->esperado('2026-03', 'seminario'));
        $this->assertEquals(5400, $ana->esperadoDelMes('2026-10', 'seminario'));
    }

    public function test_sin_promesa_el_estado_es_sin_promesa(): void
    {
        $beto = $this->calcular(PeriodoReporte::alCorte('2026-10-13'))->cuenta($this->beto->id);

        $this->assertFalse($beto->tienePromesa());
        $this->assertSame(EstadoCuenta::SIN_PROMESA, $beto->estado()->etiqueta());
        $this->assertEquals(6234.56, $beto->estado()->haDado());
        $this->assertTrue($beto->tieneMovimientoEnCompromisos());
    }

    public function test_meses_sin_aporte_y_ultimo_aporte(): void
    {
        $resultado = $this->calcular(PeriodoReporte::alCorte('2026-10-13'));

        $ana = $resultado->cuenta($this->ana->id);
        $this->assertSame(['Abril', 'Mayo', 'Julio', 'Agosto', 'Septiembre'], array_map(fn ($m) => $m->nombre, $ana->mesesSinAporte()));
        $this->assertSame('2026-10-04', $ana->ultimoAporte?->toDateString());

        $carla = $resultado->cuenta($this->carla->id);
        $this->assertSame(['Febrero', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto'], array_map(fn ($m) => $m->nombre, $carla->mesesSinAporte()));
        // El último aporte cuenta solo categorías de compromiso: el de pro_templo no.
        $this->assertSame('2026-09-06', $carla->ultimoAporte?->toDateString());
        $this->assertSame('2026-08-02', $resultado->cuenta($this->beto->id)->ultimoAporte?->toDateString());
    }

    public function test_con_fechas_exactas_se_cobran_solo_los_meses_completos(): void
    {
        $ana = $this->calcular(PeriodoReporte::deFechas('2026-03-15', '2026-05-10'))->cuenta($this->ana->id);

        // Marzo y mayo quedan a medias: solo abril se cobra (10.000 + 2.000 x 4 + 5.000).
        $this->assertEquals(23000, $ana->estado()->leCorresponde());
        // El 04/03 queda antes del 15/03.
        $this->assertEquals(0, $ana->dado());
    }

    public function test_cada_persona_tiene_solo_lo_suyo_y_respeta_el_orden_pedido(): void
    {
        $resultado = app(MotorReportes::class)->calcular(PeriodoReporte::deMeses(2026, 1, 12), [$this->carla, $this->beto]);

        $this->assertSame([$this->carla->id, $this->beto->id], array_keys($resultado->cuentas()));
        $this->assertNull($resultado->cuenta($this->ana->id));
        $this->assertEquals(26234.56, $resultado->cuenta($this->beto->id)->dado());
        $this->assertSame(['Diezmo', 'Ofrenda Especial', 'Misiones', 'Seminario', 'Construcción', 'CAMPA', 'Préstamo', 'Micro'], array_values($resultado->categorias));
        $this->assertSame('Pro Templo', $resultado->nombreCategoria('pro_templo'));
    }

    public function test_el_detalle_trae_un_renglon_por_monto_con_su_conversion(): void
    {
        $ana = $this->calcular(PeriodoReporte::deMeses(2026, 2, 2), conDetalle: true)->cuenta($this->ana->id);

        $this->assertCount(4, $ana->detalle);
        $usd = collect($ana->detalle)->firstWhere('moneda', 'USD');
        $this->assertSame('2026-02-08', $usd['fecha']);
        $this->assertSame('misiones', $usd['categoria']);
        $this->assertEquals(20, $usd['monto']);
        $this->assertEquals(510, $usd['tipo_cambio']);
        $this->assertEquals(10200, $usd['colones']);
    }

    public function test_sin_personas_no_consulta_nada_y_devuelve_vacio(): void
    {
        $resultado = app(MotorReportes::class)->calcular(PeriodoReporte::deMeses(2026, 1, 12), Persona::whereRaw('1 = 0')->get());

        $this->assertCount(0, $resultado);
    }
}

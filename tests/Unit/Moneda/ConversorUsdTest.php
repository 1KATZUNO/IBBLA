<?php

namespace Tests\Unit\Moneda;

use App\Support\Moneda\ConversorUsd;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/** Regla 2.3 de conversion de dolares (04-DECISIONES.md). */
class ConversorUsdTest extends TestCase
{
    private ConversorUsd $conversor;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->conversor = new ConversorUsd([
            '2026-01-01' => 500,
            '2026-03-31' => 505.5,
            '2026-06-15' => 510,
            '2026-10-14' => 520,
            '2026-12-01' => 999, // posterior a hoy: no se usa como "hoy"
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sobre_usa_primero_el_tipo_de_cambio_del_sobre(): void
    {
        $this->assertSame(5150.0, $this->conversor->sobreAColones(10, 'USD', 515, '2026-02-01'));
        $this->assertSame(515.0, $this->conversor->tipoCambioDeSobre('515.0000', '2026-02-01'));
    }

    public function test_sobre_sin_tipo_de_cambio_usa_el_de_la_fecha_del_culto(): void
    {
        // Vigente al 20/06: el del 15/06.
        $this->assertSame(5100.0, $this->conversor->sobreAColones(10, 'USD', null, '2026-06-20'));
        $this->assertSame(510.0, $this->conversor->tipoCambioDeSobre(0, Carbon::parse('2026-06-20')));
        // Justo el dia del cambio.
        $this->assertSame(505.5, $this->conversor->tipoCambioDeSobre(null, '2026-03-31'));
    }

    public function test_sobre_anterior_a_todos_los_tipos_de_cambio_usa_el_de_hoy(): void
    {
        $this->assertSame(520.0, $this->conversor->tipoCambioDeSobre(null, '2025-12-28'));
        $this->assertSame(520.0, $this->conversor->tipoCambioHoy());
    }

    public function test_colones_quedan_igual_y_se_redondea_a_dos_decimales(): void
    {
        $this->assertSame(1234.57, $this->conversor->sobreAColones('1234.567', 'CRC', 600, '2026-01-01'));
        $this->assertSame(3729.13, $this->conversor->sobreAColones(7.25, 'USD', 514.363, null));
        $this->assertSame(372913, $this->conversor->centimosSobre(7.25, 'USD', 514.363, null));
    }

    public function test_promesa_usa_el_tipo_de_cambio_del_ultimo_dia_del_mes(): void
    {
        $this->assertSame(500.0, $this->conversor->tipoCambioDePromesa(2026, 2));
        $this->assertSame(505.5, $this->conversor->tipoCambioDePromesa(2026, 3)); // 31/03 exacto
        $this->assertSame(510.0, $this->conversor->tipoCambioDePromesa(2026, 9));
        // Mes en curso y futuro: el de hoy.
        $this->assertSame(520.0, $this->conversor->tipoCambioDePromesa(2026, 10));
        $this->assertSame(520.0, $this->conversor->tipoCambioDePromesa(2026, 12));
        $this->assertSame(11000.0, $this->conversor->promesaAColones(22, 'USD', 2026, 1));
        $this->assertSame(4000.0, $this->conversor->promesaAColones(4000, 'CRC', 2026, 1));
    }

    public function test_sin_tipos_de_cambio_el_monto_queda_igual(): void
    {
        $vacio = new ConversorUsd([]);
        $this->assertSame(0.0, $vacio->tipoCambioHoy());
        $this->assertSame(22.0, $vacio->sobreAColones(22, 'USD', null, '2026-01-01'));
        $this->assertSame(22.0, $vacio->promesaAColones(22, 'USD', 2026, 1));
    }
}

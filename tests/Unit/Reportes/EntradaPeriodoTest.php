<?php

namespace Tests\Unit\Reportes;

use App\Support\Reportes\EntradaPeriodo;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Periodo de los reportes leido de la URL. "Hoy" = 13/10/2026. Usa Tests\TestCase (sin BD) por el
 * reloj de Laravel.
 */
class EntradaPeriodoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-13 09:00:00');
    }

    private function entrada(array $query = [], string $porDefecto = EntradaPeriodo::MESES): EntradaPeriodo
    {
        return EntradaPeriodo::desde(Request::create('/reportes', 'GET', $query), $porDefecto);
    }

    private function rango(EntradaPeriodo $entrada): string
    {
        return $entrada->periodo->desde->toDateString().' / '.$entrada->periodo->hasta->toDateString();
    }

    public function test_por_defecto_enero_al_mes_actual_o_corte_hoy(): void
    {
        $meses = $this->entrada();
        $this->assertSame('2026-01-01 / 2026-10-31', $this->rango($meses));
        $this->assertSame(EntradaPeriodo::MESES, $meses->modo);
        $this->assertFalse($meses->tieneErrores());

        $corte = $this->entrada([], EntradaPeriodo::CORTE);
        $this->assertSame('2026-01-01 / 2026-10-13', $this->rango($corte));
        $this->assertSame('2026-10-13', $corte->periodo->corte?->toDateString());
    }

    public function test_meses_de_un_anio_anterior_llegan_a_diciembre(): void
    {
        $this->assertSame('2025-01-01 / 2025-12-31', $this->rango($this->entrada(['anio' => 2025])));
        $this->assertSame('2026-03-01 / 2026-06-30', $this->rango($this->entrada(['anio' => 2026, 'mes_desde' => 3, 'mes_hasta' => 6])));
        $this->assertSame('2025-11-01 / 2026-02-28', $this->rango($this->entrada(['anio' => 2025, 'mes_desde' => 11, 'anio_hasta' => 2026, 'mes_hasta' => 2])));
    }

    public function test_se_deduce_el_modo_por_los_campos(): void
    {
        $this->assertSame(EntradaPeriodo::CORTE, $this->entrada(['corte' => '2026-09-15'])->modo);
        $this->assertSame(EntradaPeriodo::FECHAS, $this->entrada(['desde' => '2026-03-15', 'hasta' => '2026-05-10'])->modo);
        $this->assertSame('2026-03-15 / 2026-05-10', $this->rango($this->entrada(['desde' => '2026-03-15', 'hasta' => '2026-05-10'])));
    }

    public function test_un_periodo_invalido_cae_al_de_por_defecto_y_explica_por_que(): void
    {
        $alReves = $this->entrada(['anio' => 2026, 'mes_desde' => 9, 'mes_hasta' => 2]);
        $this->assertTrue($alReves->tieneErrores());
        $this->assertStringContainsString('Septiembre', $alReves->errores->first('periodo'));
        $this->assertSame('2026-01-01 / 2026-10-31', $this->rango($alReves));

        $largo = $this->entrada(['desde' => '2024-01-01', 'hasta' => '2026-10-01']);
        $this->assertStringContainsString('máximo es 24', $largo->errores->first('periodo'));

        $fecha = $this->entrada(['corte' => '13/10/2026']);
        $this->assertStringContainsString('no es válida', $fecha->errores->first('periodo'));
        $this->assertSame('2026-10-13', $fecha->periodo->corte?->toDateString());

        $futuro = $this->entrada(['corte' => '2026-12-31']);
        $this->assertStringContainsString('posterior a hoy', $futuro->errores->first('periodo'));

        $this->assertTrue($this->entrada(['periodo' => 'trimestre'])->tieneErrores());
        $this->assertTrue($this->entrada(['anio' => 'dos mil'])->tieneErrores());
        $this->assertTrue($this->entrada(['desde' => ['2026-01-01']])->tieneErrores());
    }

    public function test_los_parametros_repiten_el_mismo_periodo(): void
    {
        foreach ([
            ['periodo' => 'corte', 'corte' => '2026-09-15'],
            ['periodo' => 'meses', 'anio' => 2026, 'mes_desde' => 2, 'mes_hasta' => 7],
            ['periodo' => 'meses', 'anio' => 2025, 'mes_desde' => 11, 'mes_hasta' => 2, 'anio_hasta' => 2026],
            ['periodo' => 'fechas', 'desde' => '2026-03-15', 'hasta' => '2026-05-10'],
        ] as $query) {
            $entrada = $this->entrada($query);
            $this->assertFalse($entrada->tieneErrores());
            $this->assertEquals($query, $entrada->aParametros());
            $this->assertSame($this->rango($entrada), $this->rango($this->entrada($entrada->aParametros())));
        }
    }
}

<?php

namespace Tests\Unit;

use App\Support\RangoFechas;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Resolución del período de los dashboards a partir de la URL, y su aritmética de meses.
 * Usa Tests\TestCase (no hay BD) porque la validación necesita el contenedor de Laravel.
 * "Hoy" = 15/10/2026.
 */
class RangoFechasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 09:00:00');
    }

    private function rango(array $query = [], string $porDefecto = RangoFechas::MODO_MES): RangoFechas
    {
        return RangoFechas::desdeRequest(Request::create('/dashboard', 'GET', $query), $porDefecto);
    }

    private function fechas(RangoFechas $rango): string
    {
        return $rango->inicio->toDateString().' / '.$rango->fin->toDateString();
    }

    // ------------------------------------------------------------------ resolución

    public function test_por_defecto_es_el_mes_actual_o_el_año_actual_segun_el_dashboard(): void
    {
        $this->assertSame('2026-10-01 / 2026-10-31', $this->fechas($this->rango()));
        $this->assertSame('2026-01-01 / 2026-12-31', $this->fechas($this->rango([], RangoFechas::MODO_ANIO)));
    }

    public function test_mes_y_año_dan_el_mes_con_sus_dias_reales(): void
    {
        $this->assertSame('2026-02-01 / 2026-02-28', $this->fechas($this->rango(['mes' => '2', 'año' => '2026'])));
        $this->assertSame('2028-02-01 / 2028-02-29', $this->fechas($this->rango(['mes' => '2', 'año' => '2028'])), 'Año bisiesto.');
        $this->assertSame('2026-04-01 / 2026-04-30', $this->fechas($this->rango(['mes' => '4'])), 'Sin año usa el actual.');
    }

    public function test_el_año_sin_mes_es_el_año_completo_aunque_el_defecto_sea_el_mes(): void
    {
        $this->assertSame('2025-01-01 / 2025-12-31', $this->fechas($this->rango(['año' => '2025'])));
        $this->assertSame('2025-01-01 / 2025-12-31', $this->fechas($this->rango(['mes' => '', 'año' => '2025'])));
    }

    public function test_las_fechas_ganan_y_el_rango_va_de_las_00_00_a_las_23_59_59(): void
    {
        $rango = $this->rango(['fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-10-20', 'mes' => '1', 'año' => '2020']);

        $this->assertSame('2026-09-15 00:00:00', $rango->inicio->toDateTimeString());
        $this->assertSame('2026-10-20 23:59:59', $rango->fin->toDateTimeString());
        $this->assertSame(RangoFechas::MODO_PERSONALIZADO, $rango->modo);
        $this->assertTrue($rango->errores->isEmpty());
    }

    public function test_un_dia_suelto_es_un_rango_valido(): void
    {
        $rango = $this->rango(['fecha_inicio' => '2026-10-04', 'fecha_fin' => '2026-10-04']);

        $this->assertSame(1, $rango->dias());
        $this->assertTrue($rango->errores->isEmpty());
    }

    public function test_lo_invalido_cae_al_defecto_y_deja_los_motivos(): void
    {
        $rango = $this->rango(['fecha_inicio' => '2026-10-31', 'fecha_fin' => '2026-10-01']);

        $this->assertSame('2026-10-01 / 2026-10-31', $this->fechas($rango));
        $this->assertSame(['fecha_fin'], $rango->errores->keys());
    }

    public function test_un_mes_invalido_se_ignora_junto_con_el_año(): void
    {
        $rango = $this->rango(['mes' => '14', 'año' => '2025']);

        $this->assertSame('2026-10-01 / 2026-10-31', $this->fechas($rango));
        $this->assertTrue($rango->errores->has('mes'));
    }

    // ------------------------------------------------------------------ aritmética

    public function test_cuenta_dias_y_meses_y_reconoce_los_meses_completos(): void
    {
        $dosMeses = $this->rango(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31']);
        $this->assertSame(61, $dosMeses->dias());
        $this->assertSame(2, $dosMeses->cantidadMeses());
        $this->assertTrue($dosMeses->esMesesCompletos());
        $this->assertFalse($dosMeses->esMesCompleto());
        $this->assertFalse($dosMeses->esAnioCompleto());

        $mes = $this->rango(['mes' => '2', 'año' => '2026']);
        $this->assertTrue($mes->esMesCompleto());
        $this->assertSame(28, $mes->dias());

        $anio = $this->rango(['año' => '2026']);
        $this->assertTrue($anio->esAnioCompleto());
        $this->assertSame(365, $anio->dias());

        $parcial = $this->rango(['fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-10-20']);
        $this->assertFalse($parcial->esMesesCompletos());
        $this->assertSame(36, $parcial->dias());
        $this->assertSame(2, $parcial->cantidadMeses());
    }

    public function test_los_meses_tocados_cruzan_de_año(): void
    {
        $meses = $this->rango(['fecha_inicio' => '2025-11-20', 'fecha_fin' => '2026-02-03'])->mesesTocados();

        $this->assertSame(['2025-11-01', '2025-12-01', '2026-01-01', '2026-02-01'], $meses->map->toDateString()->all());
    }

    public function test_ampliar_a_meses_completos(): void
    {
        $ampliado = $this->rango(['fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-10-20'])->ampliadoAMeses();

        $this->assertSame('2026-09-01 / 2026-10-31', $this->fechas($ampliado));
        $this->assertTrue($ampliado->esMesesCompletos());
    }

    public function test_el_periodo_anterior_de_meses_completos_son_los_mismos_meses_antes(): void
    {
        $this->assertSame('2026-09-01 / 2026-09-30', $this->fechas($this->rango(['mes' => '10', 'año' => '2026'])->anterior()));
        $this->assertSame('2025-12-01 / 2025-12-31', $this->fechas($this->rango(['mes' => '1', 'año' => '2026'])->anterior()), 'Enero retrocede a diciembre del año anterior.');
        $this->assertSame('2026-07-01 / 2026-08-31', $this->fechas($this->rango(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31'])->anterior()));
        $this->assertSame('2025-10-01 / 2025-12-31', $this->fechas($this->rango(['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-03-31'])->anterior()));
        $this->assertSame('2025-01-01 / 2025-12-31', $this->fechas($this->rango(['año' => '2026'])->anterior()));
        $this->assertSame('2026-02-01 / 2026-02-28', $this->fechas($this->rango(['mes' => '3', 'año' => '2026'])->anterior()), 'Marzo (31 días) contra febrero (28).');
    }

    public function test_el_periodo_anterior_de_un_rango_libre_son_los_mismos_dias_justo_antes(): void
    {
        $this->assertSame('2026-08-10 / 2026-09-14', $this->fechas($this->rango(['fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-10-20'])->anterior()));
        $this->assertSame('2026-10-03 / 2026-10-03', $this->fechas($this->rango(['fecha_inicio' => '2026-10-04', 'fecha_fin' => '2026-10-04'])->anterior()));
        // 7 días que cruzan de año
        $this->assertSame('2025-12-25 / 2025-12-31', $this->fechas($this->rango(['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-01-07'])->anterior()));
    }

    public function test_etiquetas(): void
    {
        $this->assertSame('octubre 2026', $this->rango()->etiqueta());
        $this->assertSame('2026', $this->rango(['año' => '2026'])->etiqueta());
        $this->assertSame('15/09/2026 – 20/10/2026', $this->rango(['fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-10-20'])->etiqueta());
        $this->assertSame('01/09/2026 – 31/10/2026', $this->rango(['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-31'])->etiqueta());
    }

    public function test_seleccion_del_acceso_rapido(): void
    {
        $mes = $this->rango(['mes' => '3', 'año' => '2025']);
        $this->assertSame(3, $mes->mesSeleccionado());
        $this->assertSame(2025, $mes->anioSeleccionado());

        $this->assertSame('', $this->rango(['año' => '2025'])->mesSeleccionado(), "'' = todo el año");
        // un rango libre que equivale a un año completo también se muestra como "todo el año"
        $this->assertSame('', $this->rango(['fecha_inicio' => '2025-01-01', 'fecha_fin' => '2025-12-31'])->mesSeleccionado());
        // uno parcial muestra su mes de inicio
        $this->assertSame(9, $this->rango(['fecha_inicio' => '2026-09-15', 'fecha_fin' => '2026-10-20'])->mesSeleccionado());
    }

    public function test_años_para_el_selector_incluye_los_con_datos_el_actual_y_los_del_rango(): void
    {
        $rango = $this->rango(['fecha_inicio' => '2023-05-01', 'fecha_fin' => '2024-02-01']);

        $this->assertSame([2026, 2024, 2023, 2022], $rango->aniosParaSelector(['2022', 2024])->all());
    }

    public function test_parametros_reproducen_el_rango(): void
    {
        $rango = $this->rango(['mes' => '2', 'año' => '2026']);

        $this->assertSame(['fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-02-28'], $rango->parametros());
        $this->assertSame($this->fechas($rango), $this->fechas($this->rango($rango->parametros())));
    }

    public function test_los_atajos_se_calculan_desde_hoy_incluso_a_fin_de_mes(): void
    {
        $this->travelTo('2026-03-31 12:00:00');

        $this->assertSame([
            'Este mes' => ['fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-03-31'],
            'Mes anterior' => ['fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-02-28'],
            'Últimos 3 meses' => ['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-03-31'],
            'Este año' => ['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31'],
        ], RangoFechas::atajos());
    }

    public function test_entrada_devuelve_solo_texto(): void
    {
        $request = Request::create('/dashboard', 'GET', ['fecha_inicio' => '2026-09-01', 'fecha_fin' => ['x']]);

        $this->assertSame(['fecha_inicio' => '2026-09-01', 'fecha_fin' => null], RangoFechas::entrada($request));
    }

    public function test_el_constructor_ordena_los_extremos_si_vienen_al_reves(): void
    {
        $rango = new RangoFechas(CarbonImmutable::create(2026, 10, 31), CarbonImmutable::create(2026, 10, 1));

        $this->assertSame('2026-10-01 / 2026-10-31', $this->fechas($rango));
    }
}

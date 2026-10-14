<?php

namespace Tests\Unit\Reportes;

use App\Services\Reportes\MesPeriodo;
use App\Services\Reportes\PeriodoInvalido;
use App\Services\Reportes\PeriodoReporte;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Periodo de los reportes de dinero: que meses toca, cuales se cobran, validacion y textos.
 * No necesita Laravel ni BD.
 */
class PeriodoReporteTest extends TestCase
{
    private function claves(array $meses): array
    {
        return array_map(fn (MesPeriodo $m) => $m->clave, $meses);
    }

    public function test_por_meses_se_cobran_todos_los_meses(): void
    {
        $periodo = PeriodoReporte::deMeses(2026, 1, 9);

        $this->assertSame('2026-01-01', $periodo->desde->toDateString());
        $this->assertSame('2026-09-30', $periodo->hasta->toDateString());
        $this->assertNull($periodo->corte);
        $this->assertSame(9, $periodo->cantidadMeses());
        $this->assertCount(9, $periodo->mesesCobrables());
        $this->assertNull($periodo->mesEnCurso());
        $this->assertTrue($periodo->esPorMesesCompletos());
    }

    public function test_al_corte_el_mes_del_corte_esta_en_curso_y_no_se_cobra(): void
    {
        $periodo = PeriodoReporte::alCorte('2026-10-12');

        $this->assertSame('2026-01-01', $periodo->desde->toDateString());
        $this->assertSame('2026-10-12', $periodo->hasta->toDateString());
        $this->assertSame('2026-10-12', $periodo->corte?->toDateString());
        $this->assertSame(10, $periodo->cantidadMeses());
        $this->assertSame(['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09'], $this->claves($periodo->mesesCobrables()));
        $this->assertSame('2026-10', $periodo->mesEnCurso()?->clave);
        $this->assertSame('Octubre', $periodo->mesEnCurso()?->nombre);
    }

    public function test_si_el_corte_es_el_ultimo_dia_del_mes_ese_mes_se_cobra(): void
    {
        $periodo = PeriodoReporte::alCorte('2026-09-30');

        $this->assertCount(9, $periodo->mesesCobrables());
        $this->assertNull($periodo->mesEnCurso());
    }

    public function test_un_corte_en_enero_no_cobra_nada_todavia(): void
    {
        $periodo = PeriodoReporte::alCorte('2026-01-20');

        $this->assertSame([], $periodo->mesesCobrables());
        $this->assertSame('', $periodo->textoMesesCobrables());
        $this->assertSame('ninguno', $periodo->abreviaturaMesesCobrables());
    }

    public function test_con_fechas_exactas_solo_se_cobran_los_meses_completos(): void
    {
        $periodo = PeriodoReporte::deFechas('2026-03-15', '2026-06-30');

        $this->assertSame(['2026-03', '2026-04', '2026-05', '2026-06'], $this->claves($periodo->meses()));
        $this->assertSame(['2026-04', '2026-05', '2026-06'], $this->claves($periodo->mesesCobrables()));
        $this->assertFalse($periodo->esPorMesesCompletos());
    }

    public function test_con_corte_recorta_un_periodo_por_meses(): void
    {
        $periodo = PeriodoReporte::deMeses(2026, 1, 12)->conCorte('2026-10-13');

        $this->assertSame('2026-10-13', $periodo->hasta->toDateString());
        $this->assertSame(10, $periodo->cantidadMeses());
        $this->assertCount(9, $periodo->mesesCobrables());

        // Un corte despues del fin deja el fin.
        $this->assertSame('2026-03-31', PeriodoReporte::deMeses(2026, 1, 3)->conCorte('2026-10-13')->hasta->toDateString());
    }

    public function test_puede_abarcar_dos_anios(): void
    {
        $periodo = PeriodoReporte::deMeses(2025, 11, 2, 2026);

        $this->assertSame(['2025-11', '2025-12', '2026-01', '2026-02'], $this->claves($periodo->meses()));
        $this->assertTrue($periodo->abarcaVariosAnios());
        $this->assertSame('Noviembre 2025 - Febrero 2026', $periodo->etiqueta());
        $this->assertSame('noviembre de 2025 a febrero de 2026', $periodo->textoMesesCobrables());
        $this->assertSame('nov 25-feb 26', $periodo->abreviaturaMesesCobrables());
        $this->assertSame('Enero 2026', $periodo->meses()[2]->etiqueta(true));
    }

    // ------------------------------------------------------------------ validacion

    public function test_el_inicio_no_puede_ser_posterior_al_fin(): void
    {
        $this->expectException(PeriodoInvalido::class);
        $this->expectExceptionMessage('es posterior a la final');

        PeriodoReporte::deFechas('2026-05-01', '2026-04-30');
    }

    public function test_un_mismo_dia_es_un_periodo_valido(): void
    {
        $periodo = PeriodoReporte::deFechas('2026-05-03', '2026-05-03');

        $this->assertSame(1, $periodo->cantidadMeses());
        $this->assertSame([], $periodo->mesesCobrables());
    }

    public function test_como_mucho_24_meses(): void
    {
        $this->assertSame(24, PeriodoReporte::deMeses(2025, 1, 12, 2026)->cantidadMeses());
        $this->assertSame(24, PeriodoReporte::deFechas('2025-01-15', '2026-12-01')->cantidadMeses());

        $this->expectException(PeriodoInvalido::class);
        $this->expectExceptionMessage('25 meses');

        PeriodoReporte::deMeses(2025, 1, 1, 2027);
    }

    /** @return array<string, array{0: callable}> */
    public static function periodosInvalidos(): array
    {
        return [
            'mes 13' => [fn () => PeriodoReporte::deMeses(2026, 1, 13)],
            'mes 0' => [fn () => PeriodoReporte::deMeses(2026, 0, 3)],
            'meses al reves' => [fn () => PeriodoReporte::deMeses(2026, 9, 1)],
            'fecha imposible' => [fn () => PeriodoReporte::deFechas('2026-02-30', '2026-03-31')],
            'fecha mal escrita' => [fn () => PeriodoReporte::alCorte('13/10/2026')],
            'anio absurdo' => [fn () => PeriodoReporte::deMeses(1999, 1, 2)],
            'corte antes del inicio' => [fn () => PeriodoReporte::alCorte('2026-01-10', '2026-02-01')],
        ];
    }

    #[DataProvider('periodosInvalidos')]
    public function test_rechaza_periodos_invalidos(callable $crear): void
    {
        $this->expectException(PeriodoInvalido::class);

        $crear();
    }

    // ------------------------------------------------------------------ textos

    public function test_etiquetas_y_nombres_de_archivo(): void
    {
        $this->assertSame('Enero - Septiembre 2026', PeriodoReporte::deMeses(2026, 1, 9)->etiqueta());
        $this->assertSame('2026-01_2026-09', PeriodoReporte::deMeses(2026, 1, 9)->sufijoArchivo());
        $this->assertSame('Septiembre 2026', PeriodoReporte::deMeses(2026, 9, 9)->etiqueta());
        $this->assertSame('2026-09', PeriodoReporte::deMeses(2026, 9, 9)->sufijoArchivo());
        $this->assertSame('Corte al 12/10/2026', PeriodoReporte::alCorte('2026-10-12')->etiqueta());
        $this->assertSame('corte_2026-10-12', PeriodoReporte::alCorte('2026-10-12')->sufijoArchivo());
        $this->assertSame('Del 15/03/2026 al 10/05/2026', PeriodoReporte::deFechas('2026-03-15', '2026-05-10')->etiqueta());
        $this->assertSame('2026-03-15_2026-05-10', PeriodoReporte::deFechas('2026-03-15', '2026-05-10')->sufijoArchivo());
    }

    public function test_texto_de_los_meses_que_se_cobran(): void
    {
        $periodo = PeriodoReporte::alCorte('2026-10-12');

        $this->assertSame('enero a septiembre', $periodo->textoMesesCobrables());
        $this->assertSame('ene-sep', $periodo->abreviaturaMesesCobrables());
        $this->assertSame('enero', PeriodoReporte::alCorte('2026-02-03')->textoMesesCobrables());
    }

    public function test_domingos_de_cada_mes(): void
    {
        // 2026: enero 4, febrero 4, marzo 5, mayo 5, agosto 5; 43 de enero a octubre.
        $domingos = array_map(fn (MesPeriodo $m) => $m->domingos, PeriodoReporte::deMeses(2026, 1, 10)->meses());
        $this->assertSame([4, 4, 5, 4, 5, 4, 4, 5, 4, 4], $domingos);
        $this->assertSame(43, array_sum($domingos));
        $this->assertSame(5, PeriodoReporte::domingosDelMes(CarbonImmutable::create(2026, 11, 1)));
    }

    public function test_a_array_para_la_auditoria(): void
    {
        $this->assertSame(
            ['desde' => '2026-01-01', 'hasta' => '2026-10-12', 'corte' => '2026-10-12', 'etiqueta' => 'Corte al 12/10/2026'],
            PeriodoReporte::alCorte('2026-10-12')->aArray(),
        );
    }
}

<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;

/**
 * Resultado del reporte de cumplimiento de promesas: las filas ya ordenadas (pendientes, al dia,
 * sin promesa) y el resumen. Lo mismo alimenta la pantalla, el PDF y el Excel.
 */
final class ReporteCumplimiento
{
    /**
     * @param  list<string>  $categoriasCompromiso
     * @param  list<FilaCumplimiento>  $filas
     */
    public function __construct(
        public readonly PeriodoReporte $periodo,
        public readonly FiltroPersonas $filtro,
        public readonly array $categoriasCompromiso,
        public readonly array $filas,
        public readonly int $sinMovimiento,
        public readonly CarbonImmutable $generadoEn,
    ) {}

    /** @return list<FilaCumplimiento> */
    public function pendientes(): array
    {
        return array_values(array_filter($this->filas, fn (FilaCumplimiento $f) => $f->estaPendiente()));
    }

    /** @return list<FilaCumplimiento> */
    public function alDia(): array
    {
        return array_values(array_filter($this->filas, fn (FilaCumplimiento $f) => $f->estaAlDia()));
    }

    /** @return list<FilaCumplimiento> pendientes y al dia, en ese orden */
    public function conPromesa(): array
    {
        return array_values(array_filter($this->filas, fn (FilaCumplimiento $f) => $f->tienePromesa()));
    }

    /** @return list<FilaCumplimiento> aportan sin promesa registrada */
    public function sinPromesa(): array
    {
        return array_values(array_filter($this->filas, fn (FilaCumplimiento $f) => ! $f->tienePromesa()));
    }

    /**
     * Totales del encabezado: cuantos con promesa, al dia y pendientes; lo que correspondia y lo
     * dado por quienes tienen promesa; lo que les falta a los pendientes.
     *
     * @return array{con_promesa: int, al_dia: int, pendientes: int, sin_promesa: int, le_correspondia: float, ha_dado: float, diferencia: float, faltante: float, dado_sin_promesa: float}
     */
    public function resumen(): array
    {
        $conPromesa = $this->conPromesa();
        $pendientes = $this->pendientes();

        $correspondia = array_sum(array_map(fn (FilaCumplimiento $f) => $f->leCorrespondeCentimos, $conPromesa));
        $dado = array_sum(array_map(fn (FilaCumplimiento $f) => $f->haDadoCentimos, $conPromesa));
        $faltante = array_sum(array_map(fn (FilaCumplimiento $f) => -$f->diferenciaCentimos(), $pendientes));

        return [
            'con_promesa' => count($conPromesa),
            'al_dia' => count($this->alDia()),
            'pendientes' => count($pendientes),
            'sin_promesa' => count($this->sinPromesa()),
            'le_correspondia' => Dinero::colones($correspondia),
            'ha_dado' => Dinero::colones($dado),
            'diferencia' => Dinero::colones($dado - $correspondia),
            'faltante' => Dinero::colones($faltante),
            'dado_sin_promesa' => Dinero::colones(array_sum(array_map(fn (FilaCumplimiento $f) => $f->haDadoCentimos, $this->sinPromesa()))),
        ];
    }

    /** Totales de un grupo de filas: [le correspondia, ha dado, diferencia] en colones. */
    public static function totales(array $filas): array
    {
        $correspondia = array_sum(array_map(fn (FilaCumplimiento $f) => $f->leCorrespondeCentimos, $filas));
        $dado = array_sum(array_map(fn (FilaCumplimiento $f) => $f->haDadoCentimos, $filas));

        return [Dinero::colones($correspondia), Dinero::colones($dado), Dinero::colones($dado - $correspondia)];
    }

    /**
     * Explicacion de las reglas para el encabezado, con los meses del corte:
     * "Le correspondía = lo prometido de enero a septiembre (octubre está en curso y no se cobra)..."
     */
    public function explicacion(): string
    {
        $cobrados = $this->periodo->textoMesesCobrables();
        $enCurso = $this->periodo->mesEnCurso();

        $correspondia = $cobrados === ''
            ? 'Le correspondía = nada todavía: '.mb_strtolower((string) $enCurso?->nombre).' está en curso y no se cobra.'
            : 'Le correspondía = lo prometido de '.$cobrados
                .($enCurso ? ' ('.mb_strtolower($enCurso->nombre).' está en curso y no se cobra).' : '.');

        return $correspondia
            .' Ha dado = todo lo entregado hasta la fecha de corte'
            .($enCurso ? ', incluido '.mb_strtolower($enCurso->nombre) : '')
            .', en las categorías que admiten promesa.'
            .' El estado se decide por el acumulado: quien no dio un mes pero repuso después está al día.';
    }

    /** Encabezado corto de la columna: "Le correspondía (ene-sep)". */
    public function tituloCorrespondia(): string
    {
        return 'Le correspondía ('.$this->periodo->abreviaturaMesesCobrables().')';
    }

    /** "Corte al 12/10/2026" para titulos. */
    public function corteTexto(): string
    {
        return $this->periodo->corte?->format('d/m/Y') ?? $this->periodo->hasta->format('d/m/Y');
    }
}

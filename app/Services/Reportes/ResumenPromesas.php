<?php

namespace App\Services\Reportes;

/**
 * Resultado de ResumenPromesasService (QE-008). Montos en centimos por dentro; los metodos publicos
 * devuelven colones. "Prometido" es siempre de meses completos; el mes en curso va aparte.
 */
final class ResumenPromesas
{
    /**
     * @param  list<array{slug: string, nombre: string, prometido: int, en_curso: int, dado: int, dado_en_curso: int}>  $categorias
     * @param  list<array{mes: MesPeriodo, prometido: int, dado: int}>  $meses
     * @param  list<array{id: int, nombre: string, prometido: int, dado: int}>  $personas
     */
    public function __construct(
        public readonly PeriodoReporte $periodo,
        private readonly array $categorias,
        private readonly array $meses,
        private readonly array $personas,
    ) {}

    public static function vacio(PeriodoReporte $periodo): self
    {
        return new self($periodo, [], [], []);
    }

    /** Lo prometido de los meses que se cobran (= "le correspondía" del Cumplimiento). */
    public function prometido(): float
    {
        return Dinero::colones(array_sum(array_column($this->categorias, 'prometido')));
    }

    /** Lo dado en las categorias prometidas hasta el corte (= "ha dado" del Cumplimiento). */
    public function dado(): float
    {
        return Dinero::colones(array_sum(array_column($this->categorias, 'dado')));
    }

    public function saldo(): float
    {
        return Dinero::colones(array_sum(array_column($this->categorias, 'dado')) - array_sum(array_column($this->categorias, 'prometido')));
    }

    /** Porcentaje de cumplimiento (dado / prometido de meses completos), con un decimal. */
    public function porcentaje(): float
    {
        $prometido = array_sum(array_column($this->categorias, 'prometido'));

        return $prometido > 0 ? round(array_sum(array_column($this->categorias, 'dado')) / $prometido * 100, 1) : 0.0;
    }

    /** Suma de lo que falta en cada categoria (solo meses completos). */
    public function faltante(): float
    {
        return Dinero::colones(array_sum(array_map(fn ($c) => max(0, $c['prometido'] - $c['dado']), $this->categorias)));
    }

    /** Lo dado de mas sobre lo prometido; sin meses completos no hay contra que comparar: cero. */
    public function excedente(): float
    {
        if (! $this->hayMesesCobrables()) {
            return 0.0;
        }

        return Dinero::colones(array_sum(array_map(fn ($c) => max(0, $c['dado'] - $c['prometido']), $this->categorias)));
    }

    /** Lo prometido del mes en curso (todavia no se cobra). */
    public function prometidoEnCurso(): float
    {
        return Dinero::colones(array_sum(array_column($this->categorias, 'en_curso')));
    }

    public function dadoEnCurso(): float
    {
        return Dinero::colones(array_sum(array_column($this->categorias, 'dado_en_curso')));
    }

    public function mesEnCurso(): ?MesPeriodo
    {
        return $this->periodo->mesEnCurso();
    }

    /** Hay algun mes completo que se cobre en el periodo. */
    public function hayMesesCobrables(): bool
    {
        return $this->periodo->mesesCobrables() !== [];
    }

    /**
     * Por categoria, en colones: prometido (meses completos), en curso, dado, faltante y excedente.
     *
     * @return list<array{categoria: string, slug: string, total_prometido: float, en_curso: float, total_dado: float, faltante: float, profit: float}>
     */
    public function categorias(): array
    {
        return array_map(fn ($c) => [
            'categoria' => $c['nombre'],
            'slug' => $c['slug'],
            'total_prometido' => Dinero::colones($c['prometido']),
            'en_curso' => Dinero::colones($c['en_curso']),
            'total_dado' => Dinero::colones($c['dado']),
            'faltante' => Dinero::colones(max(0, $c['prometido'] - $c['dado'])),
            'profit' => $this->hayMesesCobrables() ? Dinero::colones(max(0, $c['dado'] - $c['prometido'])) : 0.0,
        ], $this->categorias);
    }

    /**
     * Mes a mes: lo prometido del mes (si es el mes en curso, marcado), y lo dado.
     *
     * @return list<array{mes: MesPeriodo, prometido: float, dado: float, en_curso: bool}>
     */
    public function meses(): array
    {
        return array_map(fn ($m) => [
            'mes' => $m['mes'],
            'prometido' => Dinero::colones($m['prometido']),
            'dado' => Dinero::colones($m['dado']),
            'en_curso' => ! $m['mes']->cobrable,
        ], $this->meses);
    }

    /**
     * Las N personas que mas dieron en sus compromisos.
     *
     * @return list<array{nombre: string, prometido: float, dado: float, saldo: float, pct: float}>
     */
    public function top(int $n = 10): array
    {
        $personas = $this->personas;
        usort($personas, fn ($a, $b) => [$b['dado'], $a['nombre']] <=> [$a['dado'], $b['nombre']]);

        return array_map(fn ($p) => [
            'nombre' => $p['nombre'],
            'prometido' => Dinero::colones($p['prometido']),
            'dado' => Dinero::colones($p['dado']),
            'saldo' => Dinero::colones($p['dado'] - $p['prometido']),
            'pct' => $p['prometido'] > 0 ? round($p['dado'] / $p['prometido'] * 100, 1) : 0.0,
        ], array_slice($personas, 0, $n));
    }

    public function cantidadPersonas(): int
    {
        return count($this->personas);
    }

    /** Rotulo del mes en curso: "Noviembre (en curso, todavía no se cobra)". */
    public function rotuloEnCurso(): string
    {
        $mes = $this->mesEnCurso();

        return $mes === null ? '' : $mes->nombre.' (en curso, todavía no se cobra)';
    }
}

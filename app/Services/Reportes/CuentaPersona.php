<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;

/**
 * Lo que el motor calculo para una persona en un periodo. Todo en colones; por dentro, centimos.
 *
 * - dado: por mes (clave "2026-03") y categoria, de TODAS las categorias en que dio (tambien
 *   diezmo y categorias que la iglesia ya no usa). Los sobres en dolares ya vienen convertidos.
 * - esperado: por mes y categoria, solo de las categorias de compromiso con promesa.
 * - "Ha dado" para el estado = lo dado en las categorias de compromiso (las que no estan excluidas
 *   de promesas), tenga o no promesa en cada una: es la regla del Reporte General.
 */
final class CuentaPersona
{
    /**
     * @param  array<string, array<string, int>>  $dado  clave de mes => categoria => centimos
     * @param  array<string, array<string, int>>  $esperado  clave de mes => categoria => centimos
     * @param  array<string, PromesaVigente>  $promesas  categoria => promesa, en el orden de la iglesia
     * @param  list<string>  $categoriasCompromiso
     * @param  list<array<string, mixed>>  $detalle  un renglon por detalle de sobre (solo si se pidio)
     */
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly bool $activo,
        public readonly PeriodoReporte $periodo,
        private readonly array $dado,
        private readonly array $esperado,
        private readonly array $promesas,
        private readonly array $categoriasCompromiso,
        public readonly ?CarbonImmutable $ultimoAporte,
        public readonly bool $hayUsd,
        public readonly array $detalle = [],
    ) {}

    // ------------------------------------------------------------------
    // Lo dado
    // ------------------------------------------------------------------

    /**
     * Lo dado en un mes (o en todo el periodo si $clave es null), en las categorias indicadas (o en
     * todas).
     *
     * @param  list<string>|null  $categorias
     */
    public function dadoCentimos(?string $clave = null, ?array $categorias = null): int
    {
        $meses = $clave === null ? $this->dado : [$clave => $this->dado[$clave] ?? []];
        $total = 0;
        foreach ($meses as $porCategoria) {
            foreach ($porCategoria as $categoria => $centimos) {
                if ($categorias === null || in_array($categoria, $categorias, true)) {
                    $total += $centimos;
                }
            }
        }

        return $total;
    }

    /** @param  list<string>|null  $categorias */
    public function dado(?string $clave = null, ?array $categorias = null): float
    {
        return Dinero::colones($this->dadoCentimos($clave, $categorias));
    }

    /** Lo dado en las categorias de compromiso (lo que cuenta para el estado). */
    public function dadoEnCompromisosCentimos(?string $clave = null): int
    {
        return $this->dadoCentimos($clave, $this->categoriasCompromiso);
    }

    public function dadoEnCompromisos(?string $clave = null): float
    {
        return Dinero::colones($this->dadoEnCompromisosCentimos($clave));
    }

    /**
     * Total del periodo por categoria (todas en las que dio), en colones.
     *
     * @return array<string, float>
     */
    public function dadoPorCategoria(): array
    {
        $centimos = [];
        foreach ($this->dado as $porCategoria) {
            foreach ($porCategoria as $categoria => $monto) {
                $centimos[$categoria] = ($centimos[$categoria] ?? 0) + $monto;
            }
        }

        return array_map(fn (int $c) => Dinero::colones($c), $centimos);
    }

    // ------------------------------------------------------------------
    // Lo esperado
    // ------------------------------------------------------------------

    /**
     * Lo esperado en un mes (o en todo el periodo), de una categoria (o de todas las prometidas).
     * Con $soloCobrables cuenta solo los meses que se cobran (enteros dentro del periodo).
     */
    public function esperadoCentimos(?string $clave = null, ?string $categoria = null, bool $soloCobrables = false): int
    {
        $total = 0;
        foreach ($this->periodo->meses() as $mes) {
            if (($clave !== null && $mes->clave !== $clave) || ($soloCobrables && ! $mes->cobrable)) {
                continue;
            }
            foreach ($this->esperado[$mes->clave] ?? [] as $cat => $centimos) {
                if ($categoria === null || $cat === $categoria) {
                    $total += $centimos;
                }
            }
        }

        return $total;
    }

    public function esperado(?string $clave = null, ?string $categoria = null, bool $soloCobrables = false): float
    {
        return Dinero::colones($this->esperadoCentimos($clave, $categoria, $soloCobrables));
    }

    // ------------------------------------------------------------------
    // Promesas y estado
    // ------------------------------------------------------------------

    /** @return array<string, PromesaVigente> categoria => promesa vigente (solo categorias de compromiso) */
    public function promesas(): array
    {
        return $this->promesas;
    }

    public function tienePromesa(): bool
    {
        foreach ($this->promesas as $promesa) {
            if ($promesa->monto > 0) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function categoriasCompromiso(): array
    {
        return $this->categoriasCompromiso;
    }

    /**
     * Estado acumulado: lo esperado de los meses que se cobran contra todo lo dado en el periodo en
     * las categorias de compromiso.
     */
    public function estado(): EstadoCuenta
    {
        return new EstadoCuenta(
            $this->esperadoCentimos(soloCobrables: true),
            $this->dadoEnCompromisosCentimos(),
            $this->tienePromesa(),
        );
    }

    /**
     * Meses que se cobran en los que le correspondia algo y no dio nada en las categorias de
     * compromiso (para la observacion "sin aportes en ...").
     *
     * @return list<MesPeriodo>
     */
    public function mesesSinAporte(): array
    {
        $meses = [];
        foreach ($this->periodo->mesesCobrables() as $mes) {
            if ($this->esperadoCentimos($mes->clave) > 0 && $this->dadoEnCompromisosCentimos($mes->clave) === 0) {
                $meses[] = $mes;
            }
        }

        return $meses;
    }

    /** Si tiene algo que mostrar en un reporte de compromisos: promesa o algo dado en categorias de compromiso. */
    public function tieneMovimientoEnCompromisos(): bool
    {
        return $this->tienePromesa() || $this->dadoEnCompromisosCentimos() > 0;
    }
}

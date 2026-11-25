<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;

/**
 * Lo que el motor calculo para una persona en un periodo. Todo en colones; por dentro, centimos.
 *
 * - dado: por mes (clave "2026-03") y categoria, de TODAS las categorias en que dio (tambien
 *   diezmo y categorias que la iglesia ya no usa). Los sobres en dolares ya vienen convertidos.
 * - esperado: por mes y categoria, solo de las categorias de compromiso con promesa. Para el
 *   estado solo cuentan los meses que se cobran (completos antes del corte): esperadoCentimos().
 *   esperadoDelMesCentimos() da lo de cualquier mes del periodo, solo como dato informativo.
 * - "Ha dado" para el estado = lo dado en las categorias en que la persona TIENE promesa (regla 2.1
 *   de 04-DECISIONES.md); el diezmo y las categorias sin promesa se muestran pero no cuentan.
 * - Promesas futuras: si el periodo toca meses anteriores a un corte de promesas, $promesasPorMes
 *   trae las que regian cada mes; entonces lo esperado y lo que cuenta como "ha dado" de cada mes
 *   salen de las promesas de ESE mes, promesas() es el juego del ultimo mes y "tiene promesa" es
 *   haber tenido alguna en algun mes del periodo. Sin cortes ($promesasPorMes null) todo es como siempre.
 */
final class CuentaPersona
{
    /**
     * @param  array<string, array<string, int>>  $dado  clave de mes => categoria => centimos
     * @param  array<string, array<string, int>>  $esperado  clave de mes => categoria => centimos
     * @param  array<string, PromesaVigente>  $promesas  categoria => promesa, en el orden de la iglesia
     * @param  list<string>  $categoriasCompromiso
     * @param  list<array<string, mixed>>  $detalle  un renglon por detalle de sobre (solo si se pidio)
     * @param  array<string, string>  $ultimoPorCategoria  categoria de compromiso => fecha (Y-m-d) del ultimo aporte en ella
     * @param  array<string, array<string, PromesaVigente>>|null  $promesasPorMes  clave de mes => categoria => promesa
     *                                                                            que regia ese mes; null = las mismas todo el periodo
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
        private readonly array $ultimoPorCategoria = [],
        private readonly ?array $promesasPorMes = null,
    ) {}

    /**
     * Ultimo aporte con la misma base que "ha dado" (QE-011): la fecha del ultimo culto en que dio en
     * una categoria que prometio; sin promesa, en cualquier categoria de compromiso. Asi "Ha dado ₡0"
     * nunca convive con un "ultimo aporte" de una categoria que no cuenta.
     */
    public function ultimoAporteACompromisos(): ?CarbonImmutable
    {
        $categorias = $this->tienePromesa() ? $this->categoriasConPromesa() : $this->categoriasCompromiso;
        $ultima = null;
        foreach ($categorias as $categoria) {
            $fecha = $this->ultimoPorCategoria[$categoria] ?? null;
            if ($fecha !== null && ($ultima === null || $fecha > $ultima)) {
                $ultima = $fecha;
            }
        }

        return $ultima === null ? null : CarbonImmutable::parse($ultima);
    }

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

    /** Lo dado en las categorias en que tiene promesa: lo que cuenta para el estado. */
    public function dadoEnPromesasCentimos(?string $clave = null): int
    {
        if ($this->promesasPorMes !== null && $clave === null) {
            // Cada mes con las categorias que prometio ESE mes.
            $total = 0;
            foreach ($this->periodo->meses() as $mes) {
                $total += $this->dadoEnPromesasCentimos($mes->clave);
            }

            return $total;
        }

        $categorias = $this->categoriasConPromesa($clave);

        return $categorias === [] ? 0 : $this->dadoCentimos($clave, $categorias);
    }

    public function dadoEnPromesas(?string $clave = null): float
    {
        return Dinero::colones($this->dadoEnPromesasCentimos($clave));
    }

    /** Lo dado en cualquier categoria de compromiso, tenga o no promesa en ella (informativo). */
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
     * Lo esperado en un mes (o en todo el periodo), de una categoria (o de todas las prometidas),
     * contando SOLO los meses que se cobran (completos antes del corte): lo que cuenta para el estado.
     */
    public function esperadoCentimos(?string $clave = null, ?string $categoria = null): int
    {
        return $this->sumarEsperado($clave, $categoria, true);
    }

    public function esperado(?string $clave = null, ?string $categoria = null): float
    {
        return Dinero::colones($this->esperadoCentimos($clave, $categoria));
    }

    /**
     * Lo que corresponde a un mes segun la promesa aunque ese mes todavia no se cobre (el mes en
     * curso): solo para mostrar el detalle mes a mes y para la tabla compromisos.
     */
    public function esperadoDelMesCentimos(string $clave, ?string $categoria = null): int
    {
        return $this->sumarEsperado($clave, $categoria, false);
    }

    public function esperadoDelMes(string $clave, ?string $categoria = null): float
    {
        return Dinero::colones($this->esperadoDelMesCentimos($clave, $categoria));
    }

    private function sumarEsperado(?string $clave, ?string $categoria, bool $soloCobrables): int
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

    // ------------------------------------------------------------------
    // Promesas y estado
    // ------------------------------------------------------------------

    /**
     * Categoria => promesa (solo categorias de compromiso). Si las promesas cambiaron dentro del
     * periodo (corte de promesas), las del ultimo mes del periodo.
     *
     * @return array<string, PromesaVigente>
     */
    public function promesas(): array
    {
        return $this->promesas;
    }

    /**
     * Las promesas que regian en un mes del periodo.
     *
     * @return array<string, PromesaVigente>
     */
    public function promesasDelMes(string $clave): array
    {
        return $this->promesasPorMes === null ? $this->promesas : ($this->promesasPorMes[$clave] ?? []);
    }

    /** Si tuvo promesa con monto (en algun mes del periodo, si las promesas cambiaron dentro de el). */
    public function tienePromesa(): bool
    {
        return $this->categoriasConPromesa() !== [];
    }

    /**
     * Categorias en que tiene promesa (monto > 0), en el orden de la iglesia: las de un mes, o (sin
     * $clave) las de cualquier mes del periodo.
     *
     * @return list<string>
     */
    public function categoriasConPromesa(?string $clave = null): array
    {
        if ($this->promesasPorMes === null) {
            return self::conMonto($this->promesas);
        }
        if ($clave !== null) {
            return self::conMonto($this->promesasPorMes[$clave] ?? []);
        }

        $union = [];
        foreach ($this->promesasPorMes as $promesas) {
            foreach (self::conMonto($promesas) as $categoria) {
                $union[$categoria] = true;
            }
        }

        return array_values(array_filter($this->categoriasCompromiso, fn (string $c) => isset($union[$c])));
    }

    /**
     * @param  array<string, PromesaVigente>  $promesas
     * @return list<string>
     */
    private static function conMonto(array $promesas): array
    {
        $categorias = [];
        foreach ($promesas as $categoria => $promesa) {
            if ($promesa->tieneMonto()) {
                $categorias[] = $categoria;
            }
        }

        return $categorias;
    }

    /** @return list<string> */
    public function categoriasCompromiso(): array
    {
        return $this->categoriasCompromiso;
    }

    /**
     * Estado acumulado: lo esperado de los meses que se cobran contra todo lo dado en el periodo
     * (incluido el mes en curso) en las categorias con promesa. Sin promesa, "ha dado" es lo dado en
     * categorias de compromiso, solo para mostrarlo.
     */
    public function estado(): EstadoCuenta
    {
        $tienePromesa = $this->tienePromesa();

        return new EstadoCuenta(
            $this->esperadoCentimos(),
            $tienePromesa ? $this->dadoEnPromesasCentimos() : $this->dadoEnCompromisosCentimos(),
            $tienePromesa,
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
            if ($this->esperadoCentimos($mes->clave) > 0 && $this->dadoEnPromesasCentimos($mes->clave) === 0) {
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

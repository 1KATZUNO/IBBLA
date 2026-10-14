<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Periodo de un reporte: del dia `desde` al dia `hasta`, los dos incluidos, y la fecha de corte
 * cuando el reporte es "a la fecha".
 *
 * Es la unica regla de periodo de todos los reportes de dinero:
 *  - Lo DADO cuenta los sobres de los cultos con fecha entre `desde` y `hasta`.
 *  - Lo ESPERADO de las promesas se cobra por meses completos: un mes se cobra solo si cae entero
 *    dentro del periodo. En un periodo por meses (enero a septiembre) se cobran todos. En uno con
 *    corte (al 13/10) octubre esta en curso y no se cobra, salvo que el corte sea su ultimo dia;
 *    lo dado de octubre hasta el corte si cuenta.
 *  - Lo esperado de un mes: semanal x domingos del mes, quincenal x 2, mensual x 1.
 *
 * Validacion: inicio <= fin y como mucho 24 meses (de enero de un anio a diciembre del siguiente).
 * Es inmutable; conCorte() devuelve otro periodo.
 */
final class PeriodoReporte
{
    public const MAX_MESES = 24;

    public const NOMBRES_MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    private const ABREVIATURAS = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** @var list<MesPeriodo>|null */
    private ?array $meses = null;

    private function __construct(
        public readonly CarbonImmutable $desde,
        public readonly CarbonImmutable $hasta,
        public readonly ?CarbonImmutable $corte,
    ) {
        if ($desde->gt($hasta)) {
            throw new PeriodoInvalido('La fecha de inicio ('.$desde->format('d/m/Y').') es posterior a la final ('.$hasta->format('d/m/Y').').');
        }

        $meses = self::mesesEntre($desde, $hasta);
        if ($meses > self::MAX_MESES) {
            throw new PeriodoInvalido('El periodo abarca '.$meses.' meses; el máximo es '.self::MAX_MESES.'.');
        }
    }

    // ------------------------------------------------------------------
    // Constructores
    // ------------------------------------------------------------------

    /** De un mes a otro, completos: deMeses(2026, 1, 9) = enero a septiembre de 2026. */
    public static function deMeses(int $anio, int $mesDesde, int $mesHasta, ?int $anioHasta = null): self
    {
        foreach ([$mesDesde, $mesHasta] as $mes) {
            if ($mes < 1 || $mes > 12) {
                throw new PeriodoInvalido('El mes debe estar entre 1 y 12.');
            }
        }
        self::validarAnio($anio);
        self::validarAnio($anioHasta ?? $anio);

        return new self(
            CarbonImmutable::create($anio, $mesDesde, 1)->startOfDay(),
            CarbonImmutable::create($anioHasta ?? $anio, $mesHasta, 1)->endOfMonth()->startOfDay(),
            null,
        );
    }

    /** Fechas exactas: lo dado cuenta del dia `desde` al dia `hasta`; se cobran los meses completos de ese rango. */
    public static function deFechas(DateTimeInterface|string $desde, DateTimeInterface|string $hasta): self
    {
        return new self(self::fecha($desde), self::fecha($hasta), null);
    }

    /**
     * A la fecha de corte: desde el 1 de enero del anio del corte (o `desde`) hasta el corte.
     * Lo dado cuenta hasta el dia del corte; lo esperado, los meses completos anteriores.
     */
    public static function alCorte(DateTimeInterface|string $corte, DateTimeInterface|string|null $desde = null): self
    {
        $corte = self::fecha($corte);

        return new self($desde === null ? $corte->startOfYear() : self::fecha($desde), $corte, $corte);
    }

    /** El mismo periodo recortado a una fecha de corte (si el corte es posterior al fin, queda el fin). */
    public function conCorte(DateTimeInterface|string $corte): self
    {
        $corte = self::fecha($corte);
        $hasta = $corte->lt($this->hasta) ? $corte : $this->hasta;

        return new self($this->desde, $hasta, $hasta);
    }

    // ------------------------------------------------------------------
    // Meses
    // ------------------------------------------------------------------

    /** @return list<MesPeriodo> los meses calendario que toca el periodo, en orden */
    public function meses(): array
    {
        if ($this->meses !== null) {
            return $this->meses;
        }

        $this->meses = [];
        $mes = $this->desde->startOfMonth();
        $ultimo = $this->hasta->startOfMonth();
        while ($mes->lte($ultimo)) {
            $this->meses[] = new MesPeriodo(
                $mes->year,
                $mes->month,
                self::domingosDelMes($mes),
                $mes->gte($this->desde) && $mes->endOfMonth()->startOfDay()->lte($this->hasta),
            );
            $mes = $mes->addMonth();
        }

        return $this->meses;
    }

    /** @return list<MesPeriodo> los meses que se cobran (enteros dentro del periodo) */
    public function mesesCobrables(): array
    {
        return array_values(array_filter($this->meses(), fn (MesPeriodo $mes) => $mes->cobrable));
    }

    /** El mes del corte cuando todavia esta en curso (no se cobra); null si el periodo termina en fin de mes. */
    public function mesEnCurso(): ?MesPeriodo
    {
        $meses = $this->meses();
        $ultimo = end($meses);

        return $ultimo !== false && ! $ultimo->cobrable && $ultimo->clave === $this->hasta->format('Y-m') ? $ultimo : null;
    }

    public function cantidadMeses(): int
    {
        return count($this->meses());
    }

    public function abarcaVariosAnios(): bool
    {
        return $this->desde->year !== $this->hasta->year;
    }

    public function esPorMesesCompletos(): bool
    {
        return $this->corte === null
            && $this->desde->day === 1
            && $this->hasta->isSameDay($this->hasta->endOfMonth());
    }

    // ------------------------------------------------------------------
    // Textos para encabezados, notas y nombres de archivo
    // ------------------------------------------------------------------

    /** "Enero - Septiembre 2026", "Noviembre 2025 - Febrero 2026", "Corte al 13/10/2026" o "Del 15/03/2026 al 20/05/2026". */
    public function etiqueta(): string
    {
        if ($this->corte !== null) {
            return 'Corte al '.$this->corte->format('d/m/Y');
        }

        if (! $this->esPorMesesCompletos()) {
            return 'Del '.$this->desde->format('d/m/Y').' al '.$this->hasta->format('d/m/Y');
        }

        $inicio = self::NOMBRES_MESES[$this->desde->month];
        $fin = self::NOMBRES_MESES[$this->hasta->month];

        if ($this->abarcaVariosAnios()) {
            return $inicio.' '.$this->desde->year.' - '.$fin.' '.$this->hasta->year;
        }

        return $this->desde->month === $this->hasta->month ? $fin.' '.$this->hasta->year : $inicio.' - '.$fin.' '.$this->hasta->year;
    }

    /** Meses que se cobran, en minuscula: "enero a septiembre", "enero", "noviembre de 2025 a febrero de 2026" o "" si ninguno. */
    public function textoMesesCobrables(): string
    {
        $cobrables = $this->mesesCobrables();
        if ($cobrables === []) {
            return '';
        }

        $primero = $cobrables[0];
        $ultimo = $cobrables[count($cobrables) - 1];
        $nombre = fn (MesPeriodo $m) => mb_strtolower($m->nombre).($this->abarcaVariosAnios() ? ' de '.$m->anio : '');

        return $primero === $ultimo ? $nombre($primero) : $nombre($primero).' a '.$nombre($ultimo);
    }

    /** Abreviatura de los meses que se cobran, para encabezados: "ene-sep", "ene", "nov 25-feb 26" o "ninguno". */
    public function abreviaturaMesesCobrables(): string
    {
        $cobrables = $this->mesesCobrables();
        if ($cobrables === []) {
            return 'ninguno';
        }

        $primero = $cobrables[0];
        $ultimo = $cobrables[count($cobrables) - 1];
        $abrev = fn (MesPeriodo $m) => self::ABREVIATURAS[$m->mes].($this->abarcaVariosAnios() ? ' '.substr((string) $m->anio, 2) : '');

        return $primero === $ultimo ? $abrev($primero) : $abrev($primero).'-'.$abrev($ultimo);
    }

    /** Para nombres de archivo: "2026-01_2026-09", "2026-09", "corte_2026-10-13" o "2026-03-15_2026-05-20". */
    public function sufijoArchivo(): string
    {
        if ($this->corte !== null) {
            return 'corte_'.$this->corte->toDateString();
        }

        if ($this->esPorMesesCompletos()) {
            $inicio = $this->desde->format('Y-m');
            $fin = $this->hasta->format('Y-m');

            return $inicio === $fin ? $fin : $inicio.'_'.$fin;
        }

        return $this->desde->toDateString().'_'.$this->hasta->toDateString();
    }

    /** Para la auditoria y para repetir el reporte. */
    public function aArray(): array
    {
        return [
            'desde' => $this->desde->toDateString(),
            'hasta' => $this->hasta->toDateString(),
            'corte' => $this->corte?->toDateString(),
            'etiqueta' => $this->etiqueta(),
        ];
    }

    // ------------------------------------------------------------------

    /** Domingos del mes calendario completo (lo que se espera de una promesa semanal). */
    public static function domingosDelMes(CarbonImmutable $mes): int
    {
        $dia = $mes->startOfMonth();
        // Dia del mes del primer domingo; de ahi en adelante, uno cada 7 dias.
        $primerDomingo = $dia->dayOfWeek === 0 ? 1 : 8 - $dia->dayOfWeek;

        return intdiv($dia->daysInMonth - $primerDomingo, 7) + 1;
    }

    private static function mesesEntre(CarbonImmutable $desde, CarbonImmutable $hasta): int
    {
        return ($hasta->year - $desde->year) * 12 + $hasta->month - $desde->month + 1;
    }

    private static function validarAnio(int $anio): void
    {
        if ($anio < 2000 || $anio > 2100) {
            throw new PeriodoInvalido('El año '.$anio.' no es válido.');
        }
    }

    private static function fecha(DateTimeInterface|string $fecha): CarbonImmutable
    {
        if ($fecha instanceof DateTimeInterface) {
            $resultado = CarbonImmutable::instance($fecha)->startOfDay();
        } else {
            try {
                $resultado = CarbonImmutable::createFromFormat('!Y-m-d', $fecha);
            } catch (Throwable) {
                $resultado = false;
            }
            if (! $resultado || $resultado->format('Y-m-d') !== $fecha) {
                throw new PeriodoInvalido('La fecha "'.$fecha.'" no es válida (formato AAAA-MM-DD).');
            }
        }
        self::validarAnio($resultado->year);

        return $resultado->startOfDay();
    }
}

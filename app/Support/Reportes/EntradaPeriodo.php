<?php

namespace App\Support\Reportes;

use App\Services\Reportes\PeriodoInvalido;
use App\Services\Reportes\PeriodoReporte;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;

/**
 * El periodo de un reporte leido de la URL (HU-R3), para que cualquier reporte sea un enlace
 * compartible y las descargas repitan exactamente lo que se ve en pantalla:
 *
 *   ?periodo=meses&anio=2026&mes_desde=1&mes_hasta=9     de enero a septiembre (anio_hasta opcional)
 *   ?periodo=fechas&desde=2026-03-15&hasta=2026-05-10    fechas exactas
 *   ?periodo=corte&corte=2026-10-13                      del 1 de enero al corte (hoy por defecto)
 *
 * Sin `periodo` se deduce: corte si viene `corte`, fechas si vienen `desde`/`hasta`, meses si no.
 * Los campos que faltan toman el valor por defecto (enero -> mes actual, corte = hoy).
 *
 * Igual que App\Support\RangoFechas de los dashboards: un valor invalido (inicio despues del fin,
 * mas de 24 meses, fecha mal escrita, corte en el futuro) no rompe la pantalla ni redirige; se usa
 * el periodo por defecto y los motivos quedan en $errores para mostrarlos junto al formulario.
 */
final class EntradaPeriodo
{
    public const MESES = 'meses';

    public const FECHAS = 'fechas';

    public const CORTE = 'corte';

    private function __construct(
        public readonly PeriodoReporte $periodo,
        public readonly string $modo,
        public readonly MessageBag $errores,
    ) {}

    /** @param  Request|array<string, mixed>  $entrada */
    public static function desde(Request|array $entrada, string $porDefecto = self::MESES): self
    {
        $datos = $entrada instanceof Request ? $entrada->query() : $entrada;
        $errores = new MessageBag;

        $modo = self::modo($datos, $porDefecto, $errores);

        try {
            $periodo = match ($modo) {
                self::CORTE => self::corte($datos),
                self::FECHAS => self::fechas($datos),
                default => self::meses($datos),
            };
        } catch (PeriodoInvalido $e) {
            $errores->add('periodo', $e->getMessage());
            $periodo = self::porDefecto($modo);
        }

        return new self($periodo, $modo, $errores);
    }

    /** Enero -> mes actual; para corte, hoy; para fechas, del 1 de enero a hoy. */
    public static function porDefecto(string $modo = self::MESES): PeriodoReporte
    {
        $hoy = CarbonImmutable::today();

        return match ($modo) {
            self::CORTE => PeriodoReporte::alCorte($hoy),
            self::FECHAS => PeriodoReporte::deFechas($hoy->startOfYear(), $hoy),
            default => PeriodoReporte::deMeses($hoy->year, 1, $hoy->month),
        };
    }

    /**
     * Parametros de URL que vuelven a armar un periodo (para los enlaces de descarga).
     *
     * @return array<string, int|string>
     */
    public static function parametros(PeriodoReporte $periodo): array
    {
        if ($periodo->corte !== null && $periodo->desde->isSameDay($periodo->corte->startOfYear())) {
            return ['periodo' => self::CORTE, 'corte' => $periodo->corte->toDateString()];
        }

        if ($periodo->esPorMesesCompletos()) {
            $parametros = [
                'periodo' => self::MESES,
                'anio' => $periodo->desde->year,
                'mes_desde' => $periodo->desde->month,
                'mes_hasta' => $periodo->hasta->month,
            ];
            if ($periodo->abarcaVariosAnios()) {
                $parametros['anio_hasta'] = $periodo->hasta->year;
            }

            return $parametros;
        }

        return ['periodo' => self::FECHAS, 'desde' => $periodo->desde->toDateString(), 'hasta' => $periodo->hasta->toDateString()];
    }

    /** @return array<string, int|string> */
    public function aParametros(): array
    {
        return self::parametros($this->periodo);
    }

    public function tieneErrores(): bool
    {
        return $this->errores->isNotEmpty();
    }

    // ------------------------------------------------------------------

    private static function modo(array $datos, string $porDefecto, MessageBag $errores): string
    {
        $pedido = $datos['periodo'] ?? null;
        if (is_string($pedido) && $pedido !== '') {
            if (in_array($pedido, [self::MESES, self::FECHAS, self::CORTE], true)) {
                return $pedido;
            }
            $errores->add('periodo', 'El tipo de periodo "'.$pedido.'" no existe (meses, fechas o corte).');

            return $porDefecto;
        }

        if (self::lleno($datos, 'corte')) {
            return self::CORTE;
        }
        if (self::lleno($datos, 'desde') || self::lleno($datos, 'hasta')) {
            return self::FECHAS;
        }
        if (self::lleno($datos, 'anio') || self::lleno($datos, 'mes_desde') || self::lleno($datos, 'mes_hasta')) {
            return self::MESES;
        }

        return $porDefecto;
    }

    private static function meses(array $datos): PeriodoReporte
    {
        $hoy = CarbonImmutable::today();
        $anio = self::entero($datos, 'anio', $hoy->year, 'El año');
        $anioHasta = self::entero($datos, 'anio_hasta', $anio, 'El año final');
        $mesDesde = self::entero($datos, 'mes_desde', 1, 'El mes inicial');
        // Por defecto hasta el mes actual en el anio en curso, y diciembre en anios anteriores.
        $mesHasta = self::entero($datos, 'mes_hasta', $anioHasta === $hoy->year ? $hoy->month : 12, 'El mes final');

        if ($anioHasta === $anio && $mesDesde > $mesHasta) {
            throw new PeriodoInvalido('El mes inicial ('.PeriodoReporte::NOMBRES_MESES[$mesDesde].') va después del mes final ('.PeriodoReporte::NOMBRES_MESES[$mesHasta].').');
        }

        return PeriodoReporte::deMeses($anio, $mesDesde, $mesHasta, $anioHasta);
    }

    private static function fechas(array $datos): PeriodoReporte
    {
        $hoy = CarbonImmutable::today();
        $desde = self::lleno($datos, 'desde') ? self::cadena($datos, 'desde') : $hoy->startOfYear()->toDateString();
        $hasta = self::lleno($datos, 'hasta') ? self::cadena($datos, 'hasta') : $hoy->toDateString();

        return PeriodoReporte::deFechas($desde, $hasta);
    }

    private static function corte(array $datos): PeriodoReporte
    {
        $periodo = PeriodoReporte::alCorte(self::lleno($datos, 'corte') ? self::cadena($datos, 'corte') : CarbonImmutable::today());

        if ($periodo->corte->gt(CarbonImmutable::today())) {
            throw new PeriodoInvalido('La fecha de corte ('.$periodo->corte->format('d/m/Y').') no puede ser posterior a hoy.');
        }

        return $periodo;
    }

    private static function entero(array $datos, string $campo, int $porDefecto, string $nombre): int
    {
        if (! self::lleno($datos, $campo)) {
            return $porDefecto;
        }

        $valor = $datos[$campo];
        if (! is_scalar($valor) || filter_var($valor, FILTER_VALIDATE_INT) === false) {
            throw new PeriodoInvalido($nombre.' debe ser un número entero.');
        }

        return (int) $valor;
    }

    private static function cadena(array $datos, string $campo): string
    {
        if (! is_scalar($datos[$campo])) {
            throw new PeriodoInvalido('La fecha "'.$campo.'" no es válida (formato AAAA-MM-DD).');
        }

        return (string) $datos[$campo];
    }

    private static function lleno(array $datos, string $campo): bool
    {
        return isset($datos[$campo]) && $datos[$campo] !== '';
    }
}

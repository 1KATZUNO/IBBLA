<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;

/**
 * Período de fechas que muestra un dashboard (inicio y fin inclusivos).
 *
 * Se arma siempre desde la URL, así que cualquier vista es un enlace compartible:
 *
 *   ?fecha_inicio=2026-09-01&fecha_fin=2026-10-31   rango libre (tiene prioridad)
 *   ?mes=9&año=2026                                 acceso rápido: ese mes
 *   ?mes=&año=2026                                  acceso rápido: el año completo
 *   (sin parámetros)                                el valor por defecto de cada dashboard
 *
 * Un parámetro inválido (fechas mal formadas, fin anterior al inicio...) no rompe la
 * pantalla ni redirige: se ignora, se cae al valor por defecto y los motivos quedan en
 * $errores para mostrarlos junto al formulario. No se usa $request->validate() porque en
 * un GET redirige "atrás" y una URL compartida con un valor malo entraría en bucle.
 */
final class RangoFechas
{
    /** Vino de fecha_inicio / fecha_fin. */
    public const MODO_PERSONALIZADO = 'personalizado';

    /** Acceso rápido año + mes (o el mes actual por defecto). */
    public const MODO_MES = 'mes';

    /** Acceso rápido solo año (o el año actual por defecto). */
    public const MODO_ANIO = 'anio';

    /** Tope de amplitud de un rango libre; más allá los gráficos mensuales no se leen. */
    private const MAX_ANIOS = 10;

    public readonly CarbonImmutable $inicio;

    public readonly CarbonImmutable $fin;

    public readonly string $modo;

    public readonly MessageBag $errores;

    public function __construct(CarbonInterface $inicio, CarbonInterface $fin, string $modo = self::MODO_PERSONALIZADO, ?MessageBag $errores = null)
    {
        $inicio = CarbonImmutable::instance($inicio)->startOfDay();
        $fin = CarbonImmutable::instance($fin)->endOfDay();

        if ($fin->lt($inicio)) {
            [$inicio, $fin] = [$fin->startOfDay(), $inicio->endOfDay()];
        }

        $this->inicio = $inicio;
        $this->fin = $fin;
        $this->modo = $modo;
        $this->errores = $errores ?? new MessageBag;
    }

    // ------------------------------------------------------------------ construcción

    public static function mes(int $anio, int $mes, ?MessageBag $errores = null): self
    {
        $inicio = CarbonImmutable::create($anio, $mes, 1);

        return new self($inicio, $inicio->endOfMonth(), self::MODO_MES, $errores);
    }

    public static function anio(int $anio, ?MessageBag $errores = null): self
    {
        return new self(
            CarbonImmutable::create($anio, 1, 1),
            CarbonImmutable::create($anio, 12, 31),
            self::MODO_ANIO,
            $errores
        );
    }

    /**
     * @param  string  $porDefecto  MODO_MES (mes actual) o MODO_ANIO (año actual) cuando la URL no trae nada válido
     */
    public static function desdeRequest(Request $request, string $porDefecto = self::MODO_MES): self
    {
        $consulta = $request->query();
        $hoy = CarbonImmutable::instance(now());

        $errores = self::validar($consulta);

        $hayFechas = ! $errores->hasAny(['fecha_inicio', 'fecha_fin'])
            && self::lleno($consulta['fecha_inicio'] ?? null)
            && self::lleno($consulta['fecha_fin'] ?? null);

        if ($hayFechas) {
            return new self(
                CarbonImmutable::createFromFormat('Y-m-d', $consulta['fecha_inicio']),
                CarbonImmutable::createFromFormat('Y-m-d', $consulta['fecha_fin']),
                self::MODO_PERSONALIZADO,
                $errores
            );
        }

        $hayAccesoRapido = ! $errores->hasAny(['mes', 'año'])
            && (array_key_exists('mes', $consulta) || array_key_exists('año', $consulta));

        if ($hayAccesoRapido) {
            $anio = self::lleno($consulta['año'] ?? null) ? (int) $consulta['año'] : $hoy->year;

            return self::lleno($consulta['mes'] ?? null)
                ? self::mes($anio, (int) $consulta['mes'], $errores)
                : self::anio($anio, $errores);
        }

        return $porDefecto === self::MODO_ANIO
            ? self::anio($hoy->year, $errores)
            : self::mes($hoy->year, $hoy->month, $errores);
    }

    /**
     * Lo que se escribió en las fechas del formulario, tal cual, para que no se borre cuando
     * hay que corregirlo (el rango efectivo puede ser otro si lo escrito era inválido).
     *
     * @return array{fecha_inicio: ?string, fecha_fin: ?string}
     */
    public static function entrada(Request $request): array
    {
        $texto = fn (string $campo) => is_string($request->query($campo)) ? $request->query($campo) : null;

        return ['fecha_inicio' => $texto('fecha_inicio'), 'fecha_fin' => $texto('fecha_fin')];
    }

    /**
     * Atajos para el formulario: [etiqueta => parámetros de URL]. Siempre con fechas
     * explícitas, así el enlace significa lo mismo cuando se comparte otro día.
     *
     * @return array<string, array{fecha_inicio: string, fecha_fin: string}>
     */
    public static function atajos(): array
    {
        $hoy = CarbonImmutable::instance(now());
        $fechas = fn (CarbonImmutable $inicio, CarbonImmutable $fin) => [
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $fin->toDateString(),
        ];

        return [
            'Este mes' => $fechas($hoy->startOfMonth(), $hoy->endOfMonth()),
            'Mes anterior' => $fechas($hoy->subMonthNoOverflow()->startOfMonth(), $hoy->subMonthNoOverflow()->endOfMonth()),
            'Últimos 3 meses' => $fechas($hoy->subMonthsNoOverflow(2)->startOfMonth(), $hoy->endOfMonth()),
            'Este año' => $fechas($hoy->startOfYear(), $hoy->endOfYear()),
        ];
    }

    // ------------------------------------------------------------------ consulta

    /** Días del rango, ambos extremos incluidos. */
    public function dias(): int
    {
        return (int) $this->inicio->startOfDay()->diffInDays($this->fin->startOfDay()) + 1;
    }

    /** Empieza el día 1 de un mes y termina el último día de otro: son meses calendario completos. */
    public function esMesesCompletos(): bool
    {
        return $this->inicio->isSameDay($this->inicio->startOfMonth())
            && $this->fin->isSameDay($this->fin->endOfMonth());
    }

    public function esMesCompleto(): bool
    {
        return $this->esMesesCompletos() && $this->cantidadMeses() === 1;
    }

    public function esAnioCompleto(): bool
    {
        return $this->inicio->year === $this->fin->year
            && $this->inicio->isSameDay($this->inicio->startOfYear())
            && $this->fin->isSameDay($this->fin->endOfYear());
    }

    /** Meses calendario que toca el rango (aunque sea parcialmente). */
    public function cantidadMeses(): int
    {
        return ($this->fin->year - $this->inicio->year) * 12 + ($this->fin->month - $this->inicio->month) + 1;
    }

    /**
     * Primer día de cada mes calendario que toca el rango.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function mesesTocados(): Collection
    {
        $primero = $this->inicio->startOfMonth();

        return collect(range(0, $this->cantidadMeses() - 1))
            ->map(fn (int $n) => $primero->addMonthsNoOverflow($n));
    }

    /**
     * El mismo rango ampliado a meses completos. Lo que se calcula por mes (compromisos y
     * promesas) no se puede partir por días, así que usa este rango.
     */
    public function ampliadoAMeses(): self
    {
        return new self($this->inicio->startOfMonth(), $this->fin->endOfMonth(), $this->modo, $this->errores);
    }

    /**
     * El período inmediatamente anterior, para comparar:
     *  - meses calendario completos (un mes, un trimestre...): la misma cantidad de meses antes;
     *  - cualquier otro rango: la misma cantidad de días justo antes del inicio.
     */
    public function anterior(): self
    {
        if ($this->esMesesCompletos()) {
            return new self(
                $this->inicio->subMonthsNoOverflow($this->cantidadMeses()),
                $this->inicio->subDay(),
                self::MODO_PERSONALIZADO
            );
        }

        $fin = $this->inicio->subDay();

        return new self($fin->subDays($this->dias() - 1), $fin, self::MODO_PERSONALIZADO);
    }

    /**
     * Si el rango todavía no termina (incluye hoy), el tramo ya transcurrido: del inicio a hoy.
     * Null si el rango ya terminó o todavía no empieza.
     */
    public function enCurso(): ?self
    {
        $hoy = CarbonImmutable::today();
        if ($this->fin->lte($hoy) || $this->inicio->gt($hoy)) {
            return null;
        }

        return new self($this->inicio, $hoy, self::MODO_PERSONALIZADO);
    }

    /**
     * El período anterior con el que es justo comparar. Si el rango ya terminó, el anterior completo.
     * Si está en curso, los mismos días ya transcurridos del período anterior (del 1 al 24 de octubre
     * contra el 1 al 24 de noviembre), para no presentar como caída un mes que va a medias; con un
     * rango libre, la misma cantidad de días justo antes.
     */
    public function anteriorComparable(): self
    {
        $anterior = $this->anterior();
        $enCurso = $this->enCurso();
        if (! $enCurso) {
            return $anterior;
        }
        if (! $this->esMesesCompletos()) {
            return $enCurso->anterior();
        }

        $fin = $anterior->inicio->addDays($enCurso->dias() - 1)->min($anterior->fin);

        return new self($anterior->inicio, $fin, self::MODO_PERSONALIZADO);
    }

    /** "septiembre 2026", "2026" o "01/09/2026 – 15/10/2026". */
    public function etiqueta(): string
    {
        if ($this->esMesCompleto()) {
            return $this->inicio->locale('es')->translatedFormat('F Y');
        }
        if ($this->esAnioCompleto()) {
            return (string) $this->inicio->year;
        }

        return $this->inicio->format('d/m/Y').' – '.$this->fin->format('d/m/Y');
    }

    /** Mes que debe quedar elegido en el acceso rápido ('' = todo el año). */
    public function mesSeleccionado(): int|string
    {
        if ($this->modo === self::MODO_ANIO || $this->esAnioCompleto()) {
            return '';
        }

        return $this->inicio->month;
    }

    public function anioSeleccionado(): int
    {
        return $this->inicio->year;
    }

    /**
     * Años para el selector: los que tienen datos, el actual y los del propio rango.
     *
     * @param  iterable<int|string>  $aniosConDatos
     * @return Collection<int, int>
     */
    public function aniosParaSelector(iterable $aniosConDatos): Collection
    {
        return collect($aniosConDatos)
            ->map(fn ($anio) => (int) $anio)
            ->push((int) now()->year, $this->inicio->year, $this->fin->year)
            ->unique()
            ->sortDesc()
            ->values();
    }

    /** Parámetros de URL que reproducen exactamente este rango. */
    public function parametros(): array
    {
        return [
            'fecha_inicio' => $this->inicio->toDateString(),
            'fecha_fin' => $this->fin->toDateString(),
        ];
    }

    // ------------------------------------------------------------------ internos

    private static function validar(array $consulta): MessageBag
    {
        $maxAmplitud = function (string $atributo, mixed $valor, Closure $fail) use ($consulta) {
            $textoInicio = $consulta['fecha_inicio'] ?? null;
            if (! is_string($textoInicio) || ! is_string($valor)) {
                return; // el formato ya lo reporta date_format
            }

            try {
                $inicio = CarbonImmutable::createFromFormat('Y-m-d', $textoInicio);
                $fin = CarbonImmutable::createFromFormat('Y-m-d', $valor);
            } catch (\Throwable) {
                return;
            }

            if ($inicio && $fin && $inicio->diffInYears($fin) > self::MAX_ANIOS) {
                $fail('El rango no puede superar '.self::MAX_ANIOS.' años.');
            }
        };

        $validador = Validator::make($consulta, [
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'required_with:fecha_fin'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:2100-12-31', 'required_with:fecha_inicio', 'after_or_equal:fecha_inicio', $maxAmplitud],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'año' => ['nullable', 'integer', 'between:2000,2100'],
        ], [
            'fecha_inicio.date_format' => 'La fecha de inicio no es válida.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio no puede ser anterior al año 2000.',
            'fecha_inicio.required_with' => 'Indica también la fecha de inicio.',
            'fecha_fin.date_format' => 'La fecha de fin no es válida.',
            'fecha_fin.before_or_equal' => 'La fecha de fin no puede ser posterior al año 2100.',
            'fecha_fin.required_with' => 'Indica también la fecha de fin.',
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'mes.integer' => 'El mes no es válido.',
            'mes.between' => 'El mes debe estar entre 1 y 12.',
            'año.integer' => 'El año no es válido.',
            'año.between' => 'El año debe estar entre 2000 y 2100.',
        ]);

        return $validador->errors();
    }

    private static function lleno(mixed $valor): bool
    {
        return is_scalar($valor) && trim((string) $valor) !== '';
    }
}

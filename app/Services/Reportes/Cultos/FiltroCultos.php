<?php

namespace App\Services\Reportes\Cultos;

use App\Models\TenantServiceType;
use App\Support\RangoFechas;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;

/**
 * Filtros de los reportes de cultos e ingresos (HU-R10, HU-R11), siempre desde la URL para que
 * cualquier vista sea un enlace que se puede copiar:
 *
 *   ?fecha_inicio=2026-01-01&fecha_fin=2026-03-31   rango (por defecto, el mes actual)
 *   &tipos[]=domingo&tipos[]=miercoles              tipos de culto (vacio = todos)
 *   &cultos[]=12&cultos[]=15                        cultos elegidos dentro del rango (vacio = todos)
 *
 * Un valor raro no rompe la pantalla: se ignora y el motivo queda en $errores.
 */
final class FiltroCultos
{
    /** Tope del rango: un anio de cultos responde en pocos segundos (Hostinger corta a los 10 s). */
    public const MAX_DIAS = 366;

    /**
     * @param  list<string>  $tipos  slugs de tipo de culto
     * @param  list<int>  $cultos  ids de cultos elegidos
     */
    public function __construct(
        public readonly RangoFechas $rango,
        public readonly array $tipos = [],
        public readonly array $cultos = [],
        public readonly MessageBag $errores = new MessageBag,
        public readonly bool $recortado = false,
    ) {}

    public static function desde(Request $request, ?Collection $tiposDisponibles = null): self
    {
        $rango = RangoFechas::desdeRequest($request);
        $errores = $rango->errores;
        $recortado = false;

        if ($rango->dias() > self::MAX_DIAS) {
            $rango = new RangoFechas($rango->inicio, $rango->inicio->addDays(self::MAX_DIAS - 1), RangoFechas::MODO_PERSONALIZADO, $errores);
            $errores->add('fecha_fin', 'El rango máximo es de un año: se muestra del '.$rango->inicio->format('d/m/Y').' al '.$rango->fin->format('d/m/Y').'. Para más tiempo, genere un reporte por año.');
            $recortado = true;
        }

        $slugs = ($tiposDisponibles ?? collect())->pluck('slug')->all();
        $tipos = collect((array) $request->query('tipos', []))
            ->filter(fn ($t) => is_string($t) && ($slugs === [] || in_array($t, $slugs, true)))
            ->unique()->values()->all();

        $cultos = collect((array) $request->query('cultos', []))
            ->filter(fn ($id) => is_scalar($id) && ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->unique()->values()->all();

        return new self($rango, $tipos, $cultos, $errores, $recortado);
    }

    public static function deRango(CarbonImmutable $inicio, CarbonImmutable $fin, array $tipos = []): self
    {
        return new self(new RangoFechas($inicio, $fin), $tipos);
    }

    /** El mismo filtro en el periodo anterior de igual duracion (sin cultos elegidos). */
    public function anterior(): self
    {
        return new self($this->rango->anterior(), $this->tipos);
    }

    /** @return array<string, mixed> parametros de URL que reproducen este filtro */
    public function aParametros(): array
    {
        return array_filter($this->rango->parametros() + [
            'tipos' => $this->tipos,
            'cultos' => $this->cultos,
        ], fn ($v) => $v !== [] && $v !== null);
    }

    /**
     * Filtros en texto para el encabezado del PDF/Excel ("Tipos: Domingo AM, Miércoles").
     *
     * @return list<string>
     */
    public function descripcion(?Collection $tiposDisponibles = null): array
    {
        $textos = [];
        if ($this->tipos !== []) {
            $nombres = ($tiposDisponibles ?? collect())->whereIn('slug', $this->tipos)->pluck('nombre')->all();
            $textos[] = 'Tipos de culto: '.implode(', ', $nombres !== [] ? $nombres : $this->tipos);
        } else {
            $textos[] = 'Tipos de culto: todos';
        }
        if ($this->cultos !== []) {
            $textos[] = count($this->cultos) === 1 ? '1 culto elegido' : count($this->cultos).' cultos elegidos';
        }

        return $textos;
    }

    public function etiquetaPeriodo(): string
    {
        return 'Del '.$this->rango->inicio->format('d/m/Y').' al '.$this->rango->fin->format('d/m/Y');
    }

    /** Tipos de culto que puede ver el usuario (los mismos de la lista de Recuento). */
    public static function tiposDisponibles(): Collection
    {
        return TenantServiceType::disponiblesPara(auth()->user());
    }
}

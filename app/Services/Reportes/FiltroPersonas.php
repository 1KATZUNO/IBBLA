<?php

namespace App\Services\Reportes;

use App\Models\Ministerio;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;

/**
 * A quienes entra un reporte, leido de la URL para que el enlace se pueda compartir y las
 * descargas repitan la pantalla:
 *
 *   ?grupo=todas                          todas las personas activas (por defecto)
 *   ?grupo=con_promesa                    activas con promesa en alguna categoria de compromiso
 *   ?grupo=ministerio&ministerio=3        activas que sirven hoy en ese ministerio
 *   ?grupo=ministerio&ministerio=todos    activas que sirven en algun ministerio ("servidores")
 *   ?grupo=seleccion&personas=1,5,9       esas personas (aunque esten inactivas)
 *
 * Sin `grupo`, si vienen `personas` es una seleccion. Todo pasa por los scopes de la iglesia: un
 * id de otra iglesia simplemente no aparece. Un valor invalido no rompe la pantalla: se usa
 * "todas" y el motivo queda en $errores.
 */
final class FiltroPersonas
{
    public const TODAS = 'todas';

    public const CON_PROMESA = 'con_promesa';

    public const MINISTERIO = 'ministerio';

    public const SELECCION = 'seleccion';

    /** Valor de `ministerio` para "cualquier ministerio". */
    public const CUALQUIER_MINISTERIO = 'todos';

    /** Tope de personas elegidas a mano en una URL. */
    public const MAX_SELECCION = 500;

    /**
     * @param  list<int>  $personaIds
     */
    private function __construct(
        public readonly string $grupo,
        public readonly ?int $ministerioId,
        public readonly array $personaIds,
        public readonly MessageBag $errores,
        private readonly ?string $nombreMinisterio = null,
    ) {}

    public static function todas(): self
    {
        return new self(self::TODAS, null, [], new MessageBag);
    }

    /** @param  Request|array<string, mixed>  $entrada */
    public static function desde(Request|array $entrada): self
    {
        $datos = $entrada instanceof Request ? $entrada->query() : $entrada;
        $errores = new MessageBag;

        $ids = self::ids($datos['personas'] ?? null, $errores);
        $grupo = $datos['grupo'] ?? null;
        if ($grupo === null || $grupo === '') {
            $grupo = $ids !== [] ? self::SELECCION : self::TODAS;
        }

        switch ($grupo) {
            case self::TODAS:
            case self::CON_PROMESA:
                return new self($grupo, null, [], $errores);

            case self::SELECCION:
                if ($ids === []) {
                    $errores->add('personas', 'Elija al menos una persona.');

                    return new self(self::TODAS, null, [], $errores);
                }

                return new self(self::SELECCION, null, $ids, $errores);

            case self::MINISTERIO:
                $valor = $datos['ministerio'] ?? self::CUALQUIER_MINISTERIO;
                if ($valor === self::CUALQUIER_MINISTERIO || $valor === '' || $valor === null) {
                    return new self(self::MINISTERIO, null, [], $errores);
                }
                $ministerio = is_scalar($valor) && ctype_digit((string) $valor) ? Ministerio::find((int) $valor) : null;
                if (! $ministerio) {
                    $errores->add('ministerio', 'El ministerio elegido no existe.');

                    return new self(self::TODAS, null, [], $errores);
                }

                return new self(self::MINISTERIO, $ministerio->id, [], $errores, $ministerio->nombre);

            default:
                $errores->add('grupo', 'El grupo de personas "'.(is_scalar($grupo) ? $grupo : '?').'" no existe.');

                return new self(self::TODAS, null, [], $errores);
        }
    }

    /**
     * Personas que entran (id, nombre, activo), ordenadas por nombre.
     *
     * @return Collection<int, Persona>
     */
    public function personas(): Collection
    {
        return $this->consulta()->get(['id', 'nombre', 'activo']);
    }

    public function consulta(): Builder
    {
        $consulta = Persona::query()->orderBy('nombre')->orderBy('id');

        return match ($this->grupo) {
            self::SELECCION => $consulta->whereIn('id', $this->personaIds),
            self::CON_PROMESA => $consulta->where('activo', true)->whereHas('promesas', function (Builder $q) {
                $q->whereIn('categoria', tenant_categories(['excluir_de_promesas' => false])->pluck('slug')->all())
                    ->where('monto', '>', 0);
            }),
            self::MINISTERIO => $consulta->where('activo', true)->whereHas('ministerios', function (Builder $q) {
                $q->whereNull('persona_ministerio.hasta');
                if ($this->ministerioId !== null) {
                    $q->where('ministerios.id', $this->ministerioId);
                }
            }),
            default => $consulta->where('activo', true),
        };
    }

    /** Texto para encabezados de PDF/Excel y para la auditoria. */
    public function descripcion(): string
    {
        return match ($this->grupo) {
            self::CON_PROMESA => 'Personas activas con promesa',
            self::MINISTERIO => $this->ministerioId === null ? 'Personas que sirven en algún ministerio' : 'Ministerio: '.$this->nombreMinisterio,
            self::SELECCION => 'Selección de '.count($this->personaIds).' '.(count($this->personaIds) === 1 ? 'persona' : 'personas'),
            default => 'Todas las personas activas',
        };
    }

    /** @return array<string, int|string> */
    public function aParametros(): array
    {
        return match ($this->grupo) {
            self::MINISTERIO => ['grupo' => self::MINISTERIO, 'ministerio' => $this->ministerioId ?? self::CUALQUIER_MINISTERIO],
            self::SELECCION => ['grupo' => self::SELECCION, 'personas' => implode(',', $this->personaIds)],
            default => ['grupo' => $this->grupo],
        };
    }

    public function tieneErrores(): bool
    {
        return $this->errores->isNotEmpty();
    }

    /** @return list<int> ids unicos y validos de "1,5,9" o de personas[]=1&personas[]=5 */
    private static function ids(mixed $valor, MessageBag $errores): array
    {
        if ($valor === null || $valor === '' || $valor === []) {
            return [];
        }

        $partes = is_array($valor) ? $valor : explode(',', (string) $valor);
        $ids = [];
        foreach ($partes as $parte) {
            $parte = is_scalar($parte) ? trim((string) $parte) : '';
            if ($parte !== '' && ctype_digit($parte) && (int) $parte > 0) {
                $ids[(int) $parte] = (int) $parte;
            }
        }

        if (count($ids) > self::MAX_SELECCION) {
            $errores->add('personas', 'Se pueden elegir hasta '.self::MAX_SELECCION.' personas; se tomaron las primeras.');
            $ids = array_slice($ids, 0, self::MAX_SELECCION, true);
        }

        return array_values($ids);
    }
}

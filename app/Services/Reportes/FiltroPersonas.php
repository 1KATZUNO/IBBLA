<?php

namespace App\Services\Reportes;

use App\Models\Ministerio;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;

/**
 * A quienes entra un reporte, leido de la URL para que el enlace se pueda compartir y las
 * descargas repitan la pantalla.
 *
 * Enlaces de siempre (siguen dando lo mismo):
 *
 *   ?grupo=todas                          todas las personas activas (por defecto)
 *   ?grupo=con_promesa                    activas con promesa en alguna categoria de compromiso
 *   ?grupo=ministerio&ministerio=3        activas que sirven hoy en ese ministerio
 *   ?grupo=ministerio&ministerio=todos    activas que sirven en algun ministerio ("servidores")
 *   ?grupo=seleccion&personas=1,5,9       esas personas (aunque esten inactivas)
 *
 * Filtros rapidos combinables del selector (HU-R2); "todas las que cumplen los filtros" viaja como
 * filtros, nunca como cientos de ids:
 *
 *   ?grupo=filtros&estado=activos|inactivos|todos&promesa=si|no|todos&aporto=si|no|todos
 *                 &ministerios=3,7 (o "todos" = cualquier ministerio)
 *
 * "aporto" mira si dio algo (en cualquier categoria) en el periodo del reporte: por eso personas() y
 * consulta() reciben el periodo. Si los filtros equivalen a un grupo de siempre, el filtro se
 * normaliza a ese grupo (misma descripcion y mismos parametros de antes).
 *
 * Sin `grupo`, si vienen `personas` es una seleccion. Todo pasa por los scopes de la iglesia: un
 * id de otra iglesia simplemente no aparece. Un valor invalido no rompe la pantalla: se usa el
 * valor por defecto y el motivo queda en $errores.
 */
final class FiltroPersonas
{
    public const TODAS = 'todas';

    public const CON_PROMESA = 'con_promesa';

    public const MINISTERIO = 'ministerio';

    public const SELECCION = 'seleccion';

    /** Filtros rapidos combinables (estado, ministerios, promesa, aporto). */
    public const FILTROS = 'filtros';

    /** Valor de `ministerio` / `ministerios` para "cualquier ministerio". */
    public const CUALQUIER_MINISTERIO = 'todos';

    /** Tope de personas elegidas a mano en una URL. */
    public const MAX_SELECCION = 500;

    public const ESTADOS = ['activos', 'inactivos', 'todos'];

    public const SI_NO = ['si', 'no', 'todos'];

    /**
     * @param  list<int>  $personaIds
     * @param  list<int>  $ministerioIds
     */
    private function __construct(
        public readonly string $grupo,
        public readonly ?int $ministerioId,
        public readonly array $personaIds,
        public readonly MessageBag $errores,
        private readonly ?string $nombreMinisterio = null,
        public readonly string $estado = 'activos',
        public readonly array $ministerioIds = [],
        public readonly bool $cualquierMinisterio = false,
        public readonly string $promesa = 'todos',
        public readonly string $aporto = 'todos',
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
            $grupo = $ids !== [] ? self::SELECCION : (self::traeFiltros($datos) ? self::FILTROS : self::TODAS);
        }

        switch ($grupo) {
            case self::TODAS:
            case self::CON_PROMESA:
                return new self($grupo, null, [], $errores, promesa: $grupo === self::CON_PROMESA ? 'si' : 'todos');

            case self::SELECCION:
                // Los filtros rapidos viajan tambien para que la pantalla los recuerde, pero entran
                // exactamente las personas elegidas.
                $filtros = self::filtros($datos, $errores);
                if ($ids === []) {
                    $errores->add('personas', 'Elija al menos una persona.');

                    return new self(self::TODAS, null, [], $errores);
                }

                return new self(self::SELECCION, null, $ids, $errores, null, ...$filtros);

            case self::MINISTERIO:
                $valor = $datos['ministerio'] ?? self::CUALQUIER_MINISTERIO;
                if ($valor === self::CUALQUIER_MINISTERIO || $valor === '' || $valor === null) {
                    return new self(self::MINISTERIO, null, [], $errores, cualquierMinisterio: true);
                }
                $ministerio = is_scalar($valor) && ctype_digit((string) $valor) ? Ministerio::find((int) $valor) : null;
                if (! $ministerio) {
                    $errores->add('ministerio', 'El ministerio elegido no existe.');

                    return new self(self::TODAS, null, [], $errores);
                }

                return new self(self::MINISTERIO, $ministerio->id, [], $errores, $ministerio->nombre, ministerioIds: [$ministerio->id]);

            case self::FILTROS:
                return self::normalizado(self::filtros($datos, $errores), $errores);

            default:
                $errores->add('grupo', 'El grupo de personas "'.(is_scalar($grupo) ? $grupo : '?').'" no existe.');

                return new self(self::TODAS, null, [], $errores);
        }
    }

    /**
     * Personas que entran (id, nombre, activo), ordenadas por nombre. Con el periodo del reporte se
     * aplica tambien "aporto en el periodo".
     *
     * @return Collection<int, Persona>
     */
    public function personas(?PeriodoReporte $periodo = null): Collection
    {
        return $this->consulta($periodo)->get(['id', 'nombre', 'activo']);
    }

    public function consulta(?PeriodoReporte $periodo = null): Builder
    {
        $consulta = Persona::query()->orderBy('nombre')->orderBy('id');

        if ($this->grupo === self::SELECCION) {
            return $consulta->whereIn('id', $this->personaIds);
        }

        if ($this->estado !== 'todos') {
            $consulta->where('activo', $this->estado === 'activos');
        }

        if ($this->cualquierMinisterio || $this->ministerioIds !== []) {
            $consulta->whereHas('ministerios', function (Builder $q) {
                $q->whereNull('persona_ministerio.hasta');
                if (! $this->cualquierMinisterio) {
                    $q->whereIn('ministerios.id', $this->ministerioIds);
                }
            });
        }

        if ($this->promesa !== 'todos') {
            $conPromesa = function (Builder $q) {
                $q->whereIn('categoria', tenant_categories(['excluir_de_promesas' => false])->pluck('slug')->all())
                    ->where('monto', '>', 0);
            };
            $this->promesa === 'si' ? $consulta->whereHas('promesas', $conPromesa) : $consulta->whereDoesntHave('promesas', $conPromesa);
        }

        if ($this->aporto !== 'todos' && $periodo !== null) {
            $periodo = $periodo->recortadoAHoy();
            $aportes = function ($q) use ($periodo) {
                $q->select(DB::raw(1))
                    ->from('sobres as s')
                    ->join('cultos as c', 'c.id', '=', 's.culto_id')
                    ->join('sobre_detalles as d', 'd.sobre_id', '=', 's.id')
                    ->whereColumn('s.persona_id', 'personas.id')
                    ->where('d.monto', '>', 0)
                    ->whereBetween('c.fecha', [$periodo->desde->toDateString(), $periodo->hasta->toDateString()]);
            };
            $this->aporto === 'si' ? $consulta->whereExists($aportes) : $consulta->whereNotExists($aportes);
        }

        return $consulta;
    }

    /** Texto para encabezados de PDF/Excel y para la auditoria. */
    public function descripcion(): string
    {
        return match ($this->grupo) {
            self::CON_PROMESA => 'Personas activas con promesa',
            self::MINISTERIO => $this->ministerioId === null ? 'Personas que sirven en algún ministerio' : 'Ministerio: '.$this->nombreMinisterio,
            self::SELECCION => 'Selección de '.count($this->personaIds).' '.(count($this->personaIds) === 1 ? 'persona' : 'personas'),
            self::FILTROS => $this->descripcionFiltros(),
            default => 'Todas las personas activas',
        };
    }

    /** @return array<string, int|string> */
    public function aParametros(): array
    {
        return match ($this->grupo) {
            self::MINISTERIO => ['grupo' => self::MINISTERIO, 'ministerio' => $this->ministerioId ?? self::CUALQUIER_MINISTERIO],
            self::SELECCION => ['grupo' => self::SELECCION, 'personas' => implode(',', $this->personaIds)],
            self::FILTROS => array_filter([
                'grupo' => self::FILTROS,
                'estado' => $this->estado === 'activos' ? null : $this->estado,
                'ministerios' => $this->cualquierMinisterio ? self::CUALQUIER_MINISTERIO : ($this->ministerioIds === [] ? null : implode(',', $this->ministerioIds)),
                'promesa' => $this->promesa === 'todos' ? null : $this->promesa,
                'aporto' => $this->aporto === 'todos' ? null : $this->aporto,
            ], fn ($v) => $v !== null),
            default => ['grupo' => $this->grupo],
        };
    }

    /**
     * Estado de los filtros rapidos para el selector de la pantalla (tambien en una seleccion, para
     * que "Seleccionar todos los filtrados" vuelva a los mismos filtros).
     *
     * @return array{estado: string, ministerios: list<int|string>, promesa: string, aporto: string}
     */
    public function filtrosRapidos(): array
    {
        return [
            'estado' => $this->estado,
            'ministerios' => $this->cualquierMinisterio ? [self::CUALQUIER_MINISTERIO] : $this->ministerioIds,
            'promesa' => $this->promesa,
            'aporto' => $this->aporto,
        ];
    }

    public function esSeleccion(): bool
    {
        return $this->grupo === self::SELECCION;
    }

    public function tieneErrores(): bool
    {
        return $this->errores->isNotEmpty();
    }

    // ------------------------------------------------------------------

    /** @param  array<string, mixed>  $datos */
    private static function traeFiltros(array $datos): bool
    {
        foreach (['estado', 'ministerios', 'promesa', 'aporto'] as $clave) {
            if (isset($datos[$clave]) && $datos[$clave] !== '' && $datos[$clave] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filtros rapidos leidos de la URL, como argumentos con nombre del constructor.
     *
     * @param  array<string, mixed>  $datos
     * @return array{estado: string, ministerioIds: list<int>, cualquierMinisterio: bool, promesa: string, aporto: string}
     */
    private static function filtros(array $datos, MessageBag $errores): array
    {
        $opcion = function (string $clave, array $validas, string $porDefecto) use ($datos, $errores): string {
            $valor = $datos[$clave] ?? null;
            if ($valor === null || $valor === '') {
                return $porDefecto;
            }
            if (! is_string($valor) || ! in_array($valor, $validas, true)) {
                $errores->add($clave, 'El filtro "'.$clave.'" no admite ese valor; se usó «'.$porDefecto.'».');

                return $porDefecto;
            }

            return $valor;
        };

        $cualquier = false;
        $ministerios = [];
        $valor = $datos['ministerios'] ?? null;
        if ($valor !== null && $valor !== '' && $valor !== []) {
            $partes = is_array($valor) ? $valor : explode(',', (string) $valor);
            $pedidos = [];
            foreach ($partes as $parte) {
                $parte = is_scalar($parte) ? trim((string) $parte) : '';
                if ($parte === self::CUALQUIER_MINISTERIO) {
                    $cualquier = true;
                } elseif ($parte !== '' && ctype_digit($parte)) {
                    $pedidos[(int) $parte] = (int) $parte;
                }
            }
            if (! $cualquier && $pedidos !== []) {
                $ministerios = Ministerio::whereIn('id', array_values($pedidos))->orderBy('orden')->orderBy('nombre')->pluck('id')->map(fn ($id) => (int) $id)->all();
                if (count($ministerios) < count($pedidos)) {
                    $errores->add('ministerios', 'Algún ministerio elegido no existe; se ignoró.');
                }
            }
        }

        return [
            'estado' => $opcion('estado', self::ESTADOS, 'activos'),
            'ministerioIds' => $ministerios,
            'cualquierMinisterio' => $cualquier,
            'promesa' => $opcion('promesa', self::SI_NO, 'todos'),
            'aporto' => $opcion('aporto', self::SI_NO, 'todos'),
        ];
    }

    /**
     * Si los filtros equivalen a un grupo de siempre, ese grupo (misma descripcion y parametros).
     *
     * @param  array{estado: string, ministerioIds: list<int>, cualquierMinisterio: bool, promesa: string, aporto: string}  $f
     */
    private static function normalizado(array $f, MessageBag $errores): self
    {
        $soloActivos = $f['estado'] === 'activos' && $f['aporto'] === 'todos';
        $sinMinisterio = ! $f['cualquierMinisterio'] && $f['ministerioIds'] === [];

        if ($soloActivos && $sinMinisterio && $f['promesa'] === 'todos') {
            return new self(self::TODAS, null, [], $errores);
        }
        if ($soloActivos && $sinMinisterio && $f['promesa'] === 'si') {
            return new self(self::CON_PROMESA, null, [], $errores, promesa: 'si');
        }
        if ($soloActivos && $f['promesa'] === 'todos' && $f['cualquierMinisterio']) {
            return new self(self::MINISTERIO, null, [], $errores, cualquierMinisterio: true);
        }
        if ($soloActivos && $f['promesa'] === 'todos' && count($f['ministerioIds']) === 1) {
            $ministerio = Ministerio::find($f['ministerioIds'][0]);

            return new self(self::MINISTERIO, $ministerio->id, [], $errores, $ministerio->nombre, ministerioIds: [$ministerio->id]);
        }

        return new self(self::FILTROS, null, [], $errores, null, ...$f);
    }

    private function descripcionFiltros(): string
    {
        $partes = [match ($this->estado) {
            'inactivos' => 'Personas inactivas',
            'todos' => 'Personas activas e inactivas',
            default => 'Personas activas',
        }];

        if ($this->promesa !== 'todos') {
            $partes[] = $this->promesa === 'si' ? 'con promesa' : 'sin promesa';
        }
        if ($this->cualquierMinisterio) {
            $partes[] = 'que sirven en algún ministerio';
        } elseif ($this->ministerioIds !== []) {
            $nombres = Ministerio::whereIn('id', $this->ministerioIds)->orderBy('orden')->orderBy('nombre')->pluck('nombre')->all();
            $partes[] = 'de '.(count($nombres) === 1 ? 'el ministerio ' : 'los ministerios ').implode(', ', $nombres);
        }
        if ($this->aporto !== 'todos') {
            $partes[] = $this->aporto === 'si' ? 'que aportaron en el periodo' : 'que no aportaron en el periodo';
        }

        return implode(', ', $partes);
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

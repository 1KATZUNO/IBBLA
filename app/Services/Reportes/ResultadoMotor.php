<?php

namespace App\Services\Reportes;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Illuminate\Support\Str;
use Traversable;

/**
 * Salida del motor: una CuentaPersona por persona (en el orden en que se pidieron) y el contexto
 * con que se calculo (periodo, categorias de la iglesia, tipo de cambio de hoy).
 *
 * @implements IteratorAggregate<int, CuentaPersona>
 */
final class ResultadoMotor implements Countable, IteratorAggregate
{
    /**
     * @param  array<string, string>  $categorias  slug => nombre: categorias activas de la iglesia, en su orden
     * @param  list<string>  $categoriasCompromiso  slugs de las que no estan excluidas de promesas, en el mismo orden
     * @param  array<int, CuentaPersona>  $cuentas  id de persona => cuenta
     */
    public function __construct(
        public readonly PeriodoReporte $periodo,
        public readonly array $categorias,
        public readonly array $categoriasCompromiso,
        public readonly float $tipoCambioHoy,
        private readonly array $cuentas,
    ) {}

    public function cuenta(int $personaId): ?CuentaPersona
    {
        return $this->cuentas[$personaId] ?? null;
    }

    /** @return array<int, CuentaPersona> */
    public function cuentas(): array
    {
        return $this->cuentas;
    }

    /** Nombre de una categoria; las que la iglesia ya no tiene salen con su slug legible ("pro_templo" => "Pro Templo"). */
    public function nombreCategoria(string $slug): string
    {
        return $this->categorias[$slug] ?? Str::headline($slug);
    }

    public function count(): int
    {
        return count($this->cuentas);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->cuentas);
    }
}

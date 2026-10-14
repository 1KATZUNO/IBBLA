<?php

namespace App\Services\Analisis;

use App\Models\Tenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lo que comparten todas las verificaciones de una iglesia: el rango de fechas, la fecha de corte, los
 * límites de filas y consultas ya acotadas a la iglesia. Solo lee; nada de aquí escribe en la base.
 *
 * Las consultas usan DB::table y no los modelos a propósito: los modelos aplican el scope por iglesia
 * del usuario conectado, y el análisis corre desde la consola, sin sesión, sobre una o varias iglesias.
 */
final class ContextoAnalisis
{
    /** @var array<int, string>|null */
    private ?array $personas = null;

    /** @var list<string>|null */
    private ?array $categorias = null;

    /** @var list<array{fecha: string, venta: float}>|null */
    private ?array $tiposCambio = null;

    public function __construct(
        public readonly Tenant $iglesia,
        public readonly ?string $desde,
        public readonly ?string $hasta,
        public readonly Carbon $hoy,
        public readonly int $anioEstadoCuenta,
        public readonly int $limite = 25,
        public readonly int $diasSinCerrar = 7,
        public readonly float $tolerancia = 0.01,
    ) {
    }

    /** Cultos de la iglesia dentro del rango (alias c). */
    public function cultos(): Builder
    {
        $consulta = DB::table('cultos as c')->where('c.tenant_id', $this->iglesia->id);

        return $this->acotar($consulta);
    }

    /** Sobres de los cultos de la iglesia dentro del rango (alias s, con el culto como c). */
    public function sobres(): Builder
    {
        $consulta = DB::table('sobres as s')->join('cultos as c', 'c.id', '=', 's.culto_id')->where('c.tenant_id', $this->iglesia->id);

        return $this->acotar($consulta);
    }

    private function acotar(Builder $consulta): Builder
    {
        return $consulta
            ->when($this->desde, fn (Builder $q) => $q->where('c.fecha', '>=', $this->desde))
            ->when($this->hasta, fn (Builder $q) => $q->where('c.fecha', '<=', $this->hasta));
    }

    /** Descripción del rango para el encabezado del informe. */
    public function rango(): string
    {
        return match (true) {
            $this->desde !== null && $this->hasta !== null => 'del '.Formato::fecha($this->desde).' al '.Formato::fecha($this->hasta),
            $this->desde !== null => 'desde el '.Formato::fecha($this->desde),
            $this->hasta !== null => 'hasta el '.Formato::fecha($this->hasta),
            default => 'todas las fechas',
        };
    }

    public function nombrePersona(?int $id): string
    {
        if ($id === null) {
            return '(sin persona)';
        }

        $this->personas ??= DB::table('personas')->where('tenant_id', $this->iglesia->id)->pluck('nombre', 'id')->all();

        return $this->personas[$id] ?? "Persona #{$id}";
    }

    /** Slugs de las categorías de la iglesia (activas o no), en minúsculas. */
    public function categorias(): array
    {
        return $this->categorias ??= DB::table('tenant_categories')
            ->where('tenant_id', $this->iglesia->id)
            ->pluck('slug')
            ->map(fn ($slug) => mb_strtolower((string) $slug))
            ->all();
    }

    /** Tipo de cambio de venta vigente en una fecha (el último registrado hasta ese día), o null si no hay. */
    public function tipoCambioDe(string $fecha): ?float
    {
        $this->tiposCambio ??= DB::table('tipo_cambios')->orderBy('fecha')->get(['fecha', 'venta'])
            ->map(fn ($t) => ['fecha' => (string) $t->fecha, 'venta' => (float) $t->venta])->all();

        $vigente = null;
        foreach ($this->tiposCambio as $tipo) {
            if ($tipo['fecha'] > $fecha) {
                break;
            }
            $vigente = $tipo['venta'];
        }

        return $vigente > 0 ? $vigente : null;
    }

    /** El tipo de cambio más reciente (el que usa «hoy» la app), o 0 si no hay ninguno. */
    public function tipoCambioReciente(): float
    {
        $this->tipoCambioDe('9999-12-31');

        return $this->tiposCambio === [] ? 0.0 : (float) end($this->tiposCambio)['venta'];
    }

    /** ¿La diferencia es mayor que el redondeo de centavos? */
    public function difiere(float $a, float $b): bool
    {
        return abs(round($a - $b, 2)) > $this->tolerancia;
    }
}

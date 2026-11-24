<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Acceso de edicion que el administrador le da a un usuario de tesoreria sobre cultos cerrados:
 * unos cultos concretos, un rango de fechas o todos, con vencimiento opcional (modulo Accesos).
 */
class PermisoEdicion extends Model
{
    use BelongsToTenant;

    public const TIPO_CULTOS = 'cultos';

    public const TIPO_RANGO = 'rango';

    public const TIPO_TODOS = 'todos';

    public const TIPOS = [self::TIPO_CULTOS, self::TIPO_RANGO, self::TIPO_TODOS];

    protected $table = 'permisos_edicion';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'tipo',
        'referencia',
        'desde',
        'hasta',
        'vence_el',
        'motivo',
        'otorgado_por',
        'revocado_at',
        'revocado_por',
    ];

    protected function casts(): array
    {
        return [
            'referencia' => 'array',
            'desde' => 'date',
            'hasta' => 'date',
            'vence_el' => 'datetime',
            'revocado_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function otorgadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'otorgado_por');
    }

    public function revocadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revocado_por');
    }

    /** Sin revocar y sin vencer. */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('revocado_at')
            ->where(fn (Builder $q) => $q->whereNull('vence_el')->orWhere('vence_el', '>', now()));
    }

    /** Revocados o vencidos (el historial). */
    public function scopeNoVigentes(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNotNull('revocado_at')
            ->orWhere(fn (Builder $v) => $v->whereNotNull('vence_el')->where('vence_el', '<=', now())));
    }

    public function estaVigente(): bool
    {
        return $this->revocado_at === null && ($this->vence_el === null || $this->vence_el->isFuture());
    }

    /** @return array<int, int> */
    public function idsCultos(): array
    {
        return array_values(array_map('intval', (array) ($this->referencia ?? [])));
    }

    /** ¿Este acceso alcanza a ese culto? (no mira si esta vigente) */
    public function cubre(Culto $culto): bool
    {
        if ($this->tenant_id !== null && $culto->tenant_id !== null && (int) $this->tenant_id !== (int) $culto->tenant_id) {
            return false;
        }

        return match ($this->tipo) {
            self::TIPO_TODOS => true,
            self::TIPO_CULTOS => in_array((int) $culto->id, $this->idsCultos(), true),
            self::TIPO_RANGO => $this->desde !== null && $this->hasta !== null
                && $culto->fecha->betweenIncluded($this->desde->copy()->startOfDay(), $this->hasta->copy()->endOfDay()),
            default => false,
        };
    }

    /**
     * Alcance en palabras: "Todos los cultos", "Del 01/09/2026 al 30/09/2026", "Culto del 11/10/2026 (Domingo AM)"
     * o "3 cultos: 11/10, 07/10 y 04/10".
     *
     * @param  Collection<int, Culto>  $cultosPorId  cultos ya cargados (keyBy id) para no consultar uno por uno
     */
    public function descripcionAlcance(Collection $cultosPorId): string
    {
        if ($this->tipo === self::TIPO_TODOS) {
            return 'Todos los cultos';
        }

        if ($this->tipo === self::TIPO_RANGO) {
            return 'Del '.$this->desde?->format('d/m/Y').' al '.$this->hasta?->format('d/m/Y');
        }

        $cultos = collect($this->idsCultos())->map(fn ($id) => $cultosPorId->get($id));
        if ($cultos->count() === 1) {
            $culto = $cultos->first();

            return $culto ? 'Culto del '.$culto->fecha->format('d/m/Y').' ('.$culto->tipo_nombre.')' : 'Un culto que ya no existe';
        }

        $fechas = $cultos->map(fn ($c) => $c ? $c->fecha->format('d/m') : 'eliminado');
        $mostradas = $fechas->take(5);
        $resto = $fechas->count() - $mostradas->count();

        return $cultos->count().' cultos: '.($resto > 0 ? $mostradas->join(', ').' y '.$resto.' más' : $mostradas->join(', ', ' y '));
    }
}

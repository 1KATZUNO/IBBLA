<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Corte de promesas ("promesas futuras"): un juego completo de promesas que entra en vigor el dia
 * `inicia_el` (siempre el 1 de un mes) y reemplaza a TODAS las promesas anteriores de la iglesia.
 * Quien no este en el corte queda sin promesa desde ese dia; quien es nuevo, entra.
 *
 * Mientras `activado_at` es null el corte esta PROGRAMADO: sus promesas viven en
 * `promesas_programadas` y ningun calculo las ve. Al activarse pasan a `promesas` y las anteriores
 * se guardan en `promesas_historial` con su vigencia.
 */
class CortePromesas extends Model
{
    use BelongsToTenant;

    public const PROGRAMADO = 'programado';

    public const VIGENTE = 'vigente';

    public const ANTERIOR = 'anterior';

    protected $table = 'cortes_promesas';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'inicia_el',
        'activado_at',
        'creado_por',
        'activado_por',
        'categorias_nuevas',
    ];

    protected $casts = [
        'inicia_el' => 'date',
        'activado_at' => 'datetime',
        'categorias_nuevas' => 'array',
    ];

    public function programadas(): HasMany
    {
        return $this->hasMany(PromesaProgramada::class, 'corte_id');
    }

    /** Todavia no rige. */
    public function scopeProgramados(Builder $query): Builder
    {
        return $query->whereNull('activado_at');
    }

    /** Ya entraron en vigor (el vigente y los anteriores). */
    public function scopeActivados(Builder $query): Builder
    {
        return $query->whereNotNull('activado_at');
    }

    public function estaProgramado(): bool
    {
        return $this->activado_at === null;
    }

    /** Su fecha de inicio ya llego (o es hoy). */
    public function yaDebeRegir(): bool
    {
        return CarbonImmutable::parse($this->inicia_el)->startOfDay()->lte(CarbonImmutable::today());
    }

    /** Dias que faltan para que entre en vigor (0 si ya llego la fecha). */
    public function diasParaIniciar(): int
    {
        $inicio = CarbonImmutable::parse($this->inicia_el)->startOfDay();
        $hoy = CarbonImmutable::today();

        return $inicio->lte($hoy) ? 0 : (int) $hoy->diffInDays($inicio);
    }
}

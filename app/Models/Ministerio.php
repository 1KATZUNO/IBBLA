<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ministerio extends Model
{
    use BelongsToTenant;

    protected $table = 'ministerios';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'slug',
        'color',
        'descripcion',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'persona_ministerio')
            ->withPivot(['desde', 'hasta', 'es_lider'])
            ->withTimestamps();
    }

    public function personasActivas(): BelongsToMany
    {
        return $this->personas()->wherePivotNull('hasta');
    }

    public function lideres(): BelongsToMany
    {
        return $this->personas()->wherePivot('es_lider', true);
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    public function scopeOrdenados(Builder $q): Builder
    {
        return $q->orderBy('orden')->orderBy('nombre');
    }
}

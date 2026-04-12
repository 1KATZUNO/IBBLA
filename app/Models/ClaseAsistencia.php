<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaseAsistencia extends Model
{
    use BelongsToTenant;

    protected $table = 'clases_asistencia';

    protected $fillable = [
        'nombre',
        'slug',
        'color',
        'orden',
        'activa',
        'tiene_maestros',
        'tenant_id',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'tiene_maestros' => 'boolean',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(AsistenciaClaseDetalle::class);
    }

    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'clase_persona')
            ->withPivot('es_maestro')
            ->withTimestamps();
    }

    public function maestros(): BelongsToMany
    {
        return $this->personas()->wherePivot('es_maestro', true);
    }

    public function estudiantes(): BelongsToMany
    {
        return $this->personas()->wherePivot('es_maestro', false);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query->orderBy('orden');
    }

    /**
     * Excluye la capilla - solo clases de edades (regulares).
     */
    public function scopeRegulares(Builder $query): Builder
    {
        return $query->where('slug', '!=', 'capilla');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaseAsistencia extends Model
{
    protected $table = 'clases_asistencia';

    protected $fillable = [
        'nombre',
        'slug',
        'color',
        'orden',
        'activa',
        'tiene_maestros',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'tiene_maestros' => 'boolean',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(AsistenciaClaseDetalle::class);
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

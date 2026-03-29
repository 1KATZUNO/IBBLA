<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistroExtraTipo extends Model
{
    protected $table = 'registro_extra_tipos';

    protected $fillable = [
        'nombre',
        'slug',
        'color',
        'subcampos',
        'orden',
        'activo',
    ];

    protected $casts = [
        'subcampos' => 'array',
        'activo' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(AsistenciaRegistroExtra::class);
    }
}

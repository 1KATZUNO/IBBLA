<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistroExtraTipo extends Model
{
    use BelongsToTenant;

    protected $table = 'registro_extra_tipos';

    protected $fillable = [
        'nombre',
        'slug',
        'color',
        'subcampos',
        'orden',
        'activo',
        'tenant_id',
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

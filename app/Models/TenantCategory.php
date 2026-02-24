<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCategory extends Model
{
    protected $fillable = [
        'tenant_id', 'nombre', 'slug', 'tipo',
        'excluir_de_promesas', 'es_ofrenda_suelta',
        'icono', 'color', 'orden', 'activa',
    ];

    protected function casts(): array
    {
        return [
            'excluir_de_promesas' => 'boolean',
            'es_ofrenda_suelta' => 'boolean',
            'activa' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

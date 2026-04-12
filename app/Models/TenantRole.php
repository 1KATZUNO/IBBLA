<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantRole extends Model
{
    protected $fillable = [
        'tenant_id', 'nombre', 'slug', 'permisos', 'es_default', 'orden',
    ];

    protected function casts(): array
    {
        return [
            'permisos' => 'array',
            'es_default' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $permission): bool
    {
        return ! empty($this->permisos[$permission]);
    }
}

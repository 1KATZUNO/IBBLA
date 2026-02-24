<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Persona extends Model
{
    use BelongsToTenant;
    protected $table = 'personas';

    protected $fillable = [
        'nombre',
        'telefono',
        'correo',
        'password',
        'user_id',
        'activo',
        'notas',
        'tenant_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $hidden = [
        'password',
    ];

    public function sobres(): HasMany
    {
        return $this->hasMany(Sobre::class);
    }

    public function promesas(): HasMany
    {
        return $this->hasMany(Promesa::class);
    }

    public function compromisos(): HasMany
    {
        return $this->hasMany(Compromiso::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

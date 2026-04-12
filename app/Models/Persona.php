<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persona extends Model
{
    use BelongsToTenant;

    protected $table = 'personas';

    protected $fillable = [
        'nombre',
        'telefono',
        'correo',
        'fecha_nacimiento',
        'pin',
        'password',
        'user_id',
        'activo',
        'notas',
        'tenant_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_nacimiento' => 'date',
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

    public function clasesAsistencia(): BelongsToMany
    {
        return $this->belongsToMany(ClaseAsistencia::class, 'clase_persona')
            ->withPivot('es_maestro')
            ->withTimestamps();
    }

    public function esMaestroEn($claseId): bool
    {
        return $this->clasesAsistencia()
            ->wherePivot('clase_asistencia_id', $claseId)
            ->wherePivot('es_maestro', true)
            ->exists();
    }
}

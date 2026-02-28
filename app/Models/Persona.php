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
        'fecha_nacimiento',
        'pin',
        'clase_asistencia_id',
        'es_maestro',
        'password',
        'user_id',
        'activo',
        'notas',
        'tenant_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'es_maestro' => 'boolean',
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

    public function claseAsistencia(): BelongsTo
    {
        return $this->belongsTo(ClaseAsistencia::class);
    }
}

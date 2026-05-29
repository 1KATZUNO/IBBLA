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
        'direccion',
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

    public function ministerios(): BelongsToMany
    {
        return $this->belongsToMany(Ministerio::class, 'persona_ministerio')
            ->withPivot(['desde', 'hasta', 'es_lider'])
            ->withTimestamps();
    }

    /**
     * Ministerios donde la persona está actualmente activa (pivot.hasta is null).
     */
    public function ministeriosActivos(): BelongsToMany
    {
        return $this->ministerios()->wherePivotNull('hasta');
    }

    public function clasesAsistencia(): BelongsToMany
    {
        return $this->belongsToMany(ClaseAsistencia::class, 'clase_persona')
            ->withPivot(['es_maestro', 'tipo', 'convertida_de_visita_at', 'notas_clase'])
            ->withTimestamps();
    }

    public function esMaestroEn($claseId): bool
    {
        return $this->clasesAsistencia()
            ->wherePivot('clase_asistencia_id', $claseId)
            ->wherePivot('es_maestro', true)
            ->exists();
    }

    public function asistenciasIndividuales(): HasMany
    {
        return $this->hasMany(AsistenciaPersonaCulto::class);
    }

    public function visitacionesPastorales(): HasMany
    {
        return $this->hasMany(VisitacionPastoral::class);
    }
}

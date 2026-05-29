<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asistencia individual de una persona a un culto en una clase específica.
 * Complementa el sistema agregado (tabla `asistencia` con totales H/M).
 */
class AsistenciaPersonaCulto extends Model
{
    use BelongsToTenant;

    protected $table = 'asistencia_persona_culto';

    protected $fillable = [
        'persona_id',
        'clase_asistencia_id',
        'culto_id',
        'presente',
        'marcada_por_user_id',
        'tenant_id',
    ];

    protected $casts = [
        'presente' => 'boolean',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function claseAsistencia(): BelongsTo
    {
        return $this->belongsTo(ClaseAsistencia::class);
    }

    public function culto(): BelongsTo
    {
        return $this->belongsTo(Culto::class);
    }

    public function marcadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marcada_por_user_id');
    }
}

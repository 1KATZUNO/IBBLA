<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Visitación pastoral: registro de cuando la maestra/líder visita a un
 * miembro de la clase fuera del culto, organizado por mes y semana.
 */
class VisitacionPastoral extends Model
{
    use BelongsToTenant;

    protected $table = 'visitaciones_pastorales';

    protected $fillable = [
        'persona_id',
        'clase_asistencia_id',
        'año',
        'mes',
        'semana',
        'fecha',
        'notas',
        'registrada_por_user_id',
        'tenant_id',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function claseAsistencia(): BelongsTo
    {
        return $this->belongsTo(ClaseAsistencia::class);
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por_user_id');
    }
}

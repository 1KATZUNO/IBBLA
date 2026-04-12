<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroEspecial extends Model
{
    protected $table = 'registros_especiales';

    protected $fillable = [
        'asistencia_id',
        'tipo',
        'nombre',
        'genero',
        'edad',
        'telefono',
        'fecha_nacimiento',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    public function asistencia(): BelongsTo
    {
        return $this->belongsTo(Asistencia::class);
    }
}

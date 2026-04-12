<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsistenciaClaseDetalle extends Model
{
    protected $table = 'asistencia_clase_detalle';

    protected $fillable = [
        'asistencia_id',
        'clase_asistencia_id',
        'hombres',
        'mujeres',
        'maestros_hombres',
        'maestros_mujeres',
        'maestros_ids',
        'estudiantes_presentes_ids',
    ];

    protected $casts = [
        'maestros_ids' => 'array',
        'estudiantes_presentes_ids' => 'array',
    ];

    public function asistencia(): BelongsTo
    {
        return $this->belongsTo(Asistencia::class);
    }

    public function claseAsistencia(): BelongsTo
    {
        return $this->belongsTo(ClaseAsistencia::class);
    }

    public function getTotal(): int
    {
        return $this->hombres + $this->mujeres + $this->maestros_hombres + $this->maestros_mujeres;
    }

    public function getTotalAlumnos(): int
    {
        return $this->hombres + $this->mujeres;
    }

    public function getTotalMaestros(): int
    {
        return $this->maestros_hombres + $this->maestros_mujeres;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    protected $table = 'asistencia';

    protected $fillable = [
        'culto_id',
        'chapel_adultos_hombres',
        'chapel_adultos_mujeres',
        'chapel_adultos_mayores_hombres',
        'chapel_adultos_mayores_mujeres',
        'chapel_jovenes_masculinos',
        'chapel_jovenes_femeninas',
        'chapel_maestros_hombres',
        'chapel_maestros_mujeres',
        'clase_0_1_hombres',
        'clase_0_1_mujeres',
        'clase_0_1_maestros_hombres',
        'clase_0_1_maestros_mujeres',
        'clase_2_6_hombres',
        'clase_2_6_mujeres',
        'clase_2_6_maestros_hombres',
        'clase_2_6_maestros_mujeres',
        'clase_7_8_hombres',
        'clase_7_8_mujeres',
        'clase_7_8_maestros_hombres',
        'clase_7_8_maestros_mujeres',
        'clase_9_11_hombres',
        'clase_9_11_mujeres',
        'clase_9_11_maestros_hombres',
        'clase_9_11_maestros_mujeres',
        'total_asistencia',
        'salvos_adulto_hombre',
        'salvos_adulto_mujer',
        'salvos_joven_hombre',
        'salvos_joven_mujer',
        'salvos_nino',
        'salvos_nina',
        'bautismos_adulto_hombre',
        'bautismos_adulto_mujer',
        'bautismos_joven_hombre',
        'bautismos_joven_mujer',
        'bautismos_nino',
        'bautismos_nina',
        'visitas_adulto_hombre',
        'visitas_adulto_mujer',
        'visitas_joven_hombre',
        'visitas_joven_mujer',
        'visitas_nino',
        'visitas_nina',
        'cerrado',
        'cerrado_at',
        'cerrado_por',
    ];

    protected $casts = [
        'cerrado' => 'boolean',
        'cerrado_at' => 'datetime',
    ];

    public function culto(): BelongsTo
    {
        return $this->belongsTo(Culto::class);
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }
}

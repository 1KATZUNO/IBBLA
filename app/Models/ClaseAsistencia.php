<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaseAsistencia extends Model
{
    protected $table = 'clases_asistencia';

    protected $fillable = [
        'nombre',
        'slug',
        'color',
        'orden',
        'activa',
        'tiene_maestros',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'tiene_maestros' => 'boolean',
    ];
}

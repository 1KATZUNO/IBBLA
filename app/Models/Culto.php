<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class Culto extends Model
{
    protected $table = 'cultos';

    protected $fillable = [
        'fecha',
        'hora',
        'tipo_culto',
        'notas',
        'firma_tesorero',
        'firmas_tesoreros',
        'firma_pastor',
        'firma_pastor_imagen',
        'firmas_tesoreros_imagenes',
        'cerrado',
        'cerrado_at',
        'cerrado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora' => 'datetime',
        'cerrado' => 'boolean',
        'cerrado_at' => 'datetime',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'hora' => 'datetime',
            'cerrado' => 'boolean',
            'cerrado_at' => 'datetime',
            'firmas_tesoreros' => 'array',
            'firmas_tesoreros_imagenes' => 'array',
        ];
    }

    protected static function booted()
    {
        static::retrieved(function ($culto) {
            if ($culto->fecha instanceof \DateTimeInterface) {
                $culto->fecha = Carbon::parse($culto->fecha);
            }
        });
    }

    public function sobres(): HasMany
    {
        return $this->hasMany(Sobre::class);
    }

    public function ofrendasSueltas(): HasMany
    {
        return $this->hasMany(OfrendaSuelta::class);
    }

    public function egresos(): HasMany
    {
        return $this->hasMany(Egreso::class);
    }

    public function asistencia(): HasOne
    {
        return $this->hasOne(Asistencia::class);
    }

    public function totales(): HasOne
    {
        return $this->hasOne(TotalesCulto::class);
    }

    public function cerradoPor()
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }
}

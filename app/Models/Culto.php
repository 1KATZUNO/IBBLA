<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Culto extends Model
{
    use BelongsToTenant;

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
        'tipo_cambio_venta',
        'tenant_id',
        'service_type_id',
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

    public function serviceType()
    {
        return $this->belongsTo(TenantServiceType::class, 'service_type_id');
    }

    /**
     * Nombre del tipo de culto (dinamico o legacy).
     */
    public function getTipoNombreAttribute(): string
    {
        if ($this->serviceType) {
            return $this->serviceType->nombre;
        }
        // Fallback para datos legacy con ENUM
        $map = [
            'domingo' => 'Domingo AM',
            'domingo_pm' => 'Domingo PM',
            'miércoles' => 'Miercoles',
            'miercoles' => 'Miercoles',
            'sábado' => 'Sabado',
            'sabado' => 'Sabado',
            'especial' => 'Especial',
        ];

        return $map[$this->tipo_culto] ?? ucfirst($this->tipo_culto ?? '');
    }
}

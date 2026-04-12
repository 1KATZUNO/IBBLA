<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asistencia extends Model
{
    use BelongsToTenant;

    protected $table = 'asistencia';

    protected $fillable = [
        'culto_id',
        'tenant_id',
        'chapel_adultos_hombres',
        'chapel_adultos_mujeres',
        'chapel_adultos_mayores_hombres',
        'chapel_adultos_mayores_mujeres',
        'chapel_jovenes_masculinos',
        'chapel_jovenes_femeninas',
        'chapel_maestros_hombres',
        'chapel_maestros_mujeres',
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

    public function detallesClases(): HasMany
    {
        return $this->hasMany(AsistenciaClaseDetalle::class);
    }

    public function registrosEspeciales(): HasMany
    {
        return $this->hasMany(RegistroEspecial::class);
    }

    public function registrosExtra(): HasMany
    {
        return $this->hasMany(AsistenciaRegistroExtra::class);
    }

    public function getRegistroExtra(string $tipoSlug): ?AsistenciaRegistroExtra
    {
        return $this->registrosExtra->first(function ($registro) use ($tipoSlug) {
            return $registro->tipo && $registro->tipo->slug === $tipoSlug;
        });
    }

    public function detalleClase(string $slug): ?AsistenciaClaseDetalle
    {
        return $this->detallesClases->first(function ($detalle) use ($slug) {
            return $detalle->claseAsistencia && $detalle->claseAsistencia->slug === $slug;
        });
    }

    public function getTotalCapilla(): int
    {
        return ($this->chapel_adultos_hombres ?? 0) +
               ($this->chapel_adultos_mujeres ?? 0) +
               ($this->chapel_jovenes_masculinos ?? 0) +
               ($this->chapel_jovenes_femeninas ?? 0);
    }

    public function getTotalClases(): int
    {
        return $this->detallesClases->sum(function ($detalle) {
            return $detalle->getTotal();
        });
    }

    public function getTotalNinos(): int
    {
        return $this->detallesClases->sum(function ($detalle) {
            return $detalle->getTotalAlumnos();
        });
    }

    public function getTotalMaestros(): int
    {
        return $this->detallesClases->sum(function ($detalle) {
            return $detalle->getTotalMaestros();
        });
    }

    public function getTotalClasesHombres(): int
    {
        return $this->detallesClases->sum(function ($detalle) {
            return $detalle->hombres + $detalle->maestros_hombres;
        });
    }

    public function getTotalClasesMujeres(): int
    {
        return $this->detallesClases->sum(function ($detalle) {
            return $detalle->mujeres + $detalle->maestros_mujeres;
        });
    }

    public function getTotalHombres(): int
    {
        return ($this->chapel_adultos_hombres ?? 0) +
               ($this->chapel_jovenes_masculinos ?? 0) +
               $this->getTotalClasesHombres();
    }

    public function getTotalMujeres(): int
    {
        return ($this->chapel_adultos_mujeres ?? 0) +
               ($this->chapel_jovenes_femeninas ?? 0) +
               $this->getTotalClasesMujeres();
    }

    public function getTotalSalvos(): int
    {
        return ($this->salvos_adulto_hombre ?? 0) + ($this->salvos_adulto_mujer ?? 0) +
               ($this->salvos_joven_hombre ?? 0) + ($this->salvos_joven_mujer ?? 0) +
               ($this->salvos_nino ?? 0) + ($this->salvos_nina ?? 0);
    }

    public function getTotalBautismos(): int
    {
        return ($this->bautismos_adulto_hombre ?? 0) + ($this->bautismos_adulto_mujer ?? 0) +
               ($this->bautismos_joven_hombre ?? 0) + ($this->bautismos_joven_mujer ?? 0) +
               ($this->bautismos_nino ?? 0) + ($this->bautismos_nina ?? 0);
    }

    public function getTotalVisitas(): int
    {
        return ($this->visitas_adulto_hombre ?? 0) + ($this->visitas_adulto_mujer ?? 0) +
               ($this->visitas_joven_hombre ?? 0) + ($this->visitas_joven_mujer ?? 0) +
               ($this->visitas_nino ?? 0) + ($this->visitas_nina ?? 0);
    }
}

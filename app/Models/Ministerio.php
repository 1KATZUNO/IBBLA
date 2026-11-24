<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Ministerio extends Model
{
    use BelongsToTenant;

    protected $table = 'ministerios';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'slug',
        'color',
        'descripcion',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected static function booted(): void
    {
        // El slug es solo un identificador interno: quien agrega un ministerio a mano
        // no debería tener que inventarlo. Si no viene, se arma desde el nombre y se
        // garantiza que sea único dentro de la iglesia (unique tenant_id + slug).
        // El orden, si no viene, deja el ministerio nuevo al final de la lista.
        // BelongsToTenant ya registró su hook de creating (pone tenant_id), así que
        // aquí el tenant_id ya está resuelto.
        static::creating(function (Ministerio $ministerio) {
            if (blank($ministerio->slug)) {
                $ministerio->slug = static::slugUnico($ministerio->nombre, $ministerio->tenant_id);
            }
            if ($ministerio->orden === null) {
                $ministerio->orden = ((int) static::withoutGlobalScopes()
                    ->where('tenant_id', $ministerio->tenant_id)
                    ->max('orden')) + 1;
            }
        });
    }

    /**
     * Slug a partir del nombre, con sufijo -2, -3... si ya existe en la iglesia.
     * `$ignorarId` permite regenerar sin chocar consigo mismo.
     */
    public static function slugUnico(string $nombre, ?int $tenantId, ?int $ignorarId = null): string
    {
        // La columna es de 80: se deja margen para el sufijo numérico.
        $base = Str::limit(Str::slug($nombre), 70, '') ?: 'ministerio';
        $slug = $base;
        $n = 2;

        while (static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($ignorarId, fn (Builder $q) => $q->where('id', '!=', $ignorarId))
            ->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'persona_ministerio')
            ->withPivot(['desde', 'hasta', 'es_lider'])
            ->withTimestamps();
    }

    public function personasActivas(): BelongsToMany
    {
        return $this->personas()->wherePivotNull('hasta');
    }

    public function lideres(): BelongsToMany
    {
        return $this->personas()->wherePivot('es_lider', true);
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    public function scopeOrdenados(Builder $q): Builder
    {
        return $q->orderBy('orden')->orderBy('nombre');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class TenantServiceType extends Model
{
    protected $fillable = [
        'tenant_id', 'nombre', 'slug', 'orden', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Tipos de culto activos de la iglesia del usuario, en su orden. Si la iglesia todavia no
     * tiene tipos propios se devuelven los de siempre (datos anteriores a los tipos por iglesia),
     * como objetos con id null, slug, nombre y tenant_id.
     */
    public static function disponiblesPara(?User $user): Collection
    {
        if ($user && $user->tenant_id) {
            $tipos = static::where('tenant_id', $user->tenant_id)
                ->where('activo', true)
                ->orderBy('orden')
                ->get();

            if ($tipos->isNotEmpty()) {
                return $tipos;
            }
        }

        $tenantId = $user->tenant_id ?? null;

        return collect([
            (object) ['id' => null, 'slug' => 'domingo', 'nombre' => 'Domingo AM', 'tenant_id' => $tenantId],
            (object) ['id' => null, 'slug' => 'domingo_pm', 'nombre' => 'Domingo PM', 'tenant_id' => $tenantId],
            (object) ['id' => null, 'slug' => 'miercoles', 'nombre' => 'Miércoles', 'tenant_id' => $tenantId],
            (object) ['id' => null, 'slug' => 'sabado', 'nombre' => 'Sábado', 'tenant_id' => $tenantId],
            (object) ['id' => null, 'slug' => 'especial', 'nombre' => 'Especial', 'tenant_id' => $tenantId],
        ]);
    }
}

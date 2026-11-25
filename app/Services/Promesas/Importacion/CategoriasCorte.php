<?php

namespace App\Services\Promesas\Importacion;

use App\Models\CortePromesas;
use App\Models\TenantCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Categorias con las que se puede prometer en un corte: las de promesa activas de la iglesia (lo mismo que
 * tenant_categories(['excluir_de_promesas' => false])) mas las que se crearon inactivas para este corte
 * (categorias_nuevas), que el helper no devuelve porque todavia no rigen.
 */
final class CategoriasCorte
{
    /** @return Collection<int, TenantCategory> */
    public static function de(CortePromesas $corte): Collection
    {
        $nuevas = array_map('intval', $corte->categorias_nuevas ?? []);

        return TenantCategory::query()
            ->where('tenant_id', $corte->tenant_id)
            ->where(function ($q) use ($nuevas) {
                $q->where(fn ($q) => $q->where('activa', true)->where('excluir_de_promesas', false));
                if ($nuevas !== []) {
                    $q->orWhereIn('id', $nuevas);
                }
            })
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    /** Crea una categoria de promesa inactiva para el corte y la anota en categorias_nuevas (sin guardar el corte). */
    public static function crearInactiva(CortePromesas $corte, string $nombre): TenantCategory
    {
        $base = Str::slug(Normalizador::clave($nombre), '_') ?: 'categoria';
        $base = mb_substr($base, 0, 40);
        $slug = $base;
        $n = 2;
        while (TenantCategory::where('tenant_id', $corte->tenant_id)->where('slug', $slug)->exists()) {
            $slug = $base.'_'.$n++;
        }

        $categoria = TenantCategory::create([
            'tenant_id' => $corte->tenant_id,
            'nombre' => mb_substr($nombre, 0, 100),
            'slug' => $slug,
            'tipo' => 'ambos',
            'excluir_de_promesas' => false,
            'es_ofrenda_suelta' => false,
            'color' => '#6b7280',
            'orden' => (int) TenantCategory::where('tenant_id', $corte->tenant_id)->max('orden') + 1,
            'activa' => false,
        ]);

        $corte->categorias_nuevas = array_values(array_unique(array_merge(array_map('intval', $corte->categorias_nuevas ?? []), [$categoria->id])));

        return $categoria;
    }
}

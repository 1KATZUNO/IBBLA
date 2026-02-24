<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TotalesCulto extends Model
{
    protected $table = 'totales_culto';

    protected $fillable = [
        'culto_id',
        'total_diezmo',
        'total_ofrenda_especial',
        'total_misiones',
        'total_seminario',
        'total_campa',
        'total_prestamo',
        'total_construccion',
        'total_micro',
        'total_suelto',
        'total_egresos',
        'total_general',
        'cantidad_sobres',
        'cantidad_transferencias',
        'totales_por_categoria',
        'notas',
    ];

    protected $casts = [
        'total_diezmo' => 'decimal:2',
        'total_ofrenda_especial' => 'decimal:2',
        'total_misiones' => 'decimal:2',
        'total_seminario' => 'decimal:2',
        'total_campa' => 'decimal:2',
        'total_prestamo' => 'decimal:2',
        'total_construccion' => 'decimal:2',
        'total_micro' => 'decimal:2',
        'total_suelto' => 'decimal:2',
        'total_egresos' => 'decimal:2',
        'total_general' => 'decimal:2',
        'totales_por_categoria' => 'array',
    ];

    // Legacy column mapping for backward compatibility
    private const LEGACY_COLUMNS = [
        'diezmo' => 'total_diezmo',
        'ofrenda_especial' => 'total_ofrenda_especial',
        'misiones' => 'total_misiones',
        'seminario' => 'total_seminario',
        'campa' => 'total_campa',
        'prestamo' => 'total_prestamo',
        'construccion' => 'total_construccion',
        'micro' => 'total_micro',
    ];

    /**
     * Get the total for a specific category slug.
     * Reads from JSON first, falls back to legacy column.
     */
    public function getCategoryTotal(string $slug): float
    {
        $json = $this->totales_por_categoria;
        if (is_array($json) && isset($json[$slug])) {
            return (float) $json[$slug];
        }

        $legacyColumn = self::LEGACY_COLUMNS[$slug] ?? null;
        if ($legacyColumn && isset($this->attributes[$legacyColumn])) {
            return (float) $this->attributes[$legacyColumn];
        }

        return 0.0;
    }

    public function culto(): BelongsTo
    {
        return $this->belongsTo(Culto::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que una persona promete en un corte de promesas todavia no activado. Mismos campos que
 * `promesas` (categoria por slug, monto, moneda, frecuencia). Una persona puede tener varias en la
 * misma categoria, igual que hoy. Ningun reporte lee esta tabla: solo el apartado Promesas futuras
 * y la activacion del corte.
 */
class PromesaProgramada extends Model
{
    protected $table = 'promesas_programadas';

    protected $fillable = [
        'corte_id',
        'persona_id',
        'categoria',
        'monto',
        'moneda',
        'frecuencia',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    /** "₡5.000", "₡230,77" o "$25" para las pantallas de Promesas futuras. */
    public static function formatoMonto(float $monto, string $moneda = 'CRC'): string
    {
        $decimales = abs($monto - round($monto)) >= 0.005 ? 2 : 0;

        return ($moneda === 'USD' ? '$' : '₡').number_format($monto, $decimales, ',', '.');
    }

    /** Frecuencia en corto para las tablas ("sem.", "quinc.", "mens."). */
    public static function frecuenciaCorta(string $frecuencia): string
    {
        return ['semanal' => 'sem.', 'quincenal' => 'quinc.', 'mensual' => 'mens.'][$frecuencia] ?? $frecuencia;
    }

    public function corte(): BelongsTo
    {
        return $this->belongsTo(CortePromesas::class, 'corte_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}

<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Promesas que rigieron hasta que un corte las reemplazo. Se escriben UNA vez, al activarse el
 * corte, y solo las lee el motor de reportes para calcular lo esperado de los meses anteriores a
 * ese corte: asi un reporte de 2026 da lo mismo antes y despues del corte de 2027.
 *
 * `vigente_desde` null = desde siempre. `vigente_hasta` = vispera del corte que las reemplazo.
 */
class PromesaHistorial extends Model
{
    use BelongsToTenant;

    protected $table = 'promesas_historial';

    protected $fillable = [
        'tenant_id',
        'corte_id',
        'persona_id',
        'categoria',
        'monto',
        'moneda',
        'frecuencia',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function corte(): BelongsTo
    {
        return $this->belongsTo(CortePromesas::class, 'corte_id');
    }
}

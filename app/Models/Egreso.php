<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Egreso extends Model
{
    protected $table = 'egresos';

    protected $fillable = [
        'culto_id',
        'monto',
        'moneda',
        'tipo_cambio_venta',
        'descripcion',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'tipo_cambio_venta' => 'decimal:4',
    ];

    public function culto(): BelongsTo
    {
        return $this->belongsTo(Culto::class);
    }

    public function esUsd(): bool
    {
        return $this->moneda === 'USD';
    }

    public function getSimboloMonedaAttribute(): string
    {
        return $this->moneda === 'USD' ? '$' : '₡';
    }

    public function getMontoCrcAttribute(): float
    {
        if ($this->moneda !== 'USD') {
            return (float) $this->monto;
        }

        $tc = (float) ($this->tipo_cambio_venta ?? 0);

        return $tc > 0 ? round((float) $this->monto * $tc, 2) : (float) $this->monto;
    }
}

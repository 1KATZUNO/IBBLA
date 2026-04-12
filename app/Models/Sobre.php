<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sobre extends Model
{
    protected $table = 'sobres';

    protected $fillable = [
        'culto_id',
        'persona_id',
        'numero_sobre',
        'metodo_pago',
        'comprobante_numero',
        'total_declarado',
        'moneda',
        'tipo_cambio_venta',
        'tipo_cambio_id',
        'notas',
    ];

    protected $casts = [
        'total_declarado' => 'decimal:2',
        'tipo_cambio_venta' => 'decimal:4',
    ];

    public function culto(): BelongsTo
    {
        return $this->belongsTo(Culto::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(SobreDetalle::class);
    }

    public function tipoCambio(): BelongsTo
    {
        return $this->belongsTo(TipoCambio::class);
    }

    /**
     * Retorna true si el sobre es en dólares.
     */
    public function esUsd(): bool
    {
        return $this->moneda === 'USD';
    }

    /**
     * Retorna el símbolo de la moneda.
     */
    public function getSimboloMonedaAttribute(): string
    {
        return $this->moneda === 'USD' ? '$' : '₡';
    }

    /**
     * Retorna el total declarado convertido a colones.
     * Si ya es CRC, retorna directo. Si es USD, multiplica por tipo de cambio.
     */
    public function getTotalDeclaradoCrcAttribute(): float
    {
        if ($this->moneda !== 'USD') {
            return (float) $this->total_declarado;
        }

        $tc = (float) ($this->tipo_cambio_venta ?? 0);

        return $tc > 0 ? round((float) $this->total_declarado * $tc, 2) : (float) $this->total_declarado;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sobre) {
            if (! $sobre->numero_sobre) {
                $maxNumero = static::where('culto_id', $sobre->culto_id)
                    ->max('numero_sobre');
                $sobre->numero_sobre = $maxNumero ? $maxNumero + 1 : 1;
            }
        });
    }
}

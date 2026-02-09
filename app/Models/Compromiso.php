<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Compromiso extends Model
{
    protected $fillable = [
        'persona_id',
        'categoria',
        'año',
        'mes',
        'monto_prometido',
        'monto_dado',
        'saldo_anterior',
        'saldo_actual',
    ];

    protected $casts = [
        'monto_prometido' => 'decimal:2',
        'monto_dado' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_actual' => 'decimal:2',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Calcula el saldo actual basado en monto prometido, dado y saldo anterior
     * Saldo positivo = a favor de la persona (dio de más)
     * Saldo negativo = debe
     */
    public function calcularSaldo(): float
    {
        return ($this->monto_dado + $this->saldo_anterior) - $this->monto_prometido;
    }

    /**
     * Actualiza el saldo actual
     */
    public function actualizarSaldo(): void
    {
        $this->saldo_actual = $this->calcularSaldo();
        $this->save();
    }
}

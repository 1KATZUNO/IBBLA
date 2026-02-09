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
        'notas',
    ];

    protected $casts = [
        'total_declarado' => 'decimal:2',
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sobre) {
            if (!$sobre->numero_sobre) {
                $maxNumero = static::where('culto_id', $sobre->culto_id)
                    ->max('numero_sobre');
                $sobre->numero_sobre = $maxNumero ? $maxNumero + 1 : 1;
            }
        });
    }
}

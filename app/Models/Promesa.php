<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promesa extends Model
{
    protected $table = 'promesas';

    protected $fillable = [
        'persona_id',
        'categoria',
        'monto',
        'frecuencia',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}

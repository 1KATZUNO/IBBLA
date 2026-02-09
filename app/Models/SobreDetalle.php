<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SobreDetalle extends Model
{
    protected $table = 'sobre_detalles';

    protected $fillable = [
        'sobre_id',
        'categoria',
        'monto',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function sobre(): BelongsTo
    {
        return $this->belongsTo(Sobre::class);
    }
}

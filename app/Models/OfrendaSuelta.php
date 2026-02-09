<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfrendaSuelta extends Model
{
    protected $table = 'ofrenda_suelta';

    protected $fillable = [
        'culto_id',
        'monto',
        'metodo_pago',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function culto(): BelongsTo
    {
        return $this->belongsTo(Culto::class);
    }
}

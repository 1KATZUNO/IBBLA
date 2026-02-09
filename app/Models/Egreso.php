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
        'descripcion',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function culto(): BelongsTo
    {
        return $this->belongsTo(Culto::class);
    }
}

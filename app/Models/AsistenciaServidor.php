<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsistenciaServidor extends Model
{
    use BelongsToTenant;

    protected $table = 'asistencia_servidores';

    protected $fillable = [
        'user_id',
        'culto_id',
        'tenant_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function culto(): BelongsTo
    {
        return $this->belongsTo(Culto::class);
    }
}

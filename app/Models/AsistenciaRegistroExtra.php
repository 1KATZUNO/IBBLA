<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsistenciaRegistroExtra extends Model
{
    protected $table = 'asistencia_registro_extra';

    protected $fillable = [
        'asistencia_id',
        'registro_extra_tipo_id',
        'valores',
    ];

    protected $casts = [
        'valores' => 'array',
    ];

    public function asistencia(): BelongsTo
    {
        return $this->belongsTo(Asistencia::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(RegistroExtraTipo::class, 'registro_extra_tipo_id');
    }

    public function getValor(string $subcampo): int
    {
        return (int) ($this->valores[$subcampo] ?? 0);
    }

    public function getTotal(): int
    {
        return array_sum(array_map('intval', $this->valores ?? []));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TipoCambio extends Model
{
    use HasFactory;

    protected $table = 'tipo_cambios';

    protected $fillable = [
        'fecha',
        'compra',
        'venta',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'compra' => 'decimal:4',
            'venta' => 'decimal:4',
        ];
    }

    /**
     * Obtiene el tipo de cambio de hoy, o el más reciente disponible.
     */
    public static function hoy(): ?self
    {
        return static::where('fecha', Carbon::today()->toDateString())->first()
            ?? static::masReciente();
    }

    /**
     * Obtiene el tipo de cambio de una fecha específica,
     * o el más reciente anterior a esa fecha.
     */
    public static function deFecha(string|Carbon $fecha): ?self
    {
        $fecha = $fecha instanceof Carbon ? $fecha->toDateString() : $fecha;

        return static::where('fecha', $fecha)->first()
            ?? static::where('fecha', '<=', $fecha)
                ->orderByDesc('fecha')
                ->first();
    }

    /**
     * Retorna el tipo de cambio más reciente.
     */
    public static function masReciente(): ?self
    {
        return static::orderByDesc('fecha')->first();
    }

    /**
     * Convierte un monto de USD a CRC usando el tipo de cambio venta.
     */
    public function convertirUsdACrc(float $montoUsd): float
    {
        return round($montoUsd * (float) $this->venta, 2);
    }

    /**
     * Convierte un monto de CRC a USD usando el tipo de cambio compra.
     */
    public function convertirCrcAUsd(float $montoCrc): float
    {
        if ((float) $this->compra === 0.0) {
            return 0;
        }

        return round($montoCrc / (float) $this->compra, 2);
    }

    /**
     * Scope: más reciente primero.
     */
    public function scopeMasRecientePrimero($query)
    {
        return $query->orderByDesc('fecha');
    }
}

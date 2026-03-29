<?php

namespace Database\Seeders;

use App\Models\TipoCambio;
use Illuminate\Database\Seeder;

class TipoCambioSeeder extends Seeder
{
    /**
     * Inserta un tipo de cambio por defecto.
     *
     * Este registro sirve como tasa de respaldo (fallback) para que el sistema
     * funcione correctamente cuando aún no se han importado tipos de cambio
     * desde una fuente externa (BCCR, API, etc.).
     */
    public function run(): void
    {
        TipoCambio::updateOrCreate(
            ['fecha' => now()->toDateString()],
            [
                'compra' => 505.00,
                'venta' => 515.00,
                'source' => 'manual',
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use App\Models\RegistroExtraTipo;
use Illuminate\Database\Seeder;

class RegistroExtraTipoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'nombre' => 'Transmisión',
                'slug' => 'transmision',
                'color' => '#F59E0B',
                'subcampos' => ['miembros'],
                'orden' => 1,
                'activo' => true,
                'cuenta_en_asistencia' => true, // personas viendo online → suman al total
            ],
            [
                'nombre' => 'Vehículos',
                'slug' => 'vehiculos',
                'color' => '#6B7280',
                'subcampos' => ['autos', 'motos'],
                'orden' => 2,
                'activo' => true,
                'cuenta_en_asistencia' => false, // objetos, no personas
            ],
        ];

        foreach ($tipos as $tipo) {
            RegistroExtraTipo::updateOrCreate(
                ['slug' => $tipo['slug']],
                $tipo
            );
        }
    }
}

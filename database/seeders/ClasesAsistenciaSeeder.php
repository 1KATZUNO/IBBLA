<?php

namespace Database\Seeders;

use App\Models\ClaseAsistencia;
use Illuminate\Database\Seeder;

class ClasesAsistenciaSeeder extends Seeder
{
    public function run(): void
    {
        $clases = [
            [
                'nombre' => 'Capilla',
                'slug' => 'capilla',
                'color' => '#3b82f6', // blue-500
                'orden' => 0,
                'activa' => true,
                'tiene_maestros' => false,
            ],
            [
                'nombre' => 'Clase 0-1 Años',
                'slug' => 'clase_0_1',
                'color' => '#22c55e', // green-500
                'orden' => 1,
                'activa' => true,
                'tiene_maestros' => true,
            ],
            [
                'nombre' => 'Clase 2-6 Años',
                'slug' => 'clase_2_6',
                'color' => '#eab308', // yellow-500
                'orden' => 2,
                'activa' => true,
                'tiene_maestros' => true,
            ],
            [
                'nombre' => 'Clase 7-8 Años',
                'slug' => 'clase_7_8',
                'color' => '#a855f7', // purple-500
                'orden' => 3,
                'activa' => true,
                'tiene_maestros' => true,
            ],
            [
                'nombre' => 'Clase 9-11 Años',
                'slug' => 'clase_9_11',
                'color' => '#ef4444', // red-500
                'orden' => 4,
                'activa' => true,
                'tiene_maestros' => true,
            ],
        ];

        foreach ($clases as $clase) {
            ClaseAsistencia::updateOrCreate(
                ['slug' => $clase['slug']],
                $clase
            );
        }
    }
}

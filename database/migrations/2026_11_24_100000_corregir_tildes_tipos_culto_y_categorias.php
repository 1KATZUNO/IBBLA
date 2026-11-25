<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * QE-018: nombres sin tilde en los datos de cada iglesia. Los tipos de culto y las categorias se
 * enlazan por `slug` (cultos.tipo_culto, sobre_detalles.categoria, promesas.categoria,
 * compromisos.categoria, columnas total_<slug>), nunca por el nombre: cambiar el nombre visible es
 * seguro. Solo se tocan esos nombres EXACTOS con su slug; si una iglesia ya los renombro, no se pisan.
 */
return new class extends Migration
{
    private const CAMBIOS = [
        'tenant_service_types' => [
            'miercoles' => ['Miercoles', 'Miércoles'],
            'sabado' => ['Sabado', 'Sábado'],
        ],
        'tenant_categories' => [
            'construccion' => ['Construccion', 'Construcción'],
            'prestamo' => ['Prestamo', 'Préstamo'],
        ],
    ];

    public function up(): void
    {
        foreach (self::CAMBIOS as $tabla => $cambios) {
            foreach ($cambios as $slug => [$antes, $despues]) {
                DB::table($tabla)->where('slug', $slug)->where('nombre', $antes)->update(['nombre' => $despues]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::CAMBIOS as $tabla => $cambios) {
            foreach ($cambios as $slug => [$antes, $despues]) {
                DB::table($tabla)->where('slug', $slug)->where('nombre', $despues)->update(['nombre' => $antes]);
            }
        }
    }
};

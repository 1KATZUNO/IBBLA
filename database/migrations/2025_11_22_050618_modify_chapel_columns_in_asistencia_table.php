<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asistencia', function (Blueprint $table) {
            // Eliminar columnas antiguas de capilla
            $table->dropColumn([
                'chapel_hombres',
                'chapel_mujeres',
                'chapel_adultos_mayores',
                'chapel_adultos',
            ]);
            
            // Agregar nuevas columnas para capilla con categorías por género
            $table->integer('chapel_adultos_hombres')->default(0)->after('culto_id');
            $table->integer('chapel_adultos_mujeres')->default(0)->after('chapel_adultos_hombres');
            $table->integer('chapel_adultos_mayores_hombres')->default(0)->after('chapel_adultos_mujeres');
            $table->integer('chapel_adultos_mayores_mujeres')->default(0)->after('chapel_adultos_mayores_hombres');
            $table->integer('chapel_maestros_hombres')->default(0)->after('chapel_jovenes_femeninas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asistencia', function (Blueprint $table) {
            // Revertir: eliminar columnas nuevas
            $table->dropColumn([
                'chapel_adultos_hombres',
                'chapel_adultos_mujeres',
                'chapel_adultos_mayores_hombres',
                'chapel_adultos_mayores_mujeres',
                'chapel_maestros_hombres',
            ]);
            
            // Restaurar columnas antiguas
            $table->integer('chapel_hombres')->default(0);
            $table->integer('chapel_mujeres')->default(0);
            $table->integer('chapel_adultos_mayores')->default(0);
            $table->integer('chapel_adultos')->default(0);
        });
    }
};

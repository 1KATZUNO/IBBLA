<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * El cliente (IBBLA - Iglesia Bíblica Bautista en La Aurora) pidió eliminar
     * los campos de jóvenes en la sección Capilla: la captura de asistencia de
     * Capilla solo debe registrar Adultos Hombres y Adultos Mujeres.
     */
    public function up(): void
    {
        Schema::table('asistencia', function (Blueprint $table) {
            if (Schema::hasColumn('asistencia', 'chapel_jovenes_masculinos')) {
                $table->dropColumn('chapel_jovenes_masculinos');
            }
            if (Schema::hasColumn('asistencia', 'chapel_jovenes_femeninas')) {
                $table->dropColumn('chapel_jovenes_femeninas');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asistencia', function (Blueprint $table) {
            if (! Schema::hasColumn('asistencia', 'chapel_jovenes_masculinos')) {
                $table->integer('chapel_jovenes_masculinos')->default(0)->after('chapel_adultos_mayores_mujeres');
            }
            if (! Schema::hasColumn('asistencia', 'chapel_jovenes_femeninas')) {
                $table->integer('chapel_jovenes_femeninas')->default(0)->after('chapel_jovenes_masculinos');
            }
        });
    }
};

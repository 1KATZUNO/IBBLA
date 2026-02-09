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
            // Verificar si las columnas antiguas existen y renombrarlas
            if (Schema::hasColumn('asistencia', 'chapel_jovenes')) {
                $table->renameColumn('chapel_jovenes', 'chapel_jovenes_masculinos');
            }
            // Si no existe chapel_jovenes_masculinos, crearla
            if (!Schema::hasColumn('asistencia', 'chapel_jovenes_masculinos')) {
                $table->integer('chapel_jovenes_masculinos')->default(0)->after('chapel_adultos');
            }
            if (!Schema::hasColumn('asistencia', 'chapel_jovenes_femeninas')) {
                $table->integer('chapel_jovenes_femeninas')->default(0)->after('chapel_jovenes_masculinos');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asistencia', function (Blueprint $table) {
            if (Schema::hasColumn('asistencia', 'chapel_jovenes_femeninas')) {
                $table->dropColumn('chapel_jovenes_femeninas');
            }
            if (Schema::hasColumn('asistencia', 'chapel_jovenes_masculinos')) {
                $table->renameColumn('chapel_jovenes_masculinos', 'chapel_jovenes');
            }
        });
    }
};

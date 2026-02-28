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
        Schema::table('personas', function (Blueprint $table) {
            $table->foreignId('clase_asistencia_id')->nullable()->after('pin')
                  ->constrained('clases_asistencia')->nullOnDelete();
            $table->boolean('es_maestro')->default(false)->after('clase_asistencia_id');
        });

        Schema::table('asistencia_clase_detalle', function (Blueprint $table) {
            $table->json('maestros_ids')->nullable()->after('maestros_mujeres');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropForeign(['clase_asistencia_id']);
            $table->dropColumn(['clase_asistencia_id', 'es_maestro']);
        });

        Schema::table('asistencia_clase_detalle', function (Blueprint $table) {
            $table->dropColumn('maestros_ids');
        });
    }
};

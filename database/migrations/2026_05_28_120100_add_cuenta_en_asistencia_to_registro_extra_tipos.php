<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Añade flag para indicar si un tipo de RegistroExtra cuenta hacia el
     * total de asistencia del culto. Esto permite distinguir, por ejemplo,
     * que "Transmisión" (personas viendo online) sume al total, pero
     * "Vehículos" (autos/motos) no.
     */
    public function up(): void
    {
        Schema::table('registro_extra_tipos', function (Blueprint $table) {
            $table->boolean('cuenta_en_asistencia')->default(false)->after('activo');
        });

        // Por defecto, marcar Transmisión como sí cuenta (son personas).
        // Vehículos queda en false (son objetos).
        \DB::table('registro_extra_tipos')
            ->where('slug', 'transmision')
            ->update(['cuenta_en_asistencia' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registro_extra_tipos', function (Blueprint $table) {
            $table->dropColumn('cuenta_en_asistencia');
        });
    }
};

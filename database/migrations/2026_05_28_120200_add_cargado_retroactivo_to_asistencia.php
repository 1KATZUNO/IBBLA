<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Flag para distinguir asistencias cargadas retroactivamente (import
     * histórico) vs capturadas en vivo. Permite auditoría y trazabilidad.
     */
    public function up(): void
    {
        Schema::table('asistencia', function (Blueprint $table) {
            $table->boolean('cargado_retroactivo')->default(false)->after('cerrado_por');
            $table->timestamp('cargado_retroactivo_at')->nullable()->after('cargado_retroactivo');
            $table->foreignId('cargado_retroactivo_por')->nullable()->after('cargado_retroactivo_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asistencia', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cargado_retroactivo_por');
            $table->dropColumn(['cargado_retroactivo', 'cargado_retroactivo_at']);
        });
    }
};

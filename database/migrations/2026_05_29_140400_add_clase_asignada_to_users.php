<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Maestras pueden tener una clase asignada. Cuando entran a /app/clase,
     * la app las redirige directo a SU clase. Admin puede ver cualquier clase.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'clase_asistencia_id')) {
                $table->foreignId('clase_asistencia_id')
                    ->nullable()
                    ->after('tenant_role_id')
                    ->constrained('clases_asistencia')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clase_asistencia_id');
        });
    }
};

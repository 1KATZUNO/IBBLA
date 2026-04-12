<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create pivot table
        Schema::create('clase_persona', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->onDelete('cascade');
            $table->foreignId('clase_asistencia_id')->constrained('clases_asistencia')->onDelete('cascade');
            $table->boolean('es_maestro')->default(false);
            $table->timestamps();

            $table->unique(['persona_id', 'clase_asistencia_id']);
        });

        // 2. Migrate existing data from personas.clase_asistencia_id + es_maestro
        $personas = DB::table('personas')
            ->whereNotNull('clase_asistencia_id')
            ->get(['id', 'clase_asistencia_id', 'es_maestro']);

        foreach ($personas as $persona) {
            DB::table('clase_persona')->insert([
                'persona_id' => $persona->id,
                'clase_asistencia_id' => $persona->clase_asistencia_id,
                'es_maestro' => $persona->es_maestro ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Drop old columns from personas
        Schema::table('personas', function (Blueprint $table) {
            $table->dropForeign(['clase_asistencia_id']);
            $table->dropColumn(['clase_asistencia_id', 'es_maestro']);
        });
    }

    public function down(): void
    {
        // Restore old columns
        Schema::table('personas', function (Blueprint $table) {
            $table->foreignId('clase_asistencia_id')->nullable()->constrained('clases_asistencia')->nullOnDelete();
            $table->boolean('es_maestro')->default(false);
        });

        // Migrate data back
        $pivots = DB::table('clase_persona')->get();
        foreach ($pivots as $pivot) {
            DB::table('personas')
                ->where('id', $pivot->persona_id)
                ->update([
                    'clase_asistencia_id' => $pivot->clase_asistencia_id,
                    'es_maestro' => $pivot->es_maestro,
                ]);
        }

        Schema::dropIfExists('clase_persona');
    }
};

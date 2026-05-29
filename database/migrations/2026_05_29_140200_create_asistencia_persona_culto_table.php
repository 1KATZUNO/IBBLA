<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Asistencia individual: persona X estuvo presente en culto Y.
     * Esto complementa el modelo agregado del sistema (tabla `asistencia` que
     * guarda totales). Para clases tipo Horeb donde se marca persona por persona.
     */
    public function up(): void
    {
        Schema::create('asistencia_persona_culto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->foreignId('clase_asistencia_id')->constrained('clases_asistencia')->cascadeOnDelete();
            $table->foreignId('culto_id')->constrained('cultos')->cascadeOnDelete();
            $table->boolean('presente')->default(true);
            $table->foreignId('marcada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['persona_id', 'clase_asistencia_id', 'culto_id'], 'apc_unique');
            $table->index(['clase_asistencia_id', 'culto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencia_persona_culto');
    }
};

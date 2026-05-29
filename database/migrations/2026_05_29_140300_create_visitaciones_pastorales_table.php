<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Visitación pastoral: cuando la maestra visita a un miembro de su clase
     * fuera del culto. Se registra por mes/semana con fecha y notas.
     *
     * Una visita por (persona, clase, año, mes, semana). Si hay más de una
     * visita en la misma semana, se acumula en `notas`.
     */
    public function up(): void
    {
        Schema::create('visitaciones_pastorales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->foreignId('clase_asistencia_id')->constrained('clases_asistencia')->cascadeOnDelete();
            $table->unsignedSmallInteger('año');
            $table->unsignedTinyInteger('mes'); // 1-12
            $table->unsignedTinyInteger('semana'); // 1-5
            $table->date('fecha')->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('registrada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['persona_id', 'clase_asistencia_id', 'año', 'mes', 'semana'], 'vp_unique');
            $table->index(['clase_asistencia_id', 'año', 'mes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitaciones_pastorales');
    }
};

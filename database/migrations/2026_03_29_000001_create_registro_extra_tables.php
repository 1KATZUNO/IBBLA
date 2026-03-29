<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_extra_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->string('color')->default('#6B7280');
            $table->json('subcampos'); // ["miembros"] or ["autos", "motos"]
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('asistencia_registro_extra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asistencia_id')->constrained('asistencia')->cascadeOnDelete();
            $table->foreignId('registro_extra_tipo_id')->constrained('registro_extra_tipos')->cascadeOnDelete();
            $table->json('valores'); // {"miembros": 15} or {"autos": 10, "motos": 5}
            $table->timestamps();
            $table->unique(['asistencia_id', 'registro_extra_tipo_id'], 'asist_reg_extra_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia_registro_extra');
        Schema::dropIfExists('registro_extra_tipos');
    }
};

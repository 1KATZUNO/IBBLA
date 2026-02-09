<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clases_asistencia', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Ej: "Clase 0-1 Años", "Clase 12-15 Años"
            $table->string('slug')->unique(); // Ej: "clase_0_1", "clase_12_15"
            $table->string('color')->default('#3b82f6'); // Color hex para UI
            $table->integer('orden')->default(0); // Orden de visualización
            $table->boolean('activa')->default(true);
            $table->boolean('tiene_maestros')->default(true); // Si tiene campos de maestros
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clases_asistencia');
    }
};

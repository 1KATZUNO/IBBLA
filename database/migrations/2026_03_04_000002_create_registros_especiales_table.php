<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_especiales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asistencia_id')->constrained('asistencia')->onDelete('cascade');
            $table->enum('tipo', ['visita', 'salvo', 'bautismo']);
            $table->string('nombre');
            $table->enum('genero', ['M', 'F']);
            $table->integer('edad')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->timestamps();
        });

        // Add estudiantes_presentes_ids to asistencia_clase_detalle
        Schema::table('asistencia_clase_detalle', function (Blueprint $table) {
            $table->json('estudiantes_presentes_ids')->nullable()->after('maestros_ids');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_clase_detalle', function (Blueprint $table) {
            $table->dropColumn('estudiantes_presentes_ids');
        });

        Schema::dropIfExists('registros_especiales');
    }
};

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
        Schema::create('asistencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('culto_id')->constrained('cultos')->onDelete('cascade');
            
            // Capilla
            $table->integer('chapel_hombres')->default(0);
            $table->integer('chapel_mujeres')->default(0);
            $table->integer('chapel_adultos_mayores')->default(0);
            $table->integer('chapel_adultos')->default(0);
            $table->integer('chapel_jovenes_masculinos')->default(0);
            $table->integer('chapel_jovenes_femeninas')->default(0);
            
            // Clase 0-1
            $table->integer('clase_0_1_hombres')->default(0);
            $table->integer('clase_0_1_mujeres')->default(0);
            $table->integer('clase_0_1_maestros_hombres')->default(0);
            $table->integer('clase_0_1_maestros_mujeres')->default(0);
            
            // Clase 2-6
            $table->integer('clase_2_6_hombres')->default(0);
            $table->integer('clase_2_6_mujeres')->default(0);
            $table->integer('clase_2_6_maestros_hombres')->default(0);
            $table->integer('clase_2_6_maestros_mujeres')->default(0);
            
            // Clase 7-8
            $table->integer('clase_7_8_hombres')->default(0);
            $table->integer('clase_7_8_mujeres')->default(0);
            $table->integer('clase_7_8_maestros_hombres')->default(0);
            $table->integer('clase_7_8_maestros_mujeres')->default(0);
            
            // Clase 9-11
            $table->integer('clase_9_11_hombres')->default(0);
            $table->integer('clase_9_11_mujeres')->default(0);
            $table->integer('clase_9_11_maestros_hombres')->default(0);
            $table->integer('clase_9_11_maestros_mujeres')->default(0);
            
            $table->integer('total_asistencia');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencia');
    }
};

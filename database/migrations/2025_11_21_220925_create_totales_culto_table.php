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
        Schema::create('totales_culto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('culto_id')->constrained('cultos')->onDelete('cascade');
            $table->decimal('total_diezmo', 10, 2)->default(0);
            $table->decimal('total_misiones', 10, 2)->default(0);
            $table->decimal('total_seminario', 10, 2)->default(0);
            $table->decimal('total_campa', 10, 2)->default(0);
            $table->decimal('total_prestamo', 10, 2)->default(0);
            $table->decimal('total_construccion', 10, 2)->default(0);
            $table->decimal('total_micro', 10, 2)->default(0);
            $table->decimal('total_suelto', 10, 2)->default(0);
            $table->decimal('total_general', 10, 2)->default(0);
            $table->integer('cantidad_sobres')->default(0);
            $table->integer('cantidad_transferencias')->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('totales_culto');
    }
};

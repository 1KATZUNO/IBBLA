<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_cambios', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->decimal('compra', 10, 4)->comment('Tipo cambio compra (indicador 317 BCCR)');
            $table->decimal('venta', 10, 4)->comment('Tipo cambio venta (indicador 318 BCCR)');
            $table->string('source', 20)->default('bccr')->comment('bccr o manual');
            $table->timestamps();

            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_cambios');
    }
};

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
        Schema::create('compromisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->onDelete('cascade');
            $table->string('categoria'); // misiones, micro, construccion, etc
            $table->integer('año');
            $table->integer('mes');
            $table->decimal('monto_prometido', 10, 2)->default(0);
            $table->decimal('monto_dado', 10, 2)->default(0);
            $table->decimal('saldo_anterior', 10, 2)->default(0); // excedente o deuda del mes anterior
            $table->decimal('saldo_actual', 10, 2)->default(0); // saldo después de aplicar lo dado este mes
            $table->timestamps();
            
            // Índice único para evitar duplicados
            $table->unique(['persona_id', 'categoria', 'año', 'mes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compromisos');
    }
};

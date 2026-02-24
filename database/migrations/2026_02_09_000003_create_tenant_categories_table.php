<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('slug', 100);
            $table->enum('tipo', ['ingreso', 'compromiso', 'ambos'])->default('ambos');
            $table->boolean('excluir_de_promesas')->default(false);
            $table->boolean('es_ofrenda_suelta')->default(false);
            $table->string('icono', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_categories');
    }
};

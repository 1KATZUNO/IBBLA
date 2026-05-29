<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla de ministerios / áreas de servicio de la iglesia (Alabanza,
     * Transmisión, Ujieres, etc.). Una Persona puede pertenecer a varios
     * ministerios vía la tabla pivot persona_ministerio.
     */
    public function up(): void
    {
        Schema::create('ministerios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 80);
            $table->string('slug', 80);
            $table->string('color', 9)->default('#6B7280');
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ministerios');
    }
};

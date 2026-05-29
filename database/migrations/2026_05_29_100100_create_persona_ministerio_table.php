<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pivot many-to-many entre Persona y Ministerio.
     * - `desde` / `hasta`: rango de servicio (hasta NULL = activo hoy).
     * - `es_lider`: identifica al líder del ministerio.
     */
    public function up(): void
    {
        Schema::create('persona_ministerio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->foreignId('ministerio_id')->constrained('ministerios')->cascadeOnDelete();
            $table->date('desde')->nullable();
            $table->date('hasta')->nullable();
            $table->boolean('es_lider')->default(false);
            $table->timestamps();

            $table->unique(['persona_id', 'ministerio_id']);
            $table->index(['ministerio_id', 'hasta']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persona_ministerio');
    }
};

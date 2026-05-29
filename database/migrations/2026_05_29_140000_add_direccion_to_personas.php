<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * La app de clase (Horeb y similares) registra dirección de cada miembro
     * para visitación pastoral. `notas` ya existe pero es genérico, queremos
     * `direccion` separada para query y display.
     */
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            if (! Schema::hasColumn('personas', 'direccion')) {
                $table->string('direccion', 255)->nullable()->after('correo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn('direccion');
        });
    }
};

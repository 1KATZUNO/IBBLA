<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * El pivot `clase_persona` actualmente solo tiene `es_maestro`.
     * Para la app de clase (Horeb) necesitamos distinguir:
     *  - miembro: persona regular de la clase
     *  - visita:  persona que asiste esporádicamente
     *
     * Los maestros siguen siendo identificados con `es_maestro`.
     * `convertida_de_visita_at` registra cuándo una visita se convirtió a miembro.
     */
    public function up(): void
    {
        Schema::table('clase_persona', function (Blueprint $table) {
            if (! Schema::hasColumn('clase_persona', 'tipo')) {
                $table->enum('tipo', ['miembro', 'visita'])->default('miembro')->after('es_maestro');
            }
            if (! Schema::hasColumn('clase_persona', 'convertida_de_visita_at')) {
                $table->timestamp('convertida_de_visita_at')->nullable()->after('tipo');
            }
            if (! Schema::hasColumn('clase_persona', 'notas_clase')) {
                $table->text('notas_clase')->nullable()->after('convertida_de_visita_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clase_persona', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'convertida_de_visita_at', 'notas_clase']);
        });
    }
};

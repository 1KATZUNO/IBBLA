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
        Schema::table('sobres', function (Blueprint $table) {
            if (!Schema::hasColumn('sobres', 'comprobante_numero')) {
                $table->string('comprobante_numero', 100)->nullable()->after('metodo_pago');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sobres', function (Blueprint $table) {
            if (Schema::hasColumn('sobres', 'comprobante_numero')) {
                $table->dropColumn('comprobante_numero');
            }
        });
    }
};

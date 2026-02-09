<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sobres', function (Blueprint $table) {
            $table->string('comprobante_numero')->nullable()->after('metodo_pago');
        });
    }

    public function down(): void
    {
        Schema::table('sobres', function (Blueprint $table) {
            $table->dropColumn('comprobante_numero');
        });
    }
};

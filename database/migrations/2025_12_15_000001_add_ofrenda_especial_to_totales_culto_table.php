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
        Schema::table('totales_culto', function (Blueprint $table) {
            if (! Schema::hasColumn('totales_culto', 'total_ofrenda_especial')) {
                $table->decimal('total_ofrenda_especial', 10, 2)->default(0)->after('total_diezmo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('totales_culto', function (Blueprint $table) {
            if (Schema::hasColumn('totales_culto', 'total_ofrenda_especial')) {
                $table->dropColumn('total_ofrenda_especial');
            }
        });
    }
};

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
        Schema::table('cultos', function (Blueprint $table) {
            $table->longText('firma_pastor_imagen')->nullable()->after('firma_pastor');
            $table->json('firmas_tesoreros_imagenes')->nullable()->after('firmas_tesoreros');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cultos', function (Blueprint $table) {
            $table->dropColumn(['firma_pastor_imagen', 'firmas_tesoreros_imagenes']);
        });
    }
};

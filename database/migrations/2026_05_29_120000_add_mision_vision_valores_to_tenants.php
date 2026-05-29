<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Añade campos de identidad de iglesia a tenants para que la vista
     * de bienvenida y otros lugares lean de BD en vez de hardcoded.
     *
     * Cada tenant (iglesia) puede tener su propia misión, visión y valores.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('mision')->nullable()->after('sitio_web');
            $table->text('vision')->nullable()->after('mision');
            $table->json('valores')->nullable()->after('vision');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['mision', 'vision', 'valores']);
        });
    }
};

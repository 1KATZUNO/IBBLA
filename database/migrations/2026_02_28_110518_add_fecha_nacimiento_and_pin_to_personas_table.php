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
        Schema::table('personas', function (Blueprint $table) {
            $table->date('fecha_nacimiento')->nullable()->after('correo');
            $table->string('pin', 20)->nullable()->after('fecha_nacimiento');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn(['fecha_nacimiento', 'pin']);
        });
    }
};

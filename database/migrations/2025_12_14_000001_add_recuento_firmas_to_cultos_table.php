<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cultos', function (Blueprint $table) {
            $table->string('firma_tesorero')->nullable()->after('notas');
            $table->string('firma_secretario')->nullable()->after('firma_tesorero');
            $table->string('firma_pastor')->nullable()->after('firma_secretario');
        });
    }

    public function down(): void
    {
        Schema::table('cultos', function (Blueprint $table) {
            $table->dropColumn(['firma_tesorero', 'firma_secretario', 'firma_pastor']);
        });
    }
};

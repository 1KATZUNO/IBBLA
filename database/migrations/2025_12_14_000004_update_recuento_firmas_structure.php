<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cultos', function (Blueprint $table) {
            if (Schema::hasColumn('cultos', 'firma_secretario')) {
                $table->dropColumn('firma_secretario');
            }
            $table->json('firmas_tesoreros')->nullable()->after('firma_tesorero');
        });
    }

    public function down(): void
    {
        Schema::table('cultos', function (Blueprint $table) {
            $table->string('firma_secretario')->nullable()->after('firma_tesorero');
            $table->dropColumn('firmas_tesoreros');
        });
    }
};

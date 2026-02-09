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
        Schema::table('asistencia', function (Blueprint $table) {
            $table->boolean('cerrado')->default(false)->after('total_asistencia');
            $table->timestamp('cerrado_at')->nullable()->after('cerrado');
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->after('cerrado_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asistencia', function (Blueprint $table) {
            $table->dropForeign(['cerrado_por']);
            $table->dropColumn(['cerrado', 'cerrado_at', 'cerrado_por']);
        });
    }
};

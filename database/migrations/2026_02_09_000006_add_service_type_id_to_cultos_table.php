<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cultos', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('service_type_id')->nullable()->after('tipo_culto')->constrained('tenant_service_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cultos', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['service_type_id']);
            $table->dropColumn(['tenant_id', 'service_type_id']);
        });
    }
};

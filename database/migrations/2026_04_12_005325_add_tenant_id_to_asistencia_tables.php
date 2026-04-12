<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add tenant_id to asistencia table
        Schema::table('asistencia', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->onDelete('cascade');
        });

        // 2. Add tenant_id to clases_asistencia table and fix unique constraint
        Schema::table('clases_asistencia', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->onDelete('cascade');
            $table->dropUnique(['slug']);
            $table->unique(['slug', 'tenant_id'], 'clases_asistencia_slug_tenant_unique');
        });

        // 3. Add tenant_id to registro_extra_tipos table and fix unique constraint
        Schema::table('registro_extra_tipos', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->onDelete('cascade');
            $table->dropUnique(['slug']);
            $table->unique(['slug', 'tenant_id'], 'registro_extra_tipos_slug_tenant_unique');
        });

        // 4. Backfill tenant_id on asistencia from the related culto
        DB::statement('
            UPDATE asistencia
            INNER JOIN cultos ON asistencia.culto_id = cultos.id
            SET asistencia.tenant_id = cultos.tenant_id
            WHERE asistencia.tenant_id IS NULL
        ');

        // 5. Backfill tenant_id on clases_asistencia and registro_extra_tipos
        $firstTenant = DB::table('tenants')->first();
        if ($firstTenant) {
            DB::table('clases_asistencia')
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $firstTenant->id]);

            DB::table('registro_extra_tipos')
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $firstTenant->id]);
        }
    }

    public function down(): void
    {
        Schema::table('registro_extra_tipos', function (Blueprint $table) {
            $table->dropUnique('registro_extra_tipos_slug_tenant_unique');
            $table->unique('slug');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('clases_asistencia', function (Blueprint $table) {
            $table->dropUnique('clases_asistencia_slug_tenant_unique');
            $table->unique('slug');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('asistencia', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};

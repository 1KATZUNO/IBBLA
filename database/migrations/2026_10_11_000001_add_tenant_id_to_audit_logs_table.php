<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El Historial de ingresos reutiliza audit_logs, pero la plataforma aloja varias
     * iglesias y un administrador solo debe ver los accesos de la suya. audit_logs no
     * tenía forma de saber a qué iglesia pertenece una fila, así que se agrega tenant_id.
     *
     * - Nullable: las filas anteriores (y los intentos fallidos con un correo que no
     *   existe) no tienen iglesia; solo las ve el super admin.
     * - Sin foreign key, igual que user_id: un registro de auditoría debe sobrevivir al
     *   borrado del usuario o de la iglesia.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};

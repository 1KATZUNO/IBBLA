<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accesos de edicion (modulo Accesos, HU-REC-3): el administrador le permite a un usuario de
     * tesoreria corregir cultos cerrados. Un registro por concesion; al revocarla no se borra (queda
     * el historial), se marca revocado_at. Mientras un usuario tenga uno vigente tambien puede crear
     * cultos.
     */
    public function up(): void
    {
        Schema::create('permisos_edicion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // cultos (uno o varios concretos) | rango (por fecha del culto) | todos
            $table->string('tipo', 20);
            // Ids de los cultos cuando tipo = cultos
            $table->json('referencia')->nullable();
            // Fechas de culto incluidas cuando tipo = rango
            $table->date('desde')->nullable();
            $table->date('hasta')->nullable();

            // Vencimiento opcional (fin del dia elegido). Null = hasta que se revoque.
            $table->dateTime('vence_el')->nullable();
            $table->string('motivo')->nullable();

            $table->foreignId('otorgado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revocado_at')->nullable();
            $table->foreignId('revocado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'revocado_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_edicion');
    }
};

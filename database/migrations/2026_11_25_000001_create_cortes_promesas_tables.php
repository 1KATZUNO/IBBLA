<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promesas futuras ("corte de promesas"): un juego completo de promesas que entra en vigor en una
     * fecha y reemplaza a TODAS las anteriores de la iglesia (borron y cuenta nueva).
     *
     *  - cortes_promesas: el corte y su fecha de inicio (siempre el dia 1 de un mes).
     *  - promesas_programadas: lo que promete cada persona en ese corte. Nadie las lee para calcular
     *    hasta que el corte se activa.
     *  - promesas_historial: al activarse un corte, las promesas que regian hasta la vispera se
     *    guardan aqui con su vigencia, para que los reportes de meses anteriores sigan dando lo mismo.
     *
     * La tabla `promesas` sigue siendo "lo que rige hoy": no cambia.
     */
    public function up(): void
    {
        Schema::create('cortes_promesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->date('inicia_el');
            // null = programado (todavia no rige); con fecha = ya entro en vigor
            $table->timestamp('activado_at')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            // null cuando lo activo el sistema al llegar la fecha
            $table->foreignId('activado_por')->nullable()->constrained('users')->nullOnDelete();
            // Ids de tenant_categories creadas para este corte (titulos nuevos del Excel o agregadas a
            // mano). Nacen con activa = false y se activan al entrar en vigor el corte: desde ese dia
            // aparecen en los sobres y en los reportes.
            $table->json('categorias_nuevas')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'inicia_el']);
        });

        Schema::create('promesas_programadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corte_id')->constrained('cortes_promesas')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->string('categoria');
            $table->decimal('monto', 10, 2);
            $table->string('moneda', 3)->default('CRC');
            $table->enum('frecuencia', ['semanal', 'quincenal', 'mensual'])->default('semanal');
            $table->timestamps();

            $table->index(['corte_id', 'persona_id']);
        });

        Schema::create('promesas_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // Corte al que pertenecian; null = las que habia antes del primer corte
            $table->foreignId('corte_id')->nullable()->constrained('cortes_promesas')->nullOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->string('categoria');
            $table->decimal('monto', 10, 2);
            $table->string('moneda', 3)->default('CRC');
            $table->enum('frecuencia', ['semanal', 'quincenal', 'mensual']);
            // null = desde siempre
            $table->date('vigente_desde')->nullable();
            // Ultimo dia en que rigieron: la vispera del corte que las reemplazo
            $table->date('vigente_hasta');
            $table->timestamps();

            $table->index(['tenant_id', 'vigente_hasta']);
            $table->index(['persona_id', 'vigente_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promesas_historial');
        Schema::dropIfExists('promesas_programadas');
        Schema::dropIfExists('cortes_promesas');
    }
};

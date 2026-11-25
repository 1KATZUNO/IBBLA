<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * QE-009: los avisos de WhatsApp ya no se envían dentro de la petición que los crea (crear culto,
     * guardar el 10.º sobre). La propia tabla hace de cola: el aviso nace pendiente y, si el sistema
     * puede mandarlo solo, queda marcado `envio_automatico`; el comando whatsapp:enviar-pendientes
     * (planificador, cada minuto) lo envía con reintentos acotados y deja el último error a la vista.
     */
    public function up(): void
    {
        Schema::table('avisos_whatsapp', function (Blueprint $table) {
            $table->boolean('envio_automatico')->default(false)->after('estado');
            $table->unsignedTinyInteger('intentos')->default(0)->after('envio_automatico');
            $table->timestamp('proximo_intento_at')->nullable()->after('intentos');
            $table->string('ultimo_error', 500)->nullable()->after('proximo_intento_at');

            $table->index(['estado', 'envio_automatico', 'proximo_intento_at'], 'avisos_whatsapp_cola_idx');
        });
    }

    public function down(): void
    {
        Schema::table('avisos_whatsapp', function (Blueprint $table) {
            $table->dropIndex('avisos_whatsapp_cola_idx');
            $table->dropColumn(['envio_automatico', 'intentos', 'proximo_intento_at', 'ultimo_error']);
        });
    }
};

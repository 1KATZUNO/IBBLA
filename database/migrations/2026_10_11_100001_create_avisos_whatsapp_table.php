<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cola de avisos de WhatsApp: un registro por destinatario y por evento.
     *
     * Con el driver «link» nadie envía solo; el aviso espera en el panel /avisos hasta que alguien
     * abre WhatsApp y lo marca como enviado (o lo descarta). Con el driver «meta» un job lo envía
     * y lo marca. Se guarda el mensaje ya armado para que lo que se mande sea lo que se vio.
     */
    public function up(): void
    {
        Schema::create('avisos_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            // culto_creado | sobres_alcanzados (ver AvisoWhatsApp::EVENTOS)
            $table->string('evento', 40);
            $table->string('titulo');
            $table->text('mensaje');

            // El teléfono se guarda normalizado (E.164 sin «+»), listo para wa.me.
            $table->string('destinatario_nombre');
            $table->string('destinatario_telefono', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Página del sistema a la que apunta el aviso (p. ej. el recuento del culto).
            $table->string('enlace', 500)->nullable();

            // pendiente | enviado | descartado
            $table->string('estado', 15)->default('pendiente');
            $table->timestamp('enviado_at')->nullable();
            // Quien lo atendió (lo marcó enviado o lo descartó). Null si lo envió el sistema (driver meta).
            $table->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'estado', 'evento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_whatsapp');
    }
};

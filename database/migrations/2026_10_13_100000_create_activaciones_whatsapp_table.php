<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Activación de WhatsApp (CallMeBot) de una persona o de un usuario del sistema.
     *
     * Con CallMeBot la clave es de cada receptor: la persona agrega el bot a sus contactos, le manda
     * la frase de activación y pega en su perfil el enlace que el bot le responde (trae su número y su
     * clave). Una fila por receptor, ligada a la persona o al usuario (nunca a los dos):
     *
     *  - La clave (`apikey`) se guarda cifrada con APP_KEY y nunca se vuelve a mostrar. Al desactivar se borra.
     *  - `telefono` es el número con el que se activó el bot, normalizado (E.164 sin «+»). Debe seguir
     *    siendo el que la persona o el usuario tiene registrado: si lo cambian, la clave ya no sirve.
     *  - El consentimiento guarda cuándo, con qué texto (versión), desde qué IP y navegador aceptó.
     *    Al desactivar se conserva la fila con la fecha de revocación, sin la clave.
     */
    public function up(): void
    {
        Schema::create('activaciones_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            // Exactamente uno de los dos; al borrar la persona o el usuario se borra su activación.
            $table->foreignId('persona_id')->nullable()->unique()->constrained('personas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();

            $table->string('telefono', 20);
            $table->text('apikey')->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamp('consentimiento_at')->nullable();
            $table->string('consentimiento_version', 20)->nullable();
            $table->string('consentimiento_ip', 45)->nullable();
            $table->string('consentimiento_agente', 255)->nullable();
            $table->timestamp('revocado_at')->nullable();

            // Última prueba que salió bien, último envío intentado y, si falló, por qué (sin la clave).
            $table->timestamp('verificado_at')->nullable();
            $table->timestamp('ultimo_envio_at')->nullable();
            $table->string('ultimo_error', 255)->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'telefono']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activaciones_whatsapp');
    }
};

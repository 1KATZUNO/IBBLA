<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los avisos de WhatsApp (culto creado, sobres alcanzados) se mandan a usuarios con cierto
     * rol, y para eso hace falta su teléfono. Opcional: quien no lo tenga simplemente no recibe avisos.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'telefono')) {
                $table->string('telefono', 30)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telefono');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sobres: moneda del sobre completo
        Schema::table('sobres', function (Blueprint $table) {
            $table->string('moneda', 3)->default('CRC')->after('total_declarado');
            $table->decimal('tipo_cambio_venta', 10, 4)->nullable()->after('moneda');
            $table->foreignId('tipo_cambio_id')->nullable()->after('tipo_cambio_venta')
                ->constrained('tipo_cambios')->nullOnDelete();
        });

        // Ofrenda suelta: moneda individual
        Schema::table('ofrenda_suelta', function (Blueprint $table) {
            $table->string('moneda', 3)->default('CRC')->after('monto');
            $table->decimal('tipo_cambio_venta', 10, 4)->nullable()->after('moneda');
        });

        // Promesas: moneda de la promesa
        Schema::table('promesas', function (Blueprint $table) {
            $table->string('moneda', 3)->default('CRC')->after('monto');
        });

        // Compromisos: campos para comparación en CRC
        Schema::table('compromisos', function (Blueprint $table) {
            $table->string('moneda_promesa', 3)->default('CRC')->after('saldo_actual');
            $table->decimal('monto_prometido_crc', 10, 2)->default(0)->after('moneda_promesa');
            $table->decimal('monto_dado_crc', 10, 2)->default(0)->after('monto_prometido_crc');
            $table->decimal('tipo_cambio_usado', 10, 4)->nullable()->after('monto_dado_crc');
        });

        // Egresos: moneda del egreso
        Schema::table('egresos', function (Blueprint $table) {
            $table->string('moneda', 3)->default('CRC')->after('monto');
            $table->decimal('tipo_cambio_venta', 10, 4)->nullable()->after('moneda');
        });

        // Totales Culto: totales USD separados
        Schema::table('totales_culto', function (Blueprint $table) {
            $table->decimal('tipo_cambio_venta', 10, 4)->nullable()->after('notas');
            $table->json('totales_usd')->nullable()->after('tipo_cambio_venta');
            $table->decimal('total_general_usd', 10, 2)->default(0)->after('totales_usd');
            $table->decimal('total_general_crc_convertido', 10, 2)->default(0)->after('total_general_usd');
        });

        // Cultos: tipo de cambio congelado al cerrar
        Schema::table('cultos', function (Blueprint $table) {
            $table->decimal('tipo_cambio_venta', 10, 4)->nullable()->after('cerrado_por');
        });
    }

    public function down(): void
    {
        Schema::table('sobres', function (Blueprint $table) {
            $table->dropForeign(['tipo_cambio_id']);
            $table->dropColumn(['moneda', 'tipo_cambio_venta', 'tipo_cambio_id']);
        });

        Schema::table('ofrenda_suelta', function (Blueprint $table) {
            $table->dropColumn(['moneda', 'tipo_cambio_venta']);
        });

        Schema::table('promesas', function (Blueprint $table) {
            $table->dropColumn(['moneda']);
        });

        Schema::table('compromisos', function (Blueprint $table) {
            $table->dropColumn(['moneda_promesa', 'monto_prometido_crc', 'monto_dado_crc', 'tipo_cambio_usado']);
        });

        Schema::table('egresos', function (Blueprint $table) {
            $table->dropColumn(['moneda', 'tipo_cambio_venta']);
        });

        Schema::table('totales_culto', function (Blueprint $table) {
            $table->dropColumn(['tipo_cambio_venta', 'totales_usd', 'total_general_usd', 'total_general_crc_convertido']);
        });

        Schema::table('cultos', function (Blueprint $table) {
            $table->dropColumn(['tipo_cambio_venta']);
        });
    }
};

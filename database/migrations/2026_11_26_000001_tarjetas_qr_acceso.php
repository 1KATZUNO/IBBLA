<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tarjetas con QR de acceso personal (docs/plan-oct-2026/07-TARJETAS-QR.md).
     *
     *  - personas.token_acceso: el secreto de la direccion /t/{token} que lleva el QR de la tarjeta. Aleatorio,
     *    de 40 caracteres, unico; nace la primera vez que se pide la tarjeta y el administrador puede cambiarlo.
     *  - users.usuario: nombre de usuario (sin @) para quien entra sin correo. El login acepta correo o usuario.
     *    Por eso el correo pasa a ser opcional; las cuentas que ya existen siguen con su correo igual que antes.
     *  - users.password_visible: copia CIFRADA (cast encrypted, llave de la app) de la contrasena, solo para
     *    cuentas de rol miembro y solo con el interruptor ibbla.tarjetas.contrasena_visible encendido.
     *  - users.debe_crear_contrasena: "Restablecer acceso" del administrador; el proximo escaneo de la tarjeta le
     *    pide a la persona una contrasena nueva.
     *
     * Compatible con MariaDB 11.8 y MySQL 8: un indice unico admite varios NULL en los dos.
     */
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->string('token_acceso', 40)->nullable()->unique()->after('pin');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('usuario', 60)->nullable()->unique()->after('email');
            $table->text('password_visible')->nullable()->after('password');
            $table->boolean('debe_crear_contrasena')->default(false)->after('password_visible');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['usuario']);
            $table->dropColumn(['usuario', 'password_visible', 'debe_crear_contrasena']);
        });
        // Volver a exigir correo solo es posible si ninguna cuenta quedo sin correo.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });

        Schema::table('personas', function (Blueprint $table) {
            $table->dropUnique(['token_acceso']);
            $table->dropColumn('token_acceso');
        });
    }
};

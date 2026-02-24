<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('siglas', 20);
            $table->string('slug', 100)->unique();
            // Branding
            $table->string('logo_path', 500)->nullable();
            $table->string('logo_pdf_path', 500)->nullable();
            $table->string('favicon_path', 500)->nullable();
            // Color theme
            $table->string('color_theme', 20)->default('blue');
            $table->boolean('use_custom_colors')->default(false);
            $table->string('color_50', 7)->default('#eff6ff');
            $table->string('color_100', 7)->default('#dbeafe');
            $table->string('color_200', 7)->default('#bfdbfe');
            $table->string('color_300', 7)->default('#93c5fd');
            $table->string('color_400', 7)->default('#60a5fa');
            $table->string('color_500', 7)->default('#3b82f6');
            $table->string('color_600', 7)->default('#2563eb');
            $table->string('color_700', 7)->default('#1d4ed8');
            $table->string('color_800', 7)->default('#1e40af');
            $table->string('color_900', 7)->default('#1e3a8a');
            // Config regional
            $table->string('timezone', 50)->default('America/Costa_Rica');
            $table->string('locale', 10)->default('es');
            $table->string('moneda_codigo', 3)->default('CRC');
            $table->string('moneda_simbolo', 5)->default('₡');
            // Contacto
            $table->text('direccion')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email_contacto')->nullable();
            $table->string('sitio_web', 500)->nullable();
            $table->json('redes_sociales')->nullable();
            // Estado
            $table->boolean('activo')->default(true);
            $table->integer('max_usuarios')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

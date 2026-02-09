<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Primero cambiar temporalmente a string
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->default('invitado')->change();
        });
        
        // Actualizar los valores
        \DB::table('users')->where('rol', 'general')->update(['rol' => 'invitado']);
        
        // Finalmente cambiar a enum con nuevos valores
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['admin', 'tesorero', 'asistente', 'invitado'])->default('invitado')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->default('general')->change();
        });
        
        \DB::table('users')->where('rol', 'invitado')->update(['rol' => 'general']);
        
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['admin', 'tesorero', 'general'])->default('general')->change();
        });
    }
};

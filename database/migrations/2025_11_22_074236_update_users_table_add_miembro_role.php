<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el enum para incluir 'miembro'
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM('admin', 'tesorero', 'asistente', 'invitado', 'miembro') DEFAULT 'invitado'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM('admin', 'tesorero', 'asistente', 'invitado') DEFAULT 'invitado'");
    }
};

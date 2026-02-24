<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM('admin','tesorero','asistente','servidor','miembro','invitado') DEFAULT 'invitado'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM('admin','tesorero','asistente','invitado') DEFAULT 'invitado'");
    }
};

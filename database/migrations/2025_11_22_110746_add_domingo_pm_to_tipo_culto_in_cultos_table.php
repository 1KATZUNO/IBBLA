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
        // Modificar el ENUM para incluir domingo_pm
        DB::statement("ALTER TABLE cultos MODIFY COLUMN tipo_culto ENUM('domingo', 'domingo_pm', 'miércoles', 'sábado', 'especial') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al ENUM original
        DB::statement("ALTER TABLE cultos MODIFY COLUMN tipo_culto ENUM('domingo', 'miércoles', 'sábado', 'especial') NOT NULL");
    }
};

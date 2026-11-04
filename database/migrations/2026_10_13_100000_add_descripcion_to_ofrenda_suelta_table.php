<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El formulario de dinero suelto siempre pidio una descripcion, pero la tabla no tenia la columna
 * y el modelo la descartaba sin avisar. Los egresos si la guardan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ofrenda_suelta', 'descripcion')) {
            Schema::table('ofrenda_suelta', function (Blueprint $table) {
                $table->text('descripcion')->nullable()->after('metodo_pago');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ofrenda_suelta', 'descripcion')) {
            Schema::table('ofrenda_suelta', function (Blueprint $table) {
                $table->dropColumn('descripcion');
            });
        }
    }
};

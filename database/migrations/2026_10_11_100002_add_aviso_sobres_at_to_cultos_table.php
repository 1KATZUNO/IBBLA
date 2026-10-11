<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca de que el culto ya disparó el aviso «llegó a N sobres», para que sea una sola vez por
     * culto aunque después se borre y se vuelva a registrar un sobre, o lo registren dos personas a la vez.
     *
     * Los cultos que ya traen N sobres o más al instalar esto se marcan como avisados: el aviso es
     * para cuando un culto LLEGA a N, no para el que ya venía de antes.
     */
    public function up(): void
    {
        Schema::table('cultos', function (Blueprint $table) {
            $table->timestamp('aviso_sobres_at')->nullable();
        });

        $umbral = max(1, (int) config('whatsapp.avisos.sobres_umbral', 10));

        DB::table('cultos')
            ->whereRaw('(select count(*) from sobres where sobres.culto_id = cultos.id) >= ?', [$umbral])
            ->update(['aviso_sobres_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('cultos', function (Blueprint $table) {
            $table->dropColumn('aviso_sobres_at');
        });
    }
};

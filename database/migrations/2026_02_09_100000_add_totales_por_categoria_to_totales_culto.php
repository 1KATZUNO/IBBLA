<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('totales_culto', function (Blueprint $table) {
            $table->json('totales_por_categoria')->nullable()->after('cantidad_transferencias');
            if (!Schema::hasColumn('totales_culto', 'total_egresos')) {
                $table->decimal('total_egresos', 10, 2)->default(0)->after('total_general');
            }
        });

        // Backfill: construir JSON desde columnas legacy
        $legacyColumns = [
            'diezmo' => 'total_diezmo',
            'ofrenda_especial' => 'total_ofrenda_especial',
            'misiones' => 'total_misiones',
            'seminario' => 'total_seminario',
            'campa' => 'total_campa',
            'prestamo' => 'total_prestamo',
            'construccion' => 'total_construccion',
            'micro' => 'total_micro',
        ];

        $rows = DB::table('totales_culto')->get();
        foreach ($rows as $row) {
            $json = [];
            foreach ($legacyColumns as $slug => $column) {
                $value = $row->$column ?? 0;
                if ($value > 0) {
                    $json[$slug] = (float) $value;
                }
            }
            DB::table('totales_culto')
                ->where('id', $row->id)
                ->update(['totales_por_categoria' => json_encode($json)]);
        }
    }

    public function down(): void
    {
        Schema::table('totales_culto', function (Blueprint $table) {
            $table->dropColumn('totales_por_categoria');
            if (Schema::hasColumn('totales_culto', 'total_egresos')) {
                $table->dropColumn('total_egresos');
            }
        });
    }
};

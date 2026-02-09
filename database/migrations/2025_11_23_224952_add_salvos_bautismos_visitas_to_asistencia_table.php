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
        Schema::table('asistencia', function (Blueprint $table) {
            // Salvos
            $table->integer('salvos_adulto_hombre')->default(0)->after('total_asistencia');
            $table->integer('salvos_adulto_mujer')->default(0)->after('salvos_adulto_hombre');
            $table->integer('salvos_joven_hombre')->default(0)->after('salvos_adulto_mujer');
            $table->integer('salvos_joven_mujer')->default(0)->after('salvos_joven_hombre');
            $table->integer('salvos_nino')->default(0)->after('salvos_joven_mujer');
            $table->integer('salvos_nina')->default(0)->after('salvos_nino');
            
            // Bautismos
            $table->integer('bautismos_adulto_hombre')->default(0)->after('salvos_nina');
            $table->integer('bautismos_adulto_mujer')->default(0)->after('bautismos_adulto_hombre');
            $table->integer('bautismos_joven_hombre')->default(0)->after('bautismos_adulto_mujer');
            $table->integer('bautismos_joven_mujer')->default(0)->after('bautismos_joven_hombre');
            $table->integer('bautismos_nino')->default(0)->after('bautismos_joven_mujer');
            $table->integer('bautismos_nina')->default(0)->after('bautismos_nino');
            
            // Visitas
            $table->integer('visitas_adulto_hombre')->default(0)->after('bautismos_nina');
            $table->integer('visitas_adulto_mujer')->default(0)->after('visitas_adulto_hombre');
            $table->integer('visitas_joven_hombre')->default(0)->after('visitas_adulto_mujer');
            $table->integer('visitas_joven_mujer')->default(0)->after('visitas_joven_hombre');
            $table->integer('visitas_nino')->default(0)->after('visitas_joven_mujer');
            $table->integer('visitas_nina')->default(0)->after('visitas_nino');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asistencia', function (Blueprint $table) {
            $table->dropColumn([
                'salvos_adulto_hombre', 'salvos_adulto_mujer', 'salvos_joven_hombre', 
                'salvos_joven_mujer', 'salvos_nino', 'salvos_nina',
                'bautismos_adulto_hombre', 'bautismos_adulto_mujer', 'bautismos_joven_hombre', 
                'bautismos_joven_mujer', 'bautismos_nino', 'bautismos_nina',
                'visitas_adulto_hombre', 'visitas_adulto_mujer', 'visitas_joven_hombre', 
                'visitas_joven_mujer', 'visitas_nino', 'visitas_nina'
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Crear la nueva tabla pivot
        Schema::create('asistencia_clase_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asistencia_id')->constrained('asistencia')->onDelete('cascade');
            $table->foreignId('clase_asistencia_id')->constrained('clases_asistencia')->onDelete('cascade');
            $table->integer('hombres')->default(0);
            $table->integer('mujeres')->default(0);
            $table->integer('maestros_hombres')->default(0);
            $table->integer('maestros_mujeres')->default(0);
            $table->timestamps();

            $table->unique(['asistencia_id', 'clase_asistencia_id'], 'asist_clase_unique');
        });

        // 2. Migrar datos existentes de columnas hardcodeadas a la nueva tabla
        $slugMapping = [
            'clase_0_1' => ['hombres' => 'clase_0_1_hombres', 'mujeres' => 'clase_0_1_mujeres', 'maestros_hombres' => 'clase_0_1_maestros_hombres', 'maestros_mujeres' => 'clase_0_1_maestros_mujeres'],
            'clase_2_6' => ['hombres' => 'clase_2_6_hombres', 'mujeres' => 'clase_2_6_mujeres', 'maestros_hombres' => 'clase_2_6_maestros_hombres', 'maestros_mujeres' => 'clase_2_6_maestros_mujeres'],
            'clase_7_8' => ['hombres' => 'clase_7_8_hombres', 'mujeres' => 'clase_7_8_mujeres', 'maestros_hombres' => 'clase_7_8_maestros_hombres', 'maestros_mujeres' => 'clase_7_8_maestros_mujeres'],
            'clase_9_11' => ['hombres' => 'clase_9_11_hombres', 'mujeres' => 'clase_9_11_mujeres', 'maestros_hombres' => 'clase_9_11_maestros_hombres', 'maestros_mujeres' => 'clase_9_11_maestros_mujeres'],
        ];

        $clases = DB::table('clases_asistencia')->whereIn('slug', array_keys($slugMapping))->get()->keyBy('slug');
        $asistencias = DB::table('asistencia')->get();

        foreach ($asistencias as $asistencia) {
            foreach ($slugMapping as $slug => $columns) {
                $clase = $clases->get($slug);
                if (!$clase) continue;

                $hombres = $asistencia->{$columns['hombres']} ?? 0;
                $mujeres = $asistencia->{$columns['mujeres']} ?? 0;
                $maestrosH = $asistencia->{$columns['maestros_hombres']} ?? 0;
                $maestrosM = $asistencia->{$columns['maestros_mujeres']} ?? 0;

                if ($hombres > 0 || $mujeres > 0 || $maestrosH > 0 || $maestrosM > 0) {
                    DB::table('asistencia_clase_detalle')->insert([
                        'asistencia_id' => $asistencia->id,
                        'clase_asistencia_id' => $clase->id,
                        'hombres' => $hombres,
                        'mujeres' => $mujeres,
                        'maestros_hombres' => $maestrosH,
                        'maestros_mujeres' => $maestrosM,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 3. Eliminar las columnas viejas
        Schema::table('asistencia', function (Blueprint $table) {
            $table->dropColumn([
                'clase_0_1_hombres', 'clase_0_1_mujeres', 'clase_0_1_maestros_hombres', 'clase_0_1_maestros_mujeres',
                'clase_2_6_hombres', 'clase_2_6_mujeres', 'clase_2_6_maestros_hombres', 'clase_2_6_maestros_mujeres',
                'clase_7_8_hombres', 'clase_7_8_mujeres', 'clase_7_8_maestros_hombres', 'clase_7_8_maestros_mujeres',
                'clase_9_11_hombres', 'clase_9_11_mujeres', 'clase_9_11_maestros_hombres', 'clase_9_11_maestros_mujeres',
            ]);
        });
    }

    public function down(): void
    {
        // Re-agregar las columnas viejas
        Schema::table('asistencia', function (Blueprint $table) {
            $table->integer('clase_0_1_hombres')->default(0)->after('chapel_maestros_mujeres');
            $table->integer('clase_0_1_mujeres')->default(0)->after('clase_0_1_hombres');
            $table->integer('clase_0_1_maestros_hombres')->default(0)->after('clase_0_1_mujeres');
            $table->integer('clase_0_1_maestros_mujeres')->default(0)->after('clase_0_1_maestros_hombres');
            $table->integer('clase_2_6_hombres')->default(0)->after('clase_0_1_maestros_mujeres');
            $table->integer('clase_2_6_mujeres')->default(0)->after('clase_2_6_hombres');
            $table->integer('clase_2_6_maestros_hombres')->default(0)->after('clase_2_6_mujeres');
            $table->integer('clase_2_6_maestros_mujeres')->default(0)->after('clase_2_6_maestros_hombres');
            $table->integer('clase_7_8_hombres')->default(0)->after('clase_2_6_maestros_mujeres');
            $table->integer('clase_7_8_mujeres')->default(0)->after('clase_7_8_hombres');
            $table->integer('clase_7_8_maestros_hombres')->default(0)->after('clase_7_8_mujeres');
            $table->integer('clase_7_8_maestros_mujeres')->default(0)->after('clase_7_8_maestros_hombres');
            $table->integer('clase_9_11_hombres')->default(0)->after('clase_7_8_maestros_mujeres');
            $table->integer('clase_9_11_mujeres')->default(0)->after('clase_9_11_hombres');
            $table->integer('clase_9_11_maestros_hombres')->default(0)->after('clase_9_11_mujeres');
            $table->integer('clase_9_11_maestros_mujeres')->default(0)->after('clase_9_11_maestros_hombres');
        });

        Schema::dropIfExists('asistencia_clase_detalle');
    }
};

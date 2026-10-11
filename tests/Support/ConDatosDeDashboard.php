<?php

namespace Tests\Support;

use App\Models\Asistencia;
use App\Models\Compromiso;
use App\Models\Culto;
use App\Models\Persona;
use App\Models\Promesa;
use App\Models\Sobre;
use App\Models\SobreDetalle;
use App\Models\Tenant;
use App\Models\TotalesCulto;

/**
 * Datos mínimos para probar los dashboards: cultos con sus totales y asistencia, personas
 * con promesas y sobres, y compromisos mensuales.
 */
trait ConDatosDeDashboard
{
    /**
     * @param  array{diezmo?: float, misiones?: float, suelto?: float}  $totales  vacío = culto sin totales calculados
     * @param  int|null  $asistencia  null = culto sin asistencia registrada
     */
    protected function culto(Tenant $iglesia, string $fecha, array $totales = [], ?int $asistencia = null, string $tipo = 'domingo'): Culto
    {
        $culto = Culto::create([
            'fecha' => $fecha,
            'hora' => '10:00:00',
            'tipo_culto' => $tipo,
            'tenant_id' => $iglesia->id,
        ]);

        if ($totales !== []) {
            $diezmo = $totales['diezmo'] ?? 0;
            $misiones = $totales['misiones'] ?? 0;
            $suelto = $totales['suelto'] ?? 0;

            TotalesCulto::create([
                'culto_id' => $culto->id,
                'total_diezmo' => $diezmo,
                'total_misiones' => $misiones,
                'total_suelto' => $suelto,
                'total_general' => $diezmo + $misiones + $suelto,
                'totales_por_categoria' => ['diezmo' => $diezmo, 'misiones' => $misiones],
            ]);
        }

        if ($asistencia !== null) {
            Asistencia::create([
                'culto_id' => $culto->id,
                'tenant_id' => $iglesia->id,
                'total_asistencia' => $asistencia,
            ]);
        }

        return $culto;
    }

    protected function personaConPromesa(Tenant $iglesia, string $nombre, string $categoria, float $monto, string $frecuencia = 'mensual'): Persona
    {
        $persona = Persona::create(['nombre' => $nombre, 'tenant_id' => $iglesia->id, 'activo' => true]);
        Promesa::create(['persona_id' => $persona->id, 'categoria' => $categoria, 'monto' => $monto, 'frecuencia' => $frecuencia]);

        return $persona;
    }

    /** Un sobre de la persona en ese culto con un solo detalle (categoría y monto). */
    protected function sobre(Culto $culto, Persona $persona, string $categoria, float $monto): Sobre
    {
        $sobre = Sobre::create([
            'culto_id' => $culto->id,
            'persona_id' => $persona->id,
            'metodo_pago' => 'efectivo',
            'total_declarado' => $monto,
            'moneda' => 'CRC',
        ]);
        SobreDetalle::create(['sobre_id' => $sobre->id, 'categoria' => $categoria, 'monto' => $monto]);

        return $sobre;
    }

    protected function compromiso(Persona $persona, int $anio, int $mes, float $prometido, float $dado, string $categoria = 'misiones'): Compromiso
    {
        return Compromiso::create([
            'persona_id' => $persona->id,
            'categoria' => $categoria,
            'año' => $anio,
            'mes' => $mes,
            'monto_prometido' => $prometido,
            'monto_dado' => $dado,
        ]);
    }
}

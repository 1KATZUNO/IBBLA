<?php

namespace Tests\Support;

use App\Models\Culto;
use App\Models\Persona;
use App\Models\Sobre;
use App\Models\SobreDetalle;
use App\Models\Tenant;
use App\Models\TenantServiceType;
use App\Models\User;
use Carbon\Carbon;

/**
 * Cultos, sobres y personas para las pruebas de Recuento. Se usa junto con ConIglesia
 * (la iglesia ya trae sus tipos de culto y categorias por defecto).
 */
trait ConRecuento
{
    protected function crearCulto(Tenant $iglesia, string $fecha, string $tipo = 'domingo', bool $cerrado = false, ?User $cerradoPor = null): Culto
    {
        $tipoCulto = TenantServiceType::where('tenant_id', $iglesia->id)->where('slug', $tipo)->first();

        return Culto::withoutGlobalScopes()->create([
            'tenant_id' => $iglesia->id,
            'service_type_id' => $tipoCulto?->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'tipo_culto' => $tipo,
            'cerrado' => $cerrado,
            'cerrado_at' => $cerrado ? Carbon::parse($fecha)->setTime(13, 0) : null,
            'cerrado_por' => $cerrado ? $cerradoPor?->id : null,
        ]);
    }

    protected function crearPersonaDe(Tenant $iglesia, string $nombre = 'Ana Mora'): Persona
    {
        return Persona::withoutGlobalScopes()->create([
            'nombre' => $nombre,
            'activo' => true,
            'tenant_id' => $iglesia->id,
        ]);
    }

    /** @param  array<string, float|int>  $detalles  categoria => monto */
    protected function crearSobreEn(Culto $culto, ?Persona $persona = null, array $detalles = ['diezmo' => 10000], string $metodo = 'efectivo'): Sobre
    {
        $sobre = Sobre::create([
            'culto_id' => $culto->id,
            'persona_id' => $persona?->id,
            'metodo_pago' => $metodo,
            'comprobante_numero' => $metodo === 'transferencia' ? '12345' : null,
            'total_declarado' => array_sum($detalles),
            'moneda' => 'CRC',
        ]);

        foreach ($detalles as $categoria => $monto) {
            SobreDetalle::create(['sobre_id' => $sobre->id, 'categoria' => $categoria, 'monto' => $monto]);
        }

        return $sobre;
    }
}

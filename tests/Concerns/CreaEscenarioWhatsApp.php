<?php

namespace Tests\Concerns;

use App\Models\Culto;
use App\Models\Persona;
use App\Models\Sobre;
use App\Models\SobreDetalle;
use App\Models\Tenant;
use App\Models\TenantCategory;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Escenario común de las pruebas de WhatsApp: iglesia con admin, categorías por defecto,
 * personas con teléfono y sobres con sus detalles. Reutiliza el escenario de asistencia.
 */
trait CreaEscenarioWhatsApp
{
    use CreaEscenarioAsistencia;

    protected function crearCategoriasPorDefecto(?int $tenantId = null): void
    {
        foreach (Tenant::defaultCategories() as $categoria) {
            TenantCategory::create($categoria + ['tenant_id' => $tenantId ?? $this->iglesia->id, 'activa' => true]);
        }
    }

    protected function crearPersona(string $nombre = 'Ana Mora', ?string $telefono = '8888-7777', ?int $tenantId = null): Persona
    {
        return Persona::create([
            'nombre' => $nombre,
            'telefono' => $telefono,
            'activo' => true,
            'tenant_id' => $tenantId ?? $this->iglesia->id,
        ]);
    }

    /**
     * Usuario de la iglesia con rol y teléfono. En una iglesia sin roles dinámicos (crearOtraIglesia)
     * queda solo con la columna rol de siempre.
     */
    protected function crearLider(string $slugRol, ?string $telefono, ?string $nombre = null, ?Tenant $iglesia = null): User
    {
        $iglesia ??= $this->iglesia;
        $rol = TenantRole::where('tenant_id', $iglesia->id)->where('slug', $slugRol)->first();
        $sufijo = Str::lower(Str::random(6));

        return User::create([
            'name' => $nombre ?? ucfirst($slugRol).' '.$sufijo,
            'email' => "{$slugRol}-{$sufijo}@{$iglesia->slug}.test",
            'password' => 'secreto-123',
            'rol' => $slugRol,
            'is_super_admin' => false,
            'tenant_id' => $iglesia->id,
            'tenant_role_id' => $rol?->id,
            'telefono' => $telefono,
        ]);
    }

    /** @param array<string, float|int> $detalles categoría => monto */
    protected function crearSobre(Culto $culto, ?Persona $persona, array $detalles, string $moneda = 'CRC', ?float $tipoCambio = null): Sobre
    {
        $sobre = Sobre::create([
            'culto_id' => $culto->id,
            'persona_id' => $persona?->id,
            'metodo_pago' => 'efectivo',
            'total_declarado' => array_sum($detalles),
            'moneda' => $moneda,
            'tipo_cambio_venta' => $tipoCambio,
        ]);

        foreach ($detalles as $categoria => $monto) {
            SobreDetalle::create(['sobre_id' => $sobre->id, 'categoria' => $categoria, 'monto' => $monto]);
        }

        return $sobre;
    }
}

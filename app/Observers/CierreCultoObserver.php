<?php

namespace App\Observers;

use App\Models\Culto;
use App\Models\Tenant;
use App\Services\Reportes\SincronizadorCompromisos;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Al cerrar un culto se sincronizan los compromisos de su mes (regla 2.4 de 04-DECISIONES.md): asi
 * el Dashboard de promesas queda al dia sin que ninguna pantalla escriba al consultar. Un error al
 * sincronizar no impide cerrar el culto (queda en el log y se arregla con el comando o el boton).
 */
class CierreCultoObserver
{
    public function updated(Culto $culto): void
    {
        if (! $culto->wasChanged('cerrado') || ! $culto->cerrado || ! $culto->fecha) {
            return;
        }

        try {
            $tenant = Tenant::find($culto->tenant_id);
            if ($tenant) {
                $fecha = $culto->fecha instanceof \DateTimeInterface ? $culto->fecha : \Carbon\Carbon::parse($culto->fecha);
                app(SincronizadorCompromisos::class)->sincronizar($tenant, (int) $fecha->format('Y'), (int) $fecha->format('n'));
            }
        } catch (Throwable $e) {
            Log::warning('No se pudieron sincronizar los compromisos al cerrar el culto '.$culto->id.': '.$e->getMessage());
        }
    }
}

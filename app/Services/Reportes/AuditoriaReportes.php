<?php

namespace App\Services\Reportes;

use App\Models\AuditLog;
use Throwable;

/**
 * Deja en audit_logs cada exportacion de un reporte (HU-R8): quien, cuando, que reporte, en que
 * formato, con que periodo y filtros, y cuantas personas. Se ve en Administracion > Auditoria.
 *
 * Un fallo al auditar no corta la descarga (igual que el middleware de auditoria): se reporta y se
 * sigue.
 */
class AuditoriaReportes
{
    public const ACCION = 'reportes.exportar';

    /** @param  array<string, mixed>  $extra */
    public function registrar(string $reporte, string $formato, PeriodoReporte $periodo, ?FiltroPersonas $filtro, int $personas, array $extra = []): void
    {
        try {
            $usuario = auth()->user();
            $peticion = request();

            AuditLog::create([
                'tenant_id' => $usuario?->tenant_id,
                'user_id' => $usuario?->getAuthIdentifier(),
                'user_name' => $usuario?->name,
                'user_email' => $usuario?->email,
                'ip_address' => $peticion->ip(),
                'user_agent' => substr((string) $peticion->userAgent(), 0, 255),
                'method' => $peticion->method(),
                'route' => $peticion->route()?->getName() ?? $peticion->path(),
                'action' => self::ACCION,
                'details' => json_encode([
                    'reporte' => $reporte,
                    'formato' => $formato,
                    'periodo' => $periodo->aArray(),
                    'personas' => $personas,
                    'filtro' => $filtro?->descripcion(),
                    'parametros' => $filtro?->aParametros(),
                ] + $extra, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}

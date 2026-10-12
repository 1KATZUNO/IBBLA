<?php

namespace App\Services\WhatsApp;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Deja constancia en el registro de auditoría de lo que se hace con WhatsApp (mismo formato
 * que usan RecuentoController y AuditLogMiddleware). Sirve para saber quién mandó el informe
 * de quién, ya que el enlace firmado se puede reenviar.
 */
class AuditoriaWhatsApp
{
    /**
     * @param  array<string, mixed>  $detalles
     */
    public static function registrar(Request $request, string $accion, string $ruta, array $detalles = []): AuditLog
    {
        $usuario = $request->user();

        return AuditLog::create([
            // Se ignora mientras audit_logs no tenga la columna (no es fillable); cuando exista, la fila queda de la iglesia.
            'tenant_id' => $usuario?->tenant_id,
            'user_id' => $usuario?->id,
            'user_name' => $usuario?->name,
            'user_email' => $usuario?->email,
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            'method' => $request->method(),
            'route' => $ruta,
            'action' => $accion,
            'details' => json_encode($detalles, JSON_UNESCAPED_UNICODE),
        ]);
    }
}

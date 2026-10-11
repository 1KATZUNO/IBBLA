<?php

namespace App\Models;

use App\Support\NavegadorResumen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /**
     * Eventos de acceso a la plataforma (Historial de ingresos). Los registra
     * App\Listeners\RegistrarAccesoSubscriber a partir de los eventos de autenticación
     * de Laravel. El resto de filas de audit_logs usa como `action` el nombre de la ruta.
     */
    public const ACCION_LOGIN = 'auth.login';

    public const ACCION_LOGOUT = 'auth.logout';

    public const ACCION_LOGIN_FALLIDO = 'auth.failed';

    public const ACCIONES_DE_ACCESO = [
        self::ACCION_LOGIN,
        self::ACCION_LOGOUT,
        self::ACCION_LOGIN_FALLIDO,
    ];

    protected $table = 'audit_logs';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'user_name',
        'user_email',
        'ip_address',
        'user_agent',
        'method',
        'route',
        'action',
        'details',
    ];

    /** Solo los eventos de acceso: ingresos, salidas e intentos fallidos. */
    public function scopeAccesos(Builder $query): Builder
    {
        return $query->whereIn('action', self::ACCIONES_DE_ACCESO);
    }

    /** Texto para mostrar de la acción ("Inicio de sesión"...). */
    public function getEtiquetaAccionAttribute(): string
    {
        return match ($this->action) {
            self::ACCION_LOGIN => 'Inicio de sesión',
            self::ACCION_LOGOUT => 'Cierre de sesión',
            self::ACCION_LOGIN_FALLIDO => 'Intento fallido',
            default => (string) $this->action,
        };
    }

    /** User-Agent resumido, p. ej. "Chrome 118 · Windows". */
    public function getNavegadorResumenAttribute(): string
    {
        return NavegadorResumen::etiqueta($this->user_agent);
    }
}

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

    /**
     * QE-013: atributo de la petición que indica que ya se escribió un registro semántico (con
     * registrar() o un create propio). El middleware genérico `audit` lo mira y no escribe otro.
     */
    public const ATRIBUTO_SEMANTICO = 'auditoria.semantica';

    /** true solo mientras el middleware genérico escribe su fila (esa no cuenta como semántica). */
    public static bool $escribiendoGenerico = false;

    protected $table = 'audit_logs';

    protected static function booted(): void
    {
        static::created(function () {
            if (! static::$escribiendoGenerico && app()->bound('request')) {
                request()->attributes->set(self::ATRIBUTO_SEMANTICO, true);
            }
        });
    }

    /**
     * QE-013: lo que se ve por defecto en la pantalla de auditoría: las acciones (sin visitas GET ni
     * los ingresos/salidas, que tienen su propio historial).
     */
    public function scopeSoloAcciones(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('method')->orWhere('method', '<>', 'GET'))
            ->where(fn ($q) => $q->whereNull('action')->orWhereNotIn('action', self::ACCIONES_DE_ACCESO));
    }

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

    /**
     * Deja una accion de negocio en la auditoria con el usuario y la peticion actuales
     * (quien, desde donde, que y con que datos). Para las acciones que la auditoria automatica
     * por ruta no describe bien: borrar un culto, corregir firmas, asignar persona a un sobre...
     */
    public static function registrar(string $accion, array $detalles = [], ?string $ruta = null): self
    {
        $request = request();
        $user = $request->user();

        return self::create([
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'method' => $request->method(),
            'route' => $ruta ?? $request->route()?->getName(),
            'action' => $accion,
            'details' => json_encode($detalles, JSON_UNESCAPED_UNICODE),
        ]);
    }

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

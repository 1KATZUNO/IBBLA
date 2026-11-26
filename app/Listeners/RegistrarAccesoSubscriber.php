<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;

/**
 * Historial de ingresos: guarda en audit_logs quién entró, cuándo y desde dónde.
 *
 * Escucha los eventos de autenticación de Laravel, de modo que cubre todas las vías de
 * acceso (formulario de login, login de la app de asistencia, remember-me, invitado) sin
 * tocar cada controlador. Se registra en AppServiceProvider con Event::subscribe().
 *
 * Los métodos NO se llaman handle*: Laravel descubre solos los listeners de app/Listeners
 * cuyo método empiece por "handle" y los registraría por segunda vez.
 */
class RegistrarAccesoSubscriber
{
    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'alIniciarSesion',
            Logout::class => 'alCerrarSesion',
            Failed::class => 'alFallarIntento',
        ];
    }

    public function alIniciarSesion(Login $evento): void
    {
        $this->registrar(AuditLog::ACCION_LOGIN, $evento->user, null, [
            'guard' => $evento->guard,
            'remember' => (bool) $evento->remember,
        ]);
    }

    public function alCerrarSesion(Logout $evento): void
    {
        // $evento->user puede ser null si se llama logout() sin sesión activa: no hay a quién registrar.
        if ($evento->user === null) {
            return;
        }

        $this->registrar(AuditLog::ACCION_LOGOUT, $evento->user, null, ['guard' => $evento->guard]);
    }

    public function alFallarIntento(Failed $evento): void
    {
        // Solo el correo o el usuario (Tarjetas QR): las credenciales traen la contraseña y jamás se guarda.
        $correo = is_array($evento->credentials) ? ($evento->credentials['email'] ?? $evento->credentials['usuario'] ?? null) : null;

        $this->registrar(AuditLog::ACCION_LOGIN_FALLIDO, $evento->user, is_string($correo) ? $correo : null, [
            'guard' => $evento->guard,
            // Distingue "contraseña incorrecta" de "ese correo no existe" sin guardar la contraseña.
            'usuario_existe' => $evento->user !== null,
        ]);
    }

    private function registrar(string $accion, ?Authenticatable $usuario, ?string $correo, array $detalles): void
    {
        // Un fallo al auditar nunca debe impedir que alguien entre (ni salga): se reporta y se sigue.
        try {
            $peticion = request();

            AuditLog::create([
                // Dentro del evento Login el guard todavía no tiene al usuario puesto, así que la
                // iglesia se toma del propio usuario del evento y no de auth()->user().
                'tenant_id' => data_get($usuario, 'tenant_id'),
                'user_id' => $usuario?->getAuthIdentifier(),
                'user_name' => data_get($usuario, 'name'),
                'user_email' => data_get($usuario, 'email') ?? data_get($usuario, 'usuario') ?? ($correo !== null ? Str::limit($correo, 255, '') : null),
                'ip_address' => $peticion->ip(),
                'user_agent' => Str::limit((string) $peticion->userAgent(), 255, ''),
                'method' => $peticion->method(),
                'route' => $peticion->route()?->getName() ?? $peticion->path(),
                'action' => $accion,
                'details' => json_encode($detalles),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

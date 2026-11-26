<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'usuario',
        'telefono',
        'password',
        'rol',
        'tenant_id',
        'tenant_role_id',
        'clase_asistencia_id',
        'is_super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'password_visible',
    ];

    /**
     * Tarjetas QR: true cuando establecerContrasena() acaba de poner la copia visible, para que el guardado no
     * la confunda con un cambio de contrasena hecho por otra via (que deja la copia vacia).
     */
    private bool $copiaVisibleEstablecida = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            // Copia de la contrasena que puede ver el administrador (solo cuentas miembro, ver config/ibbla.php)
            'password_visible' => 'encrypted',
            'debe_crear_contrasena' => 'boolean',
        ];
    }

    /**
     * La copia visible de la contrasena nunca queda guardada para una cuenta que no sea de miembro ni con el
     * interruptor apagado, y se vacia si la contrasena cambia por una via que no la actualiza (recuperar por
     * correo, por ejemplo): asi el administrador nunca ve una contrasena vieja.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('tenant_role_id')) {
                $user->unsetRelation('tenantRole');
            }

            if ($user->isDirty('password') && ! $user->copiaVisibleEstablecida) {
                $user->password_visible = null;
            }

            if ($user->getAttributes()['password_visible'] ?? null) {
                if (! self::contrasenaVisibleActiva() || ! $user->esCuentaMiembro()) {
                    $user->password_visible = null;
                }
            }

            $user->copiaVisibleEstablecida = false;
        });
    }

    // --- Tarjetas QR de acceso: usuario o correo, contrasena visible para el administrador ---

    /** Interruptor de configuracion (config/ibbla.php), encendido por defecto. */
    public static function contrasenaVisibleActiva(): bool
    {
        return (bool) config('ibbla.tarjetas.contrasena_visible', true);
    }

    /** Con lo que la persona inicia sesion: su correo o, si no tiene, su nombre de usuario. */
    public function identificador(): string
    {
        return (string) ($this->email ?: $this->usuario);
    }

    /**
     * Cuenta de rol miembro (solo Mi perfil): la unica a la que se le guarda la copia visible de la contrasena
     * y a la que el administrador puede restablecer el acceso desde la tarjeta. Nunca administrador, tesorero,
     * asistente, servidor ni super admin.
     */
    public function esCuentaMiembro(): bool
    {
        return ! $this->isSuperAdmin()
            && $this->isMiembro()
            && ! $this->isAdmin()
            && ! $this->canAccessRecuento()
            && ! $this->canAccessAsistencia()
            && ! $this->hasPermission('marcar_asistencia');
    }

    /**
     * Cambia la contrasena (se guarda con su hash normal) y, si corresponde, su copia cifrada visible para el
     * administrador. No guarda: el que llama hace save(). Quita la marca de "debe crear contrasena".
     */
    public function establecerContrasena(string $plana): void
    {
        $this->password = $plana;
        $this->debe_crear_contrasena = false;
        $this->password_visible = (self::contrasenaVisibleActiva() && $this->esCuentaMiembro()) ? $plana : null;
        $this->copiaVisibleEstablecida = true;
    }

    /** La contrasena que puede ver el administrador, o null si no hay copia o el interruptor esta apagado. */
    public function contrasenaVisible(): ?string
    {
        if (! self::contrasenaVisibleActiva() || ! $this->esCuentaMiembro()) {
            return null;
        }

        return $this->password_visible;
    }

    // --- Legacy role helpers (backward-compatible, check tenantRole first) ---

    public function isAdmin(): bool
    {
        return $this->hasPermission('admin') || $this->rol === 'admin';
    }

    public function isTesorero(): bool
    {
        return $this->hasPermission('recuento') || $this->rol === 'tesorero';
    }

    public function isAsistente(): bool
    {
        return $this->hasPermission('asistencia') || $this->rol === 'asistente';
    }

    public function isInvitado(): bool
    {
        if ($this->tenantRole) {
            return empty($this->tenantRole->permisos);
        }

        return $this->rol === 'invitado';
    }

    public function isMiembro(): bool
    {
        if ($this->tenantRole) {
            $permisos = $this->tenantRole->permisos ?? [];

            // Miembro: only has mi_perfil permission
            return ! empty($permisos['mi_perfil']) && empty($permisos['recuento']) && empty($permisos['asistencia']) && empty($permisos['admin']);
        }

        return $this->rol === 'miembro';
    }

    public function canAccessRecuento(): bool
    {
        return $this->hasPermission('recuento') || in_array($this->rol, ['admin', 'tesorero']);
    }

    public function canAccessAsistencia(): bool
    {
        return $this->hasPermission('asistencia') || in_array($this->rol, ['admin', 'asistente']);
    }

    public function canAccessAdmin(): bool
    {
        return $this->hasPermission('admin') || $this->rol === 'admin';
    }

    public function canAccessDashboard(): bool
    {
        return $this->hasPermission('dashboard') || in_array($this->rol, ['admin', 'tesorero']);
    }

    public function canAccessReportes(): bool
    {
        return $this->hasPermission('reportes') || in_array($this->rol, ['admin', 'tesorero']);
    }

    public function canAccessMiPerfil(): bool
    {
        return $this->hasPermission('mi_perfil') || in_array($this->rol, ['miembro', 'servidor']);
    }

    // --- Reglas de pantalla (docs/ROLES.md): una sola respuesta para menu, Principal y rutas ---

    /**
     * Pantalla de inicio de cada rol (QA-011): el miembro va a Mi perfil; los demas (servidor incluido,
     * que marca su asistencia desde Principal) a Principal.
     * Se usa al iniciar sesion, al abrir "/" o la app instalada, y cuando alguien con sesion abre
     * /login o /register (antes iba a /dashboard y daba 403 al asistente y al miembro).
     */
    public function rutaDeInicio(): string
    {
        return $this->isMiembro() && ! $this->isAdmin() && ! $this->hasPermission('marcar_asistencia') ? 'mi-perfil.index' : 'principal';
    }

    /**
     * "Yo / Mi perfil" (QA-014): solo si el usuario tiene una persona vinculada (sus sobres, promesas
     * y estado de cuenta son los de esa persona). Vale tambien para el administrador, que es hermano
     * de la iglesia. Sin persona no se muestra el acceso.
     */
    public function tieneMiPerfil(): bool
    {
        return $this->persona()->exists();
    }

    /** Dashboard de asistencia: admin, tesorero y asistente (decision 2.7 de 04-DECISIONES.md, QA-015). */
    public function puedeVerDashboardAsistencia(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin() || $this->canAccessRecuento() || $this->canAccessAsistencia();
    }

    /**
     * Alta rapida de personas (nombre y telefono) sin entrar al modulo Personas: admin y tesoreria
     * (decision del cliente 13/10: el tesorero no ve Personas pero si agrega personas rapido).
     */
    public function puedeAltaRapidaPersonas(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin() || $this->canAccessRecuento();
    }

    // --- Dynamic permission system ---

    public function hasPermission(string $permission): bool
    {
        if ($this->tenantRole) {
            return $this->tenantRole->hasPermission($permission);
        }

        // Legacy fallback: map permissions to roles
        $legacyMap = [
            'admin' => ['admin'],
            'recuento' => ['admin', 'tesorero'],
            'asistencia' => ['admin', 'asistente'],
            'dashboard' => ['admin', 'tesorero'],
            'reportes' => ['admin', 'tesorero'],
            'mi_perfil' => ['miembro', 'servidor'],
            'marcar_asistencia' => ['servidor'],
        ];

        if (isset($legacyMap[$permission])) {
            return in_array($this->rol, $legacyMap[$permission]);
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    public function getRolNombreAttribute(): string
    {
        if ($this->tenantRole) {
            return $this->tenantRole->nombre;
        }
        // Legacy fallback
        $map = [
            'admin' => 'Administrador',
            'tesorero' => 'Tesorero',
            'asistente' => 'Asistente',
            'miembro' => 'Miembro',
            'servidor' => 'Servidor',
            'invitado' => 'Invitado',
        ];

        return $map[$this->rol] ?? ucfirst($this->rol ?? 'Sin rol');
    }

    // --- Relationships ---

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tenantRole()
    {
        return $this->belongsTo(TenantRole::class);
    }

    public function persona()
    {
        return $this->hasOne(Persona::class);
    }

    /** Activación de WhatsApp (CallMeBot) para los avisos del sistema: su número, su clave y su consentimiento. */
    public function activacionWhatsApp()
    {
        return $this->hasOne(ActivacionWhatsApp::class);
    }
}

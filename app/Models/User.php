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
        'password',
        'rol',
        'tenant_id',
        'tenant_role_id',
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
    ];

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
        ];
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
}

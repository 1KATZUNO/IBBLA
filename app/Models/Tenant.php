<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    protected $fillable = [
        'nombre', 'siglas', 'slug',
        'logo_path', 'logo_pdf_path', 'favicon_path',
        'color_theme', 'use_custom_colors',
        'color_50', 'color_100', 'color_200', 'color_300', 'color_400',
        'color_500', 'color_600', 'color_700', 'color_800', 'color_900',
        'timezone', 'locale', 'moneda_codigo', 'moneda_simbolo',
        'direccion', 'telefono', 'email_contacto', 'sitio_web',
        'redes_sociales',
        'activo', 'max_usuarios',
    ];

    protected function casts(): array
    {
        return [
            'redes_sociales' => 'array',
            'activo' => 'boolean',
            'use_custom_colors' => 'boolean',
        ];
    }

    public const COLOR_THEMES = [
        'blue' => [
            'label' => 'Azul',
            '50' => '#eff6ff', '100' => '#dbeafe', '200' => '#bfdbfe', '300' => '#93c5fd',
            '400' => '#60a5fa', '500' => '#3b82f6', '600' => '#2563eb', '700' => '#1d4ed8',
            '800' => '#1e40af', '900' => '#1e3a8a',
        ],
        'red' => [
            'label' => 'Rojo',
            '50' => '#fef2f2', '100' => '#fee2e2', '200' => '#fecaca', '300' => '#fca5a5',
            '400' => '#f87171', '500' => '#ef4444', '600' => '#dc2626', '700' => '#b91c1c',
            '800' => '#991b1b', '900' => '#7f1d1d',
        ],
        'green' => [
            'label' => 'Verde',
            '50' => '#f0fdf4', '100' => '#dcfce7', '200' => '#bbf7d0', '300' => '#86efac',
            '400' => '#4ade80', '500' => '#22c55e', '600' => '#16a34a', '700' => '#15803d',
            '800' => '#166534', '900' => '#14532d',
        ],
        'purple' => [
            'label' => 'Morado',
            '50' => '#faf5ff', '100' => '#f3e8ff', '200' => '#e9d5ff', '300' => '#d8b4fe',
            '400' => '#c084fc', '500' => '#a855f7', '600' => '#9333ea', '700' => '#7e22ce',
            '800' => '#6b21a8', '900' => '#581c87',
        ],
        'orange' => [
            'label' => 'Naranja',
            '50' => '#fff7ed', '100' => '#ffedd5', '200' => '#fed7aa', '300' => '#fdba74',
            '400' => '#fb923c', '500' => '#f97316', '600' => '#ea580c', '700' => '#c2410c',
            '800' => '#9a3412', '900' => '#7c2d12',
        ],
        'teal' => [
            'label' => 'Teal',
            '50' => '#f0fdfa', '100' => '#ccfbf1', '200' => '#99f6e4', '300' => '#5eead4',
            '400' => '#2dd4bf', '500' => '#14b8a6', '600' => '#0d9488', '700' => '#0f766e',
            '800' => '#115e59', '900' => '#134e4a',
        ],
        'indigo' => [
            'label' => 'Indigo',
            '50' => '#eef2ff', '100' => '#e0e7ff', '200' => '#c7d2fe', '300' => '#a5b4fc',
            '400' => '#818cf8', '500' => '#6366f1', '600' => '#4f46e5', '700' => '#4338ca',
            '800' => '#3730a3', '900' => '#312e81',
        ],
        'pink' => [
            'label' => 'Rosa',
            '50' => '#fdf2f8', '100' => '#fce7f3', '200' => '#fbcfe8', '300' => '#f9a8d4',
            '400' => '#f472b6', '500' => '#ec4899', '600' => '#db2777', '700' => '#be185d',
            '800' => '#9d174d', '900' => '#831843',
        ],
    ];

    public function getColorsAttribute(): array
    {
        if ($this->use_custom_colors) {
            return [
                '50' => $this->color_50, '100' => $this->color_100, '200' => $this->color_200,
                '300' => $this->color_300, '400' => $this->color_400, '500' => $this->color_500,
                '600' => $this->color_600, '700' => $this->color_700, '800' => $this->color_800,
                '900' => $this->color_900,
            ];
        }

        $theme = self::COLOR_THEMES[$this->color_theme] ?? self::COLOR_THEMES['blue'];

        return collect($theme)->except('label')->all();
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->logo_path
            ? Storage::url($this->logo_path)
            : asset('images/Logo.png');
    }

    public function getFaviconUrlAttribute(): string
    {
        return $this->favicon_path
            ? Storage::url($this->favicon_path)
            : $this->logo_url;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function emailDomains(): HasMany
    {
        return $this->hasMany(TenantEmailDomain::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(TenantCategory::class);
    }

    public function serviceTypes(): HasMany
    {
        return $this->hasMany(TenantServiceType::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(TenantRole::class);
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }

    public static function defaultServiceTypes(): array
    {
        return [
            ['nombre' => 'Domingo AM', 'slug' => 'domingo', 'orden' => 1],
            ['nombre' => 'Domingo PM', 'slug' => 'domingo_pm', 'orden' => 2],
            ['nombre' => 'Miercoles', 'slug' => 'miercoles', 'orden' => 3],
            ['nombre' => 'Sabado', 'slug' => 'sabado', 'orden' => 4],
            ['nombre' => 'Especial', 'slug' => 'especial', 'orden' => 5],
        ];
    }

    public static function defaultRoles(): array
    {
        return [
            ['nombre' => 'Administrador', 'slug' => 'admin', 'permisos' => ['recuento' => true, 'asistencia' => true, 'admin' => true, 'mi_perfil' => true, 'dashboard' => true, 'reportes' => true], 'es_default' => false, 'orden' => 1],
            ['nombre' => 'Tesorero', 'slug' => 'tesorero', 'permisos' => ['recuento' => true, 'dashboard' => true, 'reportes' => true, 'mi_perfil' => true], 'es_default' => false, 'orden' => 2],
            ['nombre' => 'Asistente', 'slug' => 'asistente', 'permisos' => ['asistencia' => true, 'mi_perfil' => true], 'es_default' => false, 'orden' => 3],
            ['nombre' => 'Miembro', 'slug' => 'miembro', 'permisos' => ['mi_perfil' => true], 'es_default' => true, 'orden' => 4],
            ['nombre' => 'Servidor', 'slug' => 'servidor', 'permisos' => ['mi_perfil' => true, 'marcar_asistencia' => true], 'es_default' => false, 'orden' => 5],
            ['nombre' => 'Invitado', 'slug' => 'invitado', 'permisos' => [], 'es_default' => false, 'orden' => 6],
        ];
    }

    public static function defaultCategories(): array
    {
        return [
            ['nombre' => 'Diezmo', 'slug' => 'diezmo', 'tipo' => 'ambos', 'excluir_de_promesas' => true, 'color' => '#3b82f6', 'orden' => 1],
            ['nombre' => 'Ofrenda Especial', 'slug' => 'ofrenda_especial', 'tipo' => 'ingreso', 'excluir_de_promesas' => true, 'color' => '#8b5cf6', 'orden' => 2],
            ['nombre' => 'Misiones', 'slug' => 'misiones', 'tipo' => 'ambos', 'excluir_de_promesas' => false, 'color' => '#10b981', 'orden' => 3],
            ['nombre' => 'Seminario', 'slug' => 'seminario', 'tipo' => 'ambos', 'excluir_de_promesas' => false, 'color' => '#f59e0b', 'orden' => 4],
            ['nombre' => 'Construccion', 'slug' => 'construccion', 'tipo' => 'ambos', 'excluir_de_promesas' => false, 'color' => '#ef4444', 'orden' => 5],
            ['nombre' => 'CAMPA', 'slug' => 'campa', 'tipo' => 'ambos', 'excluir_de_promesas' => false, 'color' => '#ec4899', 'orden' => 6],
            ['nombre' => 'Prestamo', 'slug' => 'prestamo', 'tipo' => 'ambos', 'excluir_de_promesas' => false, 'color' => '#6366f1', 'orden' => 7],
            ['nombre' => 'Micro', 'slug' => 'micro', 'tipo' => 'ambos', 'excluir_de_promesas' => false, 'color' => '#14b8a6', 'orden' => 8],
        ];
    }
}

<?php

if (! function_exists('tenant')) {
    /**
     * Get the current tenant instance.
     */
    function tenant(): ?\App\Models\Tenant
    {
        if (app()->bound('current_tenant')) {
            return app('current_tenant');
        }

        if (auth()->check() && auth()->user()->tenant_id) {
            return auth()->user()->tenant;
        }

        return null;
    }
}

if (! function_exists('iglesia_unica')) {
    /**
     * La UNICA iglesia activa de la instalacion, o null si hay varias (o ninguna). Una instalacion de una
     * sola iglesia (ibbsc.co.uk) muestra su marca aunque nadie haya iniciado sesion; una de varias iglesias
     * (ibbla.eu) no puede saber cual es antes del login. Se resuelve una vez por peticion.
     */
    function iglesia_unica(): ?\App\Models\Tenant
    {
        if (! app()->bound('iglesia_unica')) {
            try {
                $activas = \App\Models\Tenant::query()->where('activo', true)->limit(2)->get();
                $unica = $activas->count() === 1 ? $activas->first() : null;
            } catch (\Throwable) {
                $unica = null; // sin base o sin tablas (instalacion a medias): marca generica
            }
            app()->instance('iglesia_unica', $unica ?? false);
        }

        return app('iglesia_unica') ?: null;
    }
}

if (! function_exists('iglesia_de_marca')) {
    /**
     * La iglesia cuya marca se muestra: la de la sesion si la hay; si no, la unica de la instalacion.
     * Null = marca generica (pantalla de entrada de una instalacion con varias iglesias).
     */
    function iglesia_de_marca(): ?\App\Models\Tenant
    {
        return tenant() ?? iglesia_unica();
    }
}

if (! function_exists('url_manifiesto')) {
    /**
     * El manifiesto de la app instalable: el archivo de siempre, salvo en una instalacion de una sola
     * iglesia, que lleva su nombre y su logo (ManifiestoController).
     */
    function url_manifiesto(): string
    {
        return iglesia_unica() ? route('manifiesto') : asset('manifest.json');
    }
}

if (! function_exists('tenant_title')) {
    /**
     * Construye el título de página con las siglas del tenant actual.
     * Usado en @section('title', tenant_title('Asistencia')) en blades.
     * Sin sesion (rutas públicas / login) usa la iglesia de la instalacion si es una sola; si no, 'IBBLA'.
     */
    function tenant_title(string $suffix = ''): string
    {
        $siglas = iglesia_de_marca()?->siglas ?? 'IBBLA';

        return $suffix === '' ? $siglas : $siglas.' - '.$suffix;
    }
}

if (! function_exists('tenant_pdf_data')) {
    /**
     * Get tenant data for PDF views (nombre, siglas, color, logo base64).
     */
    function tenant_pdf_data(): array
    {
        $t = tenant();
        $tenantNombre = $t ? $t->nombre : 'Sistema de Administración';
        $tenantSiglas = $t ? $t->siglas : 'Admin';
        $tenantColor = $t ? ($t->colors['600'] ?? '#3b82f6') : '#3b82f6';

        $logoFile = null;
        if ($t && $t->logo_pdf_path && file_exists(storage_path('app/public/'.$t->logo_pdf_path))) {
            $logoFile = storage_path('app/public/'.$t->logo_pdf_path);
        } elseif ($t && $t->logo_path && file_exists(storage_path('app/public/'.$t->logo_path))) {
            $logoFile = storage_path('app/public/'.$t->logo_path);
        } else {
            $logoFile = public_path('images/Logo2.png');
        }
        $tenantLogoBase64 = base64_encode(file_get_contents($logoFile));

        return compact('tenantNombre', 'tenantSiglas', 'tenantColor', 'tenantLogoBase64');
    }
}

if (! function_exists('tenant_categories')) {
    /**
     * Get active categories for the current tenant.
     * Falls back to default categories if no tenant is set.
     */
    function tenant_categories(array $filters = []): \Illuminate\Support\Collection
    {
        $t = tenant();

        if ($t) {
            $query = $t->categories()->where('activa', true);

            if (isset($filters['excluir_de_promesas'])) {
                $query->where('excluir_de_promesas', $filters['excluir_de_promesas']);
            }
            if (isset($filters['es_ofrenda_suelta'])) {
                $query->where('es_ofrenda_suelta', $filters['es_ofrenda_suelta']);
            }

            return $query->orderBy('orden')->get();
        }

        // Fallback: build collection from default categories
        $defaults = collect(\App\Models\Tenant::defaultCategories())->map(function ($cat) {
            return new \App\Models\TenantCategory($cat + ['activa' => true]);
        });

        if (isset($filters['excluir_de_promesas'])) {
            $defaults = $defaults->where('excluir_de_promesas', $filters['excluir_de_promesas']);
        }
        if (isset($filters['es_ofrenda_suelta'])) {
            $defaults = $defaults->where('es_ofrenda_suelta', $filters['es_ofrenda_suelta']);
        }

        return $defaults->sortBy('orden')->values();
    }
}

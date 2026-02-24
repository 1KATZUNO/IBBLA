<?php

if (!function_exists('tenant')) {
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

if (!function_exists('tenant_pdf_data')) {
    /**
     * Get tenant data for PDF views (nombre, siglas, color, logo base64).
     */
    function tenant_pdf_data(): array
    {
        $t = tenant();
        $tenantNombre = $t ? $t->nombre : 'Sistema de Administracion';
        $tenantSiglas = $t ? $t->siglas : 'Admin';
        $tenantColor = $t ? ($t->colors['600'] ?? '#3b82f6') : '#3b82f6';

        $logoFile = null;
        if ($t && $t->logo_pdf_path && file_exists(storage_path('app/public/' . $t->logo_pdf_path))) {
            $logoFile = storage_path('app/public/' . $t->logo_pdf_path);
        } elseif ($t && $t->logo_path && file_exists(storage_path('app/public/' . $t->logo_path))) {
            $logoFile = storage_path('app/public/' . $t->logo_path);
        } else {
            $logoFile = public_path('images/Logo2.png');
        }
        $tenantLogoBase64 = base64_encode(file_get_contents($logoFile));

        return compact('tenantNombre', 'tenantSiglas', 'tenantColor', 'tenantLogoBase64');
    }
}

if (!function_exists('tenant_categories')) {
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

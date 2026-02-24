<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantCategory;
use App\Models\TenantEmailDomain;
use App\Models\TenantRole;
use App\Models\TenantServiceType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $query = Tenant::withCount(['users', 'categories', 'emailDomains']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('siglas', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->has('estado') && $request->estado !== '') {
            $query->where('activo', $request->estado === 'activa');
        }

        $tenants = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return view('super-admin.tenants.index', compact('tenants'));
    }

    public function create()
    {
        $themes = Tenant::COLOR_THEMES;
        $currencies = [
            'CRC' => ['codigo' => 'CRC', 'simbolo' => '₡', 'nombre' => 'Colon costarricense'],
            'USD' => ['codigo' => 'USD', 'simbolo' => '$', 'nombre' => 'Dolar estadounidense'],
            'EUR' => ['codigo' => 'EUR', 'simbolo' => '€', 'nombre' => 'Euro'],
        ];
        $timezones = [
            'America/Costa_Rica' => 'America/Costa Rica (UTC-6)',
            'America/Guatemala' => 'America/Guatemala (UTC-6)',
            'America/Mexico_City' => 'America/Mexico City (UTC-6)',
            'America/Panama' => 'America/Panama (UTC-5)',
            'America/Bogota' => 'America/Bogota (UTC-5)',
            'America/Lima' => 'America/Lima (UTC-5)',
            'America/New_York' => 'America/New York (UTC-5)',
            'America/Chicago' => 'America/Chicago (UTC-6)',
            'America/Los_Angeles' => 'America/Los Angeles (UTC-8)',
            'America/Argentina/Buenos_Aires' => 'America/Buenos Aires (UTC-3)',
            'America/Sao_Paulo' => 'America/Sao Paulo (UTC-3)',
            'Europe/Madrid' => 'Europe/Madrid (UTC+1)',
        ];

        return view('super-admin.tenants.create', compact('themes', 'currencies', 'timezones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'siglas' => 'required|string|max:20',
            'dominio' => 'required|string|max:255|unique:tenant_email_domains,dominio',
            'color_theme' => 'required|string|in:' . implode(',', array_keys(Tenant::COLOR_THEMES)),
            'moneda_codigo' => 'required|string|in:CRC,USD,EUR',
            'timezone' => 'required|string|max:50',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:6',
            'admin_name' => 'required|string|max:255',
        ]);

        $monedas = [
            'CRC' => '₡',
            'USD' => '$',
            'EUR' => '€',
        ];

        $slug = Str::slug($validated['siglas']);
        $originalSlug = $slug;
        $counter = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        // Create tenant
        $tenant = Tenant::create([
            'nombre' => $validated['nombre'],
            'siglas' => strtoupper($validated['siglas']),
            'slug' => $slug,
            'color_theme' => $validated['color_theme'],
            'moneda_codigo' => $validated['moneda_codigo'],
            'moneda_simbolo' => $monedas[$validated['moneda_codigo']],
            'timezone' => $validated['timezone'],
            'activo' => true,
        ]);

        // Create email domain
        TenantEmailDomain::create([
            'tenant_id' => $tenant->id,
            'dominio' => $validated['dominio'],
            'principal' => true,
            'activo' => true,
        ]);

        // Create default categories
        foreach (Tenant::defaultCategories() as $cat) {
            TenantCategory::create(array_merge($cat, ['tenant_id' => $tenant->id]));
        }

        // Create default service types
        foreach (Tenant::defaultServiceTypes() as $st) {
            TenantServiceType::create(array_merge($st, ['tenant_id' => $tenant->id]));
        }

        // Create default roles
        $roles = [];
        foreach (Tenant::defaultRoles() as $role) {
            $roles[$role['slug']] = TenantRole::create(array_merge($role, ['tenant_id' => $tenant->id]));
        }

        // Create admin user
        User::create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'rol' => 'admin',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $roles['admin']->id,
            'is_super_admin' => false,
        ]);

        return redirect()->route('super-admin.tenants.index')
            ->with('success', "Iglesia '{$tenant->nombre}' creada exitosamente con su usuario administrador.");
    }

    public function edit(Tenant $tenant)
    {
        $themes = Tenant::COLOR_THEMES;
        $currencies = [
            'CRC' => ['codigo' => 'CRC', 'simbolo' => '₡', 'nombre' => 'Colon costarricense'],
            'USD' => ['codigo' => 'USD', 'simbolo' => '$', 'nombre' => 'Dolar estadounidense'],
            'EUR' => ['codigo' => 'EUR', 'simbolo' => '€', 'nombre' => 'Euro'],
        ];
        $timezones = [
            'America/Costa_Rica' => 'America/Costa Rica (UTC-6)',
            'America/Guatemala' => 'America/Guatemala (UTC-6)',
            'America/Mexico_City' => 'America/Mexico City (UTC-6)',
            'America/Panama' => 'America/Panama (UTC-5)',
            'America/Bogota' => 'America/Bogota (UTC-5)',
            'America/Lima' => 'America/Lima (UTC-5)',
            'America/New_York' => 'America/New York (UTC-5)',
            'America/Chicago' => 'America/Chicago (UTC-6)',
            'America/Los_Angeles' => 'America/Los Angeles (UTC-8)',
            'America/Argentina/Buenos_Aires' => 'America/Buenos Aires (UTC-3)',
            'America/Sao_Paulo' => 'America/Sao Paulo (UTC-3)',
            'Europe/Madrid' => 'Europe/Madrid (UTC+1)',
        ];

        return view('super-admin.tenants.edit', compact('tenant', 'themes', 'currencies', 'timezones'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'siglas' => 'required|string|max:20',
            'moneda_codigo' => 'required|string|in:CRC,USD,EUR',
            'timezone' => 'required|string|max:50',
            'direccion' => 'nullable|string|max:500',
            'telefono' => 'nullable|string|max:30',
            'email_contacto' => 'nullable|email|max:255',
            'sitio_web' => 'nullable|string|max:500',
            'redes_instagram' => 'nullable|string|max:500',
            'redes_facebook' => 'nullable|string|max:500',
            'redes_youtube' => 'nullable|string|max:500',
        ]);

        $monedas = [
            'CRC' => '₡',
            'USD' => '$',
            'EUR' => '€',
        ];

        $redes = array_filter([
            'instagram' => $validated['redes_instagram'] ?? null,
            'facebook' => $validated['redes_facebook'] ?? null,
            'youtube' => $validated['redes_youtube'] ?? null,
        ]);

        $tenant->update([
            'nombre' => $validated['nombre'],
            'siglas' => strtoupper($validated['siglas']),
            'moneda_codigo' => $validated['moneda_codigo'],
            'moneda_simbolo' => $monedas[$validated['moneda_codigo']],
            'timezone' => $validated['timezone'],
            'direccion' => $validated['direccion'],
            'telefono' => $validated['telefono'],
            'email_contacto' => $validated['email_contacto'],
            'sitio_web' => $validated['sitio_web'],
            'redes_sociales' => !empty($redes) ? $redes : null,
        ]);

        return redirect()->route('super-admin.tenants.index')
            ->with('success', "Iglesia '{$tenant->nombre}' actualizada exitosamente.");
    }

    public function destroy(Tenant $tenant)
    {
        $nombre = $tenant->nombre;
        $tenant->delete();

        return redirect()->route('super-admin.tenants.index')
            ->with('success', "Iglesia '{$nombre}' eliminada exitosamente.");
    }

    public function toggle(Tenant $tenant)
    {
        $tenant->update(['activo' => !$tenant->activo]);
        $estado = $tenant->activo ? 'activada' : 'desactivada';

        return redirect()->back()
            ->with('success', "Iglesia '{$tenant->nombre}' {$estado} exitosamente.");
    }

    // ---- Branding ----

    public function branding(Tenant $tenant)
    {
        $themes = Tenant::COLOR_THEMES;
        return view('super-admin.tenants.branding', compact('tenant', 'themes'));
    }

    public function updateBranding(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'color_theme' => 'required|string|in:' . implode(',', array_keys(Tenant::COLOR_THEMES)),
            'logo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($tenant->logo_path) {
                Storage::disk('public')->delete($tenant->logo_path);
            }
            $path = $request->file('logo')->store("tenants/{$tenant->id}", 'public');
            $tenant->logo_path = $path;
        }

        $tenant->color_theme = $validated['color_theme'];
        $tenant->save();

        return redirect()->route('super-admin.tenants.branding', $tenant)
            ->with('success', 'Branding actualizado exitosamente.');
    }

    // ---- Categories ----

    public function categories(Tenant $tenant)
    {
        $categories = $tenant->categories()->orderBy('orden')->get();
        return view('super-admin.tenants.categories', compact('tenant', 'categories'));
    }

    public function storeCategory(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|in:ingreso,compromiso,ambos',
            'excluir_de_promesas' => 'boolean',
            'color' => 'nullable|string|max:7',
            'orden' => 'integer|min:0',
        ]);

        $slug = Str::slug($validated['nombre'], '_');
        $originalSlug = $slug;
        $counter = 1;
        while ($tenant->categories()->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '_' . $counter++;
        }

        $tenant->categories()->create([
            'nombre' => $validated['nombre'],
            'slug' => $slug,
            'tipo' => $validated['tipo'],
            'excluir_de_promesas' => $validated['excluir_de_promesas'] ?? false,
            'color' => $validated['color'] ?? null,
            'orden' => $validated['orden'] ?? 0,
        ]);

        return redirect()->route('super-admin.tenants.categories', $tenant)
            ->with('success', 'Categoria creada exitosamente.');
    }

    public function updateCategory(Request $request, Tenant $tenant, TenantCategory $category)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|in:ingreso,compromiso,ambos',
            'excluir_de_promesas' => 'boolean',
            'color' => 'nullable|string|max:7',
            'orden' => 'integer|min:0',
        ]);

        $category->update([
            'nombre' => $validated['nombre'],
            'tipo' => $validated['tipo'],
            'excluir_de_promesas' => $validated['excluir_de_promesas'] ?? false,
            'color' => $validated['color'] ?? null,
            'orden' => $validated['orden'] ?? 0,
        ]);

        return redirect()->route('super-admin.tenants.categories', $tenant)
            ->with('success', 'Categoria actualizada exitosamente.');
    }

    public function destroyCategory(Tenant $tenant, TenantCategory $category)
    {
        $category->delete();

        return redirect()->route('super-admin.tenants.categories', $tenant)
            ->with('success', 'Categoria eliminada exitosamente.');
    }

    // ---- Domains ----

    public function domains(Tenant $tenant)
    {
        $domains = $tenant->emailDomains()->orderByDesc('principal')->get();
        return view('super-admin.tenants.domains', compact('tenant', 'domains'));
    }

    public function storeDomain(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'dominio' => 'required|string|max:255|unique:tenant_email_domains,dominio',
        ]);

        $tenant->emailDomains()->create([
            'dominio' => $validated['dominio'],
            'principal' => false,
            'activo' => true,
        ]);

        return redirect()->route('super-admin.tenants.domains', $tenant)
            ->with('success', 'Dominio agregado exitosamente.');
    }

    public function destroyDomain(Tenant $tenant, TenantEmailDomain $domain)
    {
        if ($domain->principal) {
            return redirect()->route('super-admin.tenants.domains', $tenant)
                ->with('error', 'No se puede eliminar el dominio principal.');
        }

        $domain->delete();

        return redirect()->route('super-admin.tenants.domains', $tenant)
            ->with('success', 'Dominio eliminado exitosamente.');
    }

    // ---- Users ----

    public function users(Tenant $tenant)
    {
        $users = User::where('tenant_id', $tenant->id)
            ->where('is_super_admin', false)
            ->orderBy('name')
            ->get();

        return view('super-admin.tenants.users', compact('tenant', 'users'));
    }
}

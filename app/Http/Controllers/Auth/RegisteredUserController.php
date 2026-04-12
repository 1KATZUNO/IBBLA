<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TenantEmailDomain;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Resolve tenant by email domain
        $email = $request->email;
        $domain = substr(strrchr($email, '@'), 1);

        $emailDomain = TenantEmailDomain::where('dominio', $domain)
            ->where('activo', true)
            ->first();

        if (! $emailDomain) {
            return back()->withErrors([
                'email' => 'El dominio de tu email no esta registrado en ninguna iglesia. Contacta al administrador.',
            ])->withInput();
        }

        $tenant = $emailDomain->tenant;
        if (! $tenant || ! $tenant->activo) {
            return back()->withErrors([
                'email' => 'La iglesia asociada a tu email no esta activa. Contacta al administrador.',
            ])->withInput();
        }

        // Find default role for this tenant
        $defaultRole = TenantRole::where('tenant_id', $tenant->id)
            ->where('es_default', true)
            ->first();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $defaultRole?->id,
            'rol' => $defaultRole?->slug ?? 'miembro',
            'is_super_admin' => false,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('principal', absolute: false));
    }
}

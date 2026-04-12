<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\TenantEmailDomain;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Super admin goes to super admin panel
        if ($user->isSuperAdmin()) {
            return redirect()->intended(route('super-admin.dashboard', absolute: false));
        }

        // Verify tenant is active
        if ($user->tenant_id) {
            $tenant = $user->tenant;
            if (! $tenant || ! $tenant->activo) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Tu iglesia no esta activa. Contacta al administrador.']);
            }
        } else {
            // User has no tenant and is not super admin - try to resolve by email domain
            $domain = substr(strrchr($user->email, '@'), 1);
            $emailDomain = TenantEmailDomain::where('dominio', $domain)->where('activo', true)->first();

            if ($emailDomain && $emailDomain->tenant && $emailDomain->tenant->activo) {
                $user->update(['tenant_id' => $emailDomain->tenant_id]);
            }
        }

        // Redirect miembro to their profile (not servidor)
        if ($user->rol === 'miembro') {
            return redirect()->intended(route('mi-perfil.index', absolute: false));
        }

        return redirect()->intended(route('principal', absolute: false));
    }

    /**
     * Login as guest without credentials.
     */
    public function guestLogin(Request $request): RedirectResponse
    {
        $guest = User::firstOrCreate(
            ['email' => 'invitado@ibbsc.local'],
            [
                'name' => 'Invitado',
                'password' => bcrypt(str()->random(32)),
                'rol' => 'invitado',
            ]
        );

        Auth::login($guest);

        $request->session()->regenerate();

        return redirect()->route('principal');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

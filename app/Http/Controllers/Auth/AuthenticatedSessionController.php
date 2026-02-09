<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
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

        return redirect()->intended(route('principal', absolute: false));
    }

    /**
     * Login as guest without credentials.
     */
    public function guestLogin(Request $request): RedirectResponse
    {
        // Buscar o crear usuario invitado
        $guest = User::firstOrCreate(
            ['email' => 'invitado@ibbsc.local'],
            [
                'name' => 'Invitado',
                'password' => bcrypt(str()->random(32)), // Password aleatorio que nadie conoce
                'rol' => 'invitado'
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

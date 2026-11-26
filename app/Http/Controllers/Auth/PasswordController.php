<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // Con su hash normal y, si es una cuenta miembro, la copia que puede ver el administrador (Tarjetas QR)
        $usuario = $request->user();
        $usuario->establecerContrasena($validated['password']);
        $usuario->save();

        return back()->with('status', 'password-updated');
    }
}

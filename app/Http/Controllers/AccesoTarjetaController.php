<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Persona;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lo que ve quien escanea el QR de su tarjeta (/t/{token}, ruta publica con limite de intentos):
 *  - persona sin cuenta: "Crea tu acceso" (usuario o correo y contrasena dos veces) -> cuenta de rol miembro
 *    enlazada a su persona, entra a Mi perfil;
 *  - cuenta restablecida por el administrador: "Crea tu contrasena nueva";
 *  - persona con cuenta: inicio de sesion con su usuario o correo ya escrito; el QR nunca entra solo;
 *  - token que no existe: pagina amable.
 * No revela nada de otras cuentas: solo muestra el usuario o correo de la persona de la tarjeta.
 */
class AccesoTarjetaController extends Controller
{
    public function mostrar(Request $request, string $token): Response|RedirectResponse
    {
        $persona = $this->persona($token);
        if (! $persona) {
            return response()->view('acceso.no-valido', [], 404);
        }

        $usuario = $persona->user_id ? User::find($persona->user_id) : null;

        if ($request->user()) {
            // Ya tiene la sesion abierta con su propia cuenta: directo a su inicio
            if ($usuario && $request->user()->id === $usuario->id) {
                return redirect()->route($usuario->rutaDeInicio());
            }

            return response()->view('acceso.otra-sesion', ['persona' => $persona]);
        }

        if (! $usuario) {
            return response()->view('acceso.crear', [
                'persona' => $persona,
                'modo' => 'crear',
                'sugerido' => $persona->correo,
            ]);
        }

        if ($usuario->debe_crear_contrasena) {
            return response()->view('acceso.crear', [
                'persona' => $persona,
                'modo' => 'nueva',
                'identificador' => $usuario->identificador(),
            ]);
        }

        return response()->view('acceso.entrar', [
            'persona' => $persona,
            'identificador' => $usuario->identificador(),
        ]);
    }

    public function guardar(Request $request, string $token): RedirectResponse|Response
    {
        $persona = $this->persona($token);
        if (! $persona) {
            return response()->view('acceso.no-valido', [], 404);
        }
        if ($request->user()) {
            return redirect()->route('acceso.tarjeta', ['token' => $token]);
        }

        $mensajes = [
            'password.required' => 'Escribe una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las dos contraseñas no coinciden. Escríbela igual las dos veces.',
        ];
        $reglaContrasena = ['required', 'string', 'min:8', 'max:255', 'confirmed'];

        $usuario = DB::transaction(function () use ($request, $persona, $mensajes, $reglaContrasena) {
            // Bloquea la persona: dos envios a la vez no crean dos cuentas
            $persona = Persona::withoutGlobalScopes()->lockForUpdate()->find($persona->id);
            $usuario = $persona->user_id ? User::find($persona->user_id) : null;

            // Contrasena nueva tras "Restablecer acceso"
            if ($usuario) {
                if (! $usuario->debe_crear_contrasena) {
                    return null;
                }
                $datos = $request->validate(['password' => $reglaContrasena], $mensajes);
                $usuario->establecerContrasena($datos['password']);
                $usuario->save();

                return [$usuario, 'Crear contraseña nueva con la tarjeta QR'];
            }

            // Cuenta nueva: usuario (sin @) o correo
            $identificador = Str::lower(trim((string) $request->input('identificador')));
            $request->merge(['identificador' => $identificador]);
            $esCorreo = str_contains($identificador, '@');
            $datos = $request->validate([
                'identificador' => $esCorreo
                    ? ['required', 'string', 'max:191', 'email', Rule::unique('users', 'email'), Rule::unique('users', 'usuario')]
                    : ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'usuario'), Rule::unique('users', 'email')],
                'password' => $reglaContrasena,
            ], $mensajes + [
                'identificador.required' => 'Escribe un usuario o un correo.',
                'identificador.email' => 'Ese correo no parece válido. Revísalo o usa un usuario sin @.',
                'identificador.unique' => 'Ese usuario o correo ya está en uso. Elige otro.',
                'identificador.min' => 'El usuario debe tener al menos 3 letras o números.',
                'identificador.max' => 'El usuario o correo es demasiado largo.',
                'identificador.regex' => 'El usuario solo puede llevar letras sin tilde, números, punto, guion y guion bajo (sin espacios).',
            ]);

            $usuario = new User([
                'name' => $persona->nombre,
                'email' => $esCorreo ? $datos['identificador'] : null,
                'usuario' => $esCorreo ? null : $datos['identificador'],
                'rol' => 'miembro',
                'tenant_id' => $persona->tenant_id,
                'tenant_role_id' => TenantRole::where('tenant_id', $persona->tenant_id)->where('slug', 'miembro')->value('id'),
            ]);
            $usuario->establecerContrasena($datos['password']);
            $usuario->save();

            $persona->forceFill(array_filter([
                'user_id' => $usuario->id,
                // Si se registro con correo y la ficha no tenia, queda tambien como su correo de contacto
                'correo' => $esCorreo && ! $persona->correo ? $datos['identificador'] : null,
            ]))->save();

            return [$usuario, 'Crear acceso con la tarjeta QR'];
        });

        if ($usuario === null) {
            // Ya tiene cuenta: la tarjeta solo lleva a iniciar sesion
            return redirect()->route('acceso.tarjeta', ['token' => $token]);
        }

        [$usuario, $accion] = $usuario;
        Auth::login($usuario);
        $request->session()->regenerate();

        AuditLog::registrar($accion, [
            'persona_id' => $persona->id,
            'persona' => $persona->nombre,
            'usuario_id' => $usuario->id,
            'cuenta' => $usuario->identificador(),
        ]);

        return redirect()->route($usuario->rutaDeInicio())
            ->with('success', $accion === 'Crear acceso con la tarjeta QR'
                ? 'Tu cuenta quedó creada. La próxima vez entra con '.$usuario->identificador().' y tu contraseña.'
                : 'Listo: tu contraseña nueva quedó guardada.');
    }

    /** La persona de la tarjeta, de una iglesia activa; null si el token no existe o no tiene la forma correcta. */
    private function persona(string $token): ?Persona
    {
        if (strlen($token) !== Persona::LARGO_TOKEN || ! ctype_alnum($token)) {
            return null;
        }

        $persona = Persona::withoutGlobalScopes()->where('token_acceso', $token)->first();
        if (! $persona || ! $persona->tenant || ! $persona->tenant->activo) {
            return null;
        }

        // La pagina publica lleva el nombre y los colores de la iglesia de la tarjeta
        app()->instance('current_tenant', $persona->tenant);

        return $persona;
    }
}

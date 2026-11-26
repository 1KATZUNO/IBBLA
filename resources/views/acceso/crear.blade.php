<x-guest-layout>
    {{-- Tarjeta QR: crear el acceso (persona sin cuenta) o la contraseña nueva (tras «Restablecer acceso») --}}
    <div class="mb-6">
        <p class="text-sm font-medium text-brand-700">Hola, {{ $persona->nombre }}</p>
        @if ($modo === 'crear')
            <h2 class="mt-1 text-xl font-semibold tracking-tight text-gray-900">Crea tu acceso</h2>
            <p class="mt-1 text-sm text-gray-500">Elige con qué vas a entrar (un usuario o tu correo) y una contraseña. Es una sola vez: después entras con eso.</p>
        @else
            <h2 class="mt-1 text-xl font-semibold tracking-tight text-gray-900">Crea tu contraseña nueva</h2>
            <p class="mt-1 text-sm text-gray-500">El administrador restableció tu acceso. Entras con <strong class="text-gray-800">{{ $identificador }}</strong> y la contraseña que escribas aquí.</p>
        @endif
    </div>

    <form method="POST" action="{{ route('acceso.tarjeta.guardar', ['token' => request()->route('token')]) }}" id="accesoForm">
        @csrf

        @if ($modo === 'crear')
            <div>
                <x-input-label for="identificador" value="Usuario o correo" />
                <x-text-input id="identificador" class="mt-1.5" type="text" name="identificador" :value="old('identificador', $sugerido)" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="Por ejemplo: juanperez o tu@correo.com" maxlength="191" />
                <p class="field-hint mt-1">Un usuario lleva solo letras sin tilde, números, punto o guion, sin espacios (por ejemplo <em>mariarojas</em>).</p>
                <x-input-error :messages="$errors->get('identificador')" class="mt-2" />
            </div>
        @else
            {{-- Para que el gestor de contraseñas del teléfono la guarde con la cuenta correcta --}}
            <input type="text" name="usuario_visible" value="{{ $identificador }}" autocomplete="username" class="hidden" tabindex="-1" aria-hidden="true" readonly>
        @endif

        <div class="mt-4">
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="mt-1.5" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Al menos 8 caracteres" :autofocus="$modo !== 'crear'" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Repite la contraseña" />
            <x-text-input id="password_confirmation" class="mt-1.5" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" placeholder="Escríbela otra vez" />
        </div>

        @if (\App\Models\User::contrasenaVisibleActiva())
            <div class="alert-warning mt-4 text-sm">
                El administrador de la iglesia puede ver esta contraseña para ayudarte si la olvidas. No uses la misma de tu correo ni la de tu banco.
            </div>
        @endif

        <button type="submit" class="btn-primary mt-5 w-full">
            {{ $modo === 'crear' ? 'Crear mi acceso y entrar' : 'Guardar y entrar' }}
        </button>
    </form>
</x-guest-layout>

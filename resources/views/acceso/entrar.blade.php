<x-guest-layout>
    {{-- Tarjeta QR de una persona que ya tiene cuenta: el QR solo la trae aquí; siempre escribe su contraseña --}}
    <div class="mb-6">
        <p class="text-sm font-medium text-brand-700">Hola, {{ $persona->nombre }}</p>
        <h2 class="mt-1 text-xl font-semibold tracking-tight text-gray-900">Entra a tu cuenta</h2>
        <p class="mt-1 text-sm text-gray-500">Escribe tu contraseña para entrar.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" value="Correo electrónico o usuario" />
            <x-text-input id="email" class="mt-1.5" type="text" name="email" :value="old('email', $identificador)" required autocomplete="username" autocapitalize="none" spellcheck="false" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="mt-1.5" type="password" name="password" required autofocus autocomplete="current-password" placeholder="Tu contraseña" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="mt-3 inline-flex min-h-[44px] cursor-pointer items-center">
            <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" name="remember">
            <span class="ms-2 text-sm text-gray-600">Recordarme en este teléfono</span>
        </label>

        <button type="submit" class="btn-primary mt-2 w-full">Entrar</button>
    </form>

    <p class="mt-6 border-t border-gray-100 pt-4 text-center text-xs text-gray-500">
        ¿Olvidaste tu contraseña? Pídele al administrador de tu iglesia que te ayude.
    </p>
</x-guest-layout>

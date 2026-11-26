<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-semibold tracking-tight text-gray-900">Iniciar sesión</h2>
        <p class="mt-1 text-sm text-gray-500">Entra con tu correo o tu usuario y tu contraseña.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" id="loginForm">
        @csrf

        <div>
            {{-- Correo o nombre de usuario (quien creó su acceso con la tarjeta QR puede no tener correo) --}}
            <x-input-label for="email" value="Correo electrónico o usuario" />
            <x-text-input id="email" class="mt-1.5" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="tu@correo.com o tu usuario" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1.5" type="password" name="password" required autocomplete="current-password" placeholder="Tu contraseña" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <label for="remember_me" class="inline-flex min-h-[44px] cursor-pointer items-center">
                <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="inline-flex min-h-[44px] items-center text-sm font-medium text-brand-700 hover:text-brand-800 hover:underline" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <button type="submit" id="loginBoton" class="btn-primary mt-2 w-full">
            <span id="loginTexto">{{ __('Log in') }}</span>
            <svg id="loginGiro" class="hidden h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
        </button>
    </form>

    <p class="mt-6 border-t border-gray-100 pt-4 text-center text-xs text-gray-500">
        ¿No tienes usuario? Pídeselo al administrador de tu iglesia: solo entran las personas que él agregó.
    </p>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            if (!this.checkValidity()) { return; }
            document.getElementById('loginBoton').setAttribute('aria-busy', 'true');
            document.getElementById('loginTexto').textContent = 'Entrando…';
            document.getElementById('loginGiro').classList.remove('hidden');
        });
    </script>
</x-guest-layout>

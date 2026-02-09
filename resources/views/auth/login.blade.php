<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" id="loginForm">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" class="text-gray-700 font-medium" />
            <x-text-input id="email" class="input-primary mt-2" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="tu@email.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-5">
            <x-input-label for="password" :value="__('Password')" class="text-gray-700 font-medium" />
            <x-text-input id="password" class="input-primary mt-2"
                            type="password"
                            name="password"
                            required autocomplete="current-password"
                            placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between mt-5">
            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 transition-all" name="remember">
                <span class="ms-2 text-sm text-gray-600 hover:text-blue-600 transition-colors">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-blue-600 hover:text-blue-700 font-medium transition-colors" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <div class="mt-6">
            <button type="submit" class="btn-primary w-full">
                {{ __('Log in') }}
            </button>
        </div>
    </form>

    <!-- Separador -->
    <div class="relative my-6">
        <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-200"></div>
        </div>
        <div class="relative flex justify-center text-sm">
            <span class="px-4 bg-white text-gray-500 font-medium">o continúa con</span>
        </div>
    </div>

    <!-- Botón Invitado -->
    <form method="POST" action="{{ route('login.guest') }}" id="guestForm">
        @csrf
        <button type="submit" class="w-full flex items-center justify-center px-6 py-3 bg-white border-2 border-gray-200 rounded-lg font-semibold text-sm text-gray-700 hover:bg-gray-50 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all duration-200 shadow-sm">
            <svg class="w-5 h-5 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
            </svg>
            <span>Entrar como Invitado</span>
        </button>
    </form>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-blue-700 z-[9999] flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="text-center">
            <!-- Logo -->
            <div class="mb-8">
                <img src="{{ asset('images/Logo.png') }}" alt="IBBSC" class="w-24 h-24 mx-auto">
            </div>

            <!-- Spinner -->
            <div class="w-12 h-12 mx-auto mb-6">
                <div class="w-full h-full border-4 border-white/30 border-t-white rounded-full animate-spin"></div>
            </div>

            <!-- Texto -->
            <h2 class="text-2xl font-display font-bold text-white mb-2">¡Bienvenido!</h2>
            <p class="text-blue-100">Preparando tu panel de control...</p>

            <!-- Barra de progreso -->
            <div class="w-48 h-1 bg-white/20 rounded-full mx-auto mt-6 overflow-hidden">
                <div class="h-full bg-white rounded-full animate-progress"></div>
            </div>
        </div>
    </div>

    <style>
        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        .animate-progress {
            animation: progress 2s ease-in-out infinite;
        }
    </style>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const overlay = document.getElementById('loadingOverlay');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100');
        });

        document.getElementById('guestForm').addEventListener('submit', function(e) {
            const overlay = document.getElementById('loadingOverlay');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100');
        });
    </script>
</x-guest-layout>

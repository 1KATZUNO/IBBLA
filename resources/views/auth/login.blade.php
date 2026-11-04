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

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-blue-700 z-[9999] flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="text-center">
            <!-- Checkmark Icon -->
            <div class="mb-8">
                <svg class="w-24 h-24 mx-auto text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
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

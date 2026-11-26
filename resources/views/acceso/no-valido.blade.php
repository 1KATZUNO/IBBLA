<x-guest-layout>
    <div class="text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-700">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        </span>
        <h2 class="mt-4 text-xl font-semibold tracking-tight text-gray-900">Este código ya no es válido</h2>
        <p class="mt-2 text-sm text-gray-600">Pídele uno nuevo al administrador de tu iglesia.</p>
        <p class="mt-1 text-sm text-gray-500">Si ya tienes cuenta, puedes entrar con tu usuario o correo.</p>
        <a href="{{ route('login') }}" class="btn-secondary mt-6 w-full">Ir a iniciar sesión</a>
    </div>
</x-guest-layout>

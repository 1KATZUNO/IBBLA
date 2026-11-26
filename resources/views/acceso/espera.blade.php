<x-guest-layout>
    {{-- Tarjeta QR: se paso el limite de intentos por minuto (de esa tarjeta o de toda la red). Se resuelve solo. --}}
    <div class="text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-700">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <h2 class="mt-4 text-xl font-semibold tracking-tight text-gray-900">Espera un momento</h2>
        <p class="mt-2 text-sm text-gray-600">Hay muchos intentos seguidos con esta tarjeta o muchas personas entrando al mismo tiempo.</p>
        <p class="mt-1 text-sm text-gray-500">Espera un minuto y vuelve a intentarlo.</p>
        <a href="{{ url()->current() }}" class="btn-secondary mt-6 w-full">Volver a intentar</a>
    </div>
</x-guest-layout>

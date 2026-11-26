<x-guest-layout>
    {{-- Alguien con la sesión abierta (otra cuenta) escaneó una tarjeta: no se mezcla, primero sale --}}
    <div class="text-center">
        <h2 class="text-xl font-semibold tracking-tight text-gray-900">Tienes otra sesión abierta</h2>
        <p class="mt-2 text-sm text-gray-600">Esta tarjeta es de <strong>{{ $persona->nombre }}</strong>, pero en este teléfono está abierta la cuenta de <strong>{{ auth()->user()->name }}</strong>.</p>
        <p class="mt-1 text-sm text-gray-500">Cierra la sesión y vuelve a escanear la tarjeta.</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="btn-primary w-full">Cerrar sesión</button>
        </form>
        <a href="{{ route(auth()->user()->rutaDeInicio()) }}" class="btn-ghost mt-2 w-full">Seguir con mi cuenta</a>
    </div>
</x-guest-layout>

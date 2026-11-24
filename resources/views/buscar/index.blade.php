@extends('layouts.admin')

@section('page-title', 'Resultados de la búsqueda')
@section('page-subtitle', mb_strlen($q) >= 2 ? 'Para “'.$q.'”' : 'Escribe al menos 2 letras para buscar')

@section('content')
<div class="max-w-4xl space-y-6">
    {{-- Campo propio de la pagina: en pantallas angostas la barra superior solo muestra la lupa --}}
    <form method="GET" action="{{ route('buscar') }}" role="search" class="card flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:p-4">
        <label for="buscar-q" class="sr-only">Buscar en el sistema</label>
        <input id="buscar-q" type="search" name="q" value="{{ $q }}" maxlength="100" autocomplete="off" class="input-primary flex-1" placeholder="Personas, cultos, pantallas, reportes o temas del manual">
        <button type="submit" class="btn-primary">Buscar</button>
    </form>

    @if(mb_strlen($q) < 2)
        <div class="card empty-state">
            <span class="empty-state-icon"><x-icono nombre="lupa" class="h-6 w-6" /></span>
            <p class="empty-state-title">¿Qué estás buscando?</p>
            <p class="empty-state-text">Busca personas por nombre, PIN o teléfono; cultos por fecha (12/10, octubre, domingo); pantallas, reportes y temas del manual.</p>
        </div>
    @elseif($resultado['total'] === 0)
        <div class="card empty-state">
            <span class="empty-state-icon"><x-icono nombre="lupa" class="h-6 w-6" /></span>
            <p class="empty-state-title">No encontramos “{{ $q }}”</p>
            <p class="empty-state-text">Prueba con otra palabra, un nombre completo o una fecha como 12/10. Solo se muestran las pantallas y datos que tu rol puede abrir.</p>
            @if(Route::has('manual.index'))
                <div class="empty-state-actions">
                    <a href="{{ route('manual.index') }}" class="btn-secondary">Abrir el manual de usuario</a>
                </div>
            @endif
        </div>
    @else
        @foreach($resultado['grupos'] as $grupo)
            <section class="card overflow-hidden" aria-labelledby="grupo-{{ $grupo['clave'] }}">
                <div class="card-header">
                    <h2 id="grupo-{{ $grupo['clave'] }}" class="card-title">{{ $grupo['titulo'] }}</h2>
                    <span class="badge-neutral">{{ count($grupo['items']) }}</span>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach($grupo['items'] as $it)
                        <li>
                            <a href="{{ $it['url'] }}" class="flex min-h-[56px] items-center gap-3 px-4 py-3 transition-colors hover:bg-gray-50 sm:px-6">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                    <x-icono :nombre="$it['icono']" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-gray-900">{{ $it['titulo'] }}</span>
                                    <span class="block text-sm text-gray-500 line-clamp-2">{{ $it['detalle'] }}</span>
                                </span>
                                <x-icono nombre="flecha-derecha" class="h-4 w-4 shrink-0 text-gray-400" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>
@endsection

@extends('layouts.admin')

@section('title', tenant_title('Recuento'))
@section('page-title', 'Recuento de Sobres')

@section('content')
@php
    $selectClase = 'w-full min-h-[44px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm';
    $estados = [
        '' => ['Todos', $conteo['total']],
        'abiertos' => ['Abiertos', $conteo['abiertos']],
        'cerrados' => ['Cerrados', $conteo['cerrados']],
    ];
@endphp
<div class="space-y-4">
    {{-- Encabezado: que es la pantalla y la accion principal a la derecha --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold text-gray-900">Cultos</h2>
            <p class="text-sm text-gray-500">Toca una línea para abrir el culto y registrar o revisar sus sobres.</p>
        </div>
        @if(Auth::user()->canAccessAdmin())
            <a href="{{ route('cultos.create') }}" class="btn-primary">+ Nuevo culto</a>
        @endif
    </div>

    {{-- Filtros arriba de la tabla --}}
    <form method="GET" action="{{ route('recuento.index') }}" class="bg-white rounded-lg shadow p-4" role="search" aria-label="Buscar cultos">
        @if($filtros['estado'])
            <input type="hidden" name="estado" value="{{ $filtros['estado'] }}">
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] gap-3 items-end">
            <div>
                <label for="f-fecha" class="block text-sm font-medium text-gray-700 mb-1">Buscar por fecha</label>
                <input type="date" id="f-fecha" name="fecha" value="{{ $filtros['fecha'] }}" class="{{ $selectClase }}" data-autoenviar>
            </div>
            <div>
                <label for="f-mes" class="block text-sm font-medium text-gray-700 mb-1">Mes</label>
                <select id="f-mes" name="mes" class="{{ $selectClase }}" data-autoenviar>
                    <option value="">Todos</option>
                    @foreach($meses as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected($filtros['mes'] === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo de culto</label>
                <select id="f-tipo" name="tipo" class="{{ $selectClase }}" data-autoenviar>
                    <option value="">Todos</option>
                    @foreach($tipos as $tipo)
                        <option value="{{ $tipo->slug }}" @selected($filtros['tipo'] === $tipo->slug)>{{ $tipo->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-secondary flex-1 lg:flex-none">Buscar</button>
                @if($hayFiltros)
                    <a href="{{ route('recuento.index') }}" class="btn-secondary flex-1 lg:flex-none">Limpiar</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Estado: todos / abiertos / cerrados, con cuantos hay de cada uno --}}
    <nav class="flex flex-wrap gap-2" aria-label="Filtrar por estado">
        @foreach($estados as $valor => [$etiqueta, $cantidad])
            @php $activo = ($filtros['estado'] ?? '') === $valor; @endphp
            <a href="{{ request()->fullUrlWithQuery(['estado' => $valor ?: null, 'page' => null]) }}"
               class="inline-flex items-center gap-2 min-h-[44px] px-4 rounded-full border text-sm font-medium transition-colors {{ $activo ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' }}"
               @if($activo) aria-current="page" @endif>
                {{ $etiqueta }}
                <span class="rounded-full px-2 py-0.5 text-xs {{ $activo ? 'bg-white/20' : 'bg-gray-100 text-gray-600' }}">{{ $cantidad }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Una linea por culto --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="table-scroll">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Día</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Tipo</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sobres</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th scope="col" class="px-2 py-3"><span class="sr-only">Abrir</span></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($cultos as $culto)
                        @php
                            $url = route('recuento.culto', $culto);
                            $dia = ucfirst($culto->fecha->locale('es')->isoFormat('dddd'));
                            $esHoy = $culto->fecha->isToday();
                        @endphp
                        <tr class="group cursor-pointer transition-colors hover:bg-blue-50 {{ $esHoy ? 'bg-amber-50' : '' }}" data-href="{{ $url }}">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ $url }}" class="text-sm font-semibold text-gray-900 group-hover:text-blue-700 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">{{ $culto->fecha->format('d/m/Y') }}</a>
                                @if($esHoy)
                                    <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Hoy</span>
                                @endif
                                <span class="block sm:hidden text-xs text-gray-500">{{ $dia }} · {{ $culto->tipo_nombre }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 hidden sm:table-cell">{{ $dia }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 hidden sm:table-cell">{{ $culto->tipo_nombre }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right tabular-nums text-gray-700">{{ $culto->sobres_count }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right tabular-nums font-semibold text-gray-900">
                                {{ $culto->totales ? '₡'.number_format((float) $culto->totales->total_general, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                @if($culto->cerrado)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                        Cerrado
                                    </span>
                                    @if($culto->cerrado_at)
                                        <span class="block mt-0.5 text-xs text-gray-500">{{ $culto->cerrado_at->format('d/m') }}{{ $culto->cerradoPor ? ' · '.$culto->cerradoPor->name : '' }}</span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">
                                        <span class="h-1.5 w-1.5 rounded-full bg-green-600" aria-hidden="true"></span>
                                        Abierto
                                    </span>
                                @endif
                            </td>
                            <td class="px-2 py-3 text-right text-gray-400 group-hover:text-blue-600">
                                <svg class="w-5 h-5 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                @if($hayFiltros)
                                    <p class="text-base font-semibold text-gray-800">No hay cultos con estos filtros</p>
                                    <p class="mt-1 text-sm text-gray-500">Prueba con otra fecha, otro mes o quita los filtros.</p>
                                    <a href="{{ route('recuento.index') }}" class="btn-secondary mt-4">Quitar filtros</a>
                                @else
                                    <p class="text-base font-semibold text-gray-800">Todavía no hay cultos</p>
                                    <p class="mt-1 text-sm text-gray-500">Para registrar sobres primero hay que crear el culto.</p>
                                    @if(Auth::user()->canAccessAdmin())
                                        <a href="{{ route('cultos.create') }}" class="btn-primary mt-4">+ Nuevo culto</a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($cultos->hasPages())
        <div>{{ $cultos->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
    // Los filtros se aplican al elegir, sin tener que tocar "Buscar"
    document.querySelectorAll('[data-autoenviar]').forEach(function (campo) {
        campo.addEventListener('change', function () {
            campo.form.requestSubmit ? campo.form.requestSubmit() : campo.form.submit();
        });
    });
    // Toda la linea abre el culto (el enlace de la fecha sigue sirviendo con teclado)
    document.querySelectorAll('tr[data-href]').forEach(function (fila) {
        fila.addEventListener('click', function (e) {
            if (e.target.closest('a, button, input, select, label')) return;
            window.location = fila.dataset.href;
        });
    });
</script>
@endpush
@endsection

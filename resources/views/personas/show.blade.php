@extends('layouts.admin')

@section('title', 'IBBSC - Detalles de Persona')
@section('page-title', 'Detalles de Persona')

@section('content')
<div class="space-y-6">
    <!-- Información Personal -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $persona->nombre }}</h2>
                <span class="inline-block mt-2 px-3 py-1 text-sm font-semibold rounded-full {{ $persona->activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $persona->activo ? 'Activo' : 'Inactivo' }}
                </span>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('personas.edit', $persona) }}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Editar
                </a>
                <a href="{{ route('personas.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Volver
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="text-sm font-medium text-gray-500">Teléfono</label>
                <p class="mt-1 text-base text-gray-900">{{ $persona->telefono ?? '-' }}</p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Correo</label>
                <p class="mt-1 text-base text-gray-900">{{ $persona->correo ?? '-' }}</p>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-500">Fecha de registro</label>
                <p class="mt-1 text-base text-gray-900">{{ $persona->created_at->format('d/m/Y') }}</p>
            </div>
        </div>

        @if($persona->notas)
        <div class="mt-4">
            <label class="text-sm font-medium text-gray-500">Notas</label>
            <p class="mt-1 text-base text-gray-700">{{ $persona->notas }}</p>
        </div>
        @endif
    </div>

    <!-- Estadísticas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-500">Total Sobres</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $persona->sobres->count() }}</p>
                </div>
                <div class="p-3 bg-blue-100 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-500">Total Ofrendado</p>
                    <p class="text-2xl font-bold text-green-600">₡{{ number_format($persona->sobres->sum('total_declarado'), 2) }}</p>
                </div>
                <div class="p-3 bg-green-100 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-500">Promesas Activas</p>
                    <p class="text-2xl font-bold text-purple-600">{{ $persona->promesas->count() }}</p>
                </div>
                <div class="p-3 bg-purple-100 rounded-full">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Promesas -->
    @if($persona->promesas->count() > 0)
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Promesas</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Monto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frecuencia</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($persona->promesas as $promesa)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                            {{ ucfirst($promesa->categoria) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">
                            ₡{{ number_format($promesa->monto, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                @if($promesa->frecuencia == 'semanal') bg-blue-100 text-blue-800
                                @elseif($promesa->frecuencia == 'quincenal') bg-green-100 text-green-800
                                @else bg-purple-100 text-purple-800
                                @endif">
                                {{ ucfirst($promesa->frecuencia) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Últimos Sobres -->
    @if($persona->sobres->count() > 0)
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Sobres Registrados</h3>
        </div>
        
        <!-- Filtros -->
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <form method="GET" action="{{ route('personas.show', $persona) }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Filtro de Mes -->
                    <div>
                        <label for="mes" class="block text-sm font-medium text-gray-700 mb-2">Mes</label>
                        <select name="mes" id="mes" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="todos" {{ request('mes', 'todos') == 'todos' ? 'selected' : '' }}>Todos los meses</option>
                            <option value="1" {{ request('mes') == '1' ? 'selected' : '' }}>Enero</option>
                            <option value="2" {{ request('mes') == '2' ? 'selected' : '' }}>Febrero</option>
                            <option value="3" {{ request('mes') == '3' ? 'selected' : '' }}>Marzo</option>
                            <option value="4" {{ request('mes') == '4' ? 'selected' : '' }}>Abril</option>
                            <option value="5" {{ request('mes') == '5' ? 'selected' : '' }}>Mayo</option>
                            <option value="6" {{ request('mes') == '6' ? 'selected' : '' }}>Junio</option>
                            <option value="7" {{ request('mes') == '7' ? 'selected' : '' }}>Julio</option>
                            <option value="8" {{ request('mes') == '8' ? 'selected' : '' }}>Agosto</option>
                            <option value="9" {{ request('mes') == '9' ? 'selected' : '' }}>Septiembre</option>
                            <option value="10" {{ request('mes') == '10' ? 'selected' : '' }}>Octubre</option>
                            <option value="11" {{ request('mes') == '11' ? 'selected' : '' }}>Noviembre</option>
                            <option value="12" {{ request('mes') == '12' ? 'selected' : '' }}>Diciembre</option>
                        </select>
                    </div>

                    <!-- Filtro de Año -->
                    <div>
                        <label for="año" class="block text-sm font-medium text-gray-700 mb-2">Año</label>
                        <select name="año" id="año" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="todos" {{ request('año', 'todos') == 'todos' ? 'selected' : '' }}>Todos los años</option>
                            @foreach($añosDisponibles as $año)
                                <option value="{{ $año }}" {{ request('año') == $año ? 'selected' : '' }}>{{ $año }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Fecha Inicio -->
                    <div>
                        <label for="fecha_inicio" class="block text-sm font-medium text-gray-700 mb-2">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ request('fecha_inicio') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <!-- Fecha Fin -->
                    <div>
                        <label for="fecha_fin" class="block text-sm font-medium text-gray-700 mb-2">Fecha Fin</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" value="{{ request('fecha_fin') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <!-- Botones -->
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            Filtrar
                        </button>
                        <a href="{{ route('personas.show', $persona) }}" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                            Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Culto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Sobre</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Método</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Monto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categorías</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($persona->sobres as $sobre)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $sobre->culto->fecha->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                            #{{ $sobre->numero_sobre }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $sobre->metodo_pago == 'efectivo' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ ucfirst($sobre->metodo_pago) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                            ₡{{ number_format($sobre->total_declarado, 2) }}
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="flex flex-wrap gap-1">
                                @foreach($sobre->detalles as $detalle)
                                <span class="px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded">
                                    {{ ucfirst($detalle->categoria) }}: ₡{{ number_format($detalle->monto, 2) }}
                                </span>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection

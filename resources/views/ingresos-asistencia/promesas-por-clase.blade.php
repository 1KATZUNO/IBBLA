@extends('layouts.admin')

@section('title', 'IBBSC - Promesas por Clase')
@section('page-title', 'Promesas por Clase')

@section('content')
<div class="space-y-6">
    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" action="{{ route('ingresos-asistencia.promesas-por-clase') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="clase_id" class="block text-sm font-medium text-gray-700 mb-2">Clase *</label>
                    <select name="clase_id" id="clase_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">-- Seleccione una clase --</option>
                        <option value="capilla" {{ $claseId === 'capilla' ? 'selected' : '' }}>Capilla (Adultos)</option>
                        @foreach($clasesDisponibles as $clase)
                            <option value="{{ $clase->id }}" {{ $claseId == $clase->id ? 'selected' : '' }}>
                                {{ $clase->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="año" class="block text-sm font-medium text-gray-700 mb-2">Año</label>
                    <select name="año" id="año"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            onchange="actualizarMeses()">
                        @foreach($añosDisponibles as $añoDisponible)
                            <option value="{{ $añoDisponible }}" {{ $año == $añoDisponible ? 'selected' : '' }}>
                                {{ $añoDisponible }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="mes" class="block text-sm font-medium text-gray-700 mb-2">Mes</label>
                    <select name="mes" id="mes"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="todos" {{ $mes == 'todos' ? 'selected' : '' }}>Todos los meses</option>
                        @php
                            $meses = [
                                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                            ];
                            $añoActual = date('Y');
                            $mesActual = date('n');
                        @endphp
                        @foreach($meses as $numMes => $nombreMes)
                            @php $mostrar = ($año < $añoActual) || ($año == $añoActual && $numMes <= $mesActual); @endphp
                            @if($mostrar)
                                <option value="{{ $numMes }}" {{ $mes == $numMes ? 'selected' : '' }}>
                                    {{ $nombreMes }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        Buscar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
    function actualizarMeses() {
        const añoSelect = document.getElementById('año');
        const mesSelect = document.getElementById('mes');
        const añoSeleccionado = parseInt(añoSelect.value);
        const añoActual = {{ date('Y') }};
        const mesActual = {{ date('n') }};

        const meses = mesSelect.querySelectorAll('option');
        meses.forEach(option => {
            const mesNum = parseInt(option.value);
            if (isNaN(mesNum)) return; // Skip "todos"
            if (añoSeleccionado < añoActual) {
                option.style.display = 'block';
            } else if (añoSeleccionado == añoActual) {
                option.style.display = mesNum <= mesActual ? 'block' : 'none';
            } else {
                option.style.display = 'none';
            }
        });
    }
    </script>

    <!-- Botón volver a Promesas general -->
    <div class="flex justify-between">
        <a href="{{ route('ingresos-asistencia.promesas') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Volver a Promesas General
        </a>
    </div>

    @if($totales)
    <!-- Info de clase -->
    <div class="bg-white rounded-lg shadow p-4 flex items-center gap-3">
        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
        </svg>
        <div>
            <span class="text-lg font-semibold text-gray-900">{{ $claseNombre }}</span>
            <span class="text-sm text-gray-500 ml-2">({{ $totales['total_personas'] }} personas activas)</span>
        </div>
        <span class="text-sm text-gray-500 ml-auto">
            @if($mes == 'todos')
                Todo el año {{ $año }}
            @else
                {{ \Carbon\Carbon::create($año, $mes, 1)->locale('es')->translatedFormat('F Y') }}
            @endif
        </span>
    </div>

    <!-- Resumen General -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl shadow-lg p-6 text-white">
            <p class="text-sm text-blue-100 font-medium mb-1">Total Prometido</p>
            <p class="text-3xl font-bold">₡{{ number_format($totales['grand_total']['prometido'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-xl shadow-lg p-6 text-white">
            <p class="text-sm text-green-100 font-medium mb-1">Total Dado</p>
            <p class="text-3xl font-bold">₡{{ number_format($totales['grand_total']['dado'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-gradient-to-br from-red-500 to-red-700 rounded-xl shadow-lg p-6 text-white">
            <p class="text-sm text-red-100 font-medium mb-1">Total Faltante</p>
            <p class="text-3xl font-bold">₡{{ number_format($totales['grand_total']['faltante'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl shadow-lg p-6 text-white">
            <p class="text-sm text-purple-100 font-medium mb-1">Total Profit</p>
            <p class="text-3xl font-bold">₡{{ number_format($totales['grand_total']['profit'], 0, ',', '.') }}</p>
        </div>
    </div>

    <!-- Tabla de Promesas por Categoría -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold">Detalle por Categoría - {{ $claseNombre }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Prometido</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Dado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Faltante</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Profit</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($totales['categorias'] as $cat)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                            {{ $cat['categoria'] }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 font-medium">
                            ₡{{ number_format($cat['total_prometido'], 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">
                            ₡{{ number_format($cat['total_dado'], 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-bold">
                            ₡{{ number_format($cat['faltante'], 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-purple-600 font-bold">
                            ₡{{ number_format($cat['profit'], 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            No hay datos de promesas para esta clase en el periodo seleccionado
                        </td>
                    </tr>
                    @endforelse
                    @if(count($totales['categorias']) > 0)
                    <tr class="bg-gray-100 font-bold text-gray-900">
                        <td class="px-6 py-4 text-sm">TOTALES GENERALES</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-700">
                            ₡{{ number_format($totales['grand_total']['prometido'], 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700">
                            ₡{{ number_format($totales['grand_total']['dado'], 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-700">
                            ₡{{ number_format($totales['grand_total']['faltante'], 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-purple-700">
                            ₡{{ number_format($totales['grand_total']['profit'], 0, ',', '.') }}
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @elseif($claseId === null)
    <!-- Estado inicial: pedir seleccionar clase -->
    <div class="bg-white rounded-lg shadow p-12 text-center">
        <svg class="mx-auto w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
        </svg>
        <h3 class="text-lg font-medium text-gray-500 mb-2">Seleccione una clase</h3>
        <p class="text-sm text-gray-400">Elija una clase del filtro superior para ver el reporte de promesas</p>
    </div>
    @endif
</div>
@endsection

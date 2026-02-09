@extends('layouts.admin')

@section('title', 'IBBSC - Reporte de Promesas')
@section('page-title', 'Reporte de Promesas')

@section('content')
<div class="space-y-6">
    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" action="{{ route('ingresos-asistencia.promesas') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                            @php
                                // Solo mostrar meses actuales o pasados
                                $mostrar = ($año < $añoActual) || ($año == $añoActual && $numMes <= $mesActual);
                            @endphp
                            @if($mostrar)
                                <option value="{{ $numMes }}" {{ $mes == $numMes ? 'selected' : '' }}>
                                    {{ $nombreMes }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="categoria" class="block text-sm font-medium text-gray-700 mb-2">Categoría (Opcional)</label>
                    <select name="categoria" id="categoria" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todas las categorías</option>
                        <option value="diezmo" {{ $categoria == 'diezmo' ? 'selected' : '' }}>Diezmo</option>
                        <option value="misiones" {{ $categoria == 'misiones' ? 'selected' : '' }}>Misiones</option>
                        <option value="seminario" {{ $categoria == 'seminario' ? 'selected' : '' }}>Seminario</option>
                        <option value="campa" {{ $categoria == 'campa' ? 'selected' : '' }}>Campamento</option>
                        <option value="construccion" {{ $categoria == 'construccion' ? 'selected' : '' }}>Construcción</option>
                        <option value="prestamo" {{ $categoria == 'prestamo' ? 'selected' : '' }}>Préstamo</option>
                        <option value="micro" {{ $categoria == 'micro' ? 'selected' : '' }}>Micro</option>
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

    <!-- Botón de Descarga PDF -->
    <div class="flex justify-end gap-3">
        <a href="{{ route('ingresos-asistencia.pdf-promesas-anual', ['año' => $año, 'categoria' => $categoria]) }}" 
           class="px-6 py-3 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors font-semibold flex items-center gap-2" target="_blank">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
            </svg>
            Reporte Anual (12 meses)
        </a>
        <a href="{{ route('ingresos-asistencia.pdf-promesas', ['año' => $año, 'mes' => $mes, 'categoria' => $categoria]) }}" 
           class="px-6 py-3 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors font-semibold flex items-center gap-2" target="_blank">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
            PDF Mensual
        </a>
    </div>

    <!-- Resumen General -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
            <p class="text-sm text-blue-100 font-medium mb-1">Total Prometido</p>
            <p class="text-3xl font-bold">₡{{ number_format($totales['grand_total']['prometido'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p class="text-sm text-green-100 font-medium mb-1">Total Dado</p>
            <p class="text-3xl font-bold">₡{{ number_format($totales['grand_total']['dado'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-gradient-to-br from-red-500 to-red-700 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p class="text-sm text-red-100 font-medium mb-1">Total Faltante</p>
            <p class="text-3xl font-bold">₡{{ number_format($totales['grand_total']['faltante'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
            <p class="text-sm text-purple-100 font-medium mb-1">Total Profit</p>
            <p class="text-3xl font-bold">₡{{ number_format($totales['grand_total']['profit'], 0, ',', '.') }}</p>
        </div>
    </div>

    <!-- Tabla de Promesas por Categoría -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold">
                Detalle por Categoría - 
                @if($mes == 'todos')
                    Todo el año {{ $año }}
                @else
                    {{ \Carbon\Carbon::create($año, $mes, 1)->locale('es')->translatedFormat('F Y') }}
                @endif
            </h3>
            @if($categoria)
            <p class="text-sm text-gray-600 mt-1">Mostrando solo: <span class="font-semibold">{{ ucfirst($categoria) }}</span></p>
            @endif
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
                            No hay datos de promesas para el año seleccionado
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

    <!-- Nota sobre el Profit -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    <span class="font-bold">Nota:</span> Este reporte muestra los datos del mes seleccionado. El profit representa el monto extra dado por encima de lo prometido en ese mes específico.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

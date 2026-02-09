@extends('layouts.admin')

@section('title', 'IBBSC - Reportes de Asistencia')
@section('page-title', 'Reportes de Asistencia')

@section('content')
<div class="space-y-6">
    <!-- Filtros y Búsqueda -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl shadow-md p-6 border border-blue-100">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
            </svg>
            Filtros de Búsqueda
        </h3>
        <form method="GET" action="{{ route('ingresos-asistencia.asistencia') }}" class="space-y-4">
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
                        Buscar
                    </button>
                    <a href="{{ route('ingresos-asistencia.asistencia') }}" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                        Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Lista de Cultos -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    Registros de Asistencia
                </h3>
                <p class="text-blue-100 text-sm mt-1">Lista completa de cultos y asistencias registradas</p>
            </div>
            <a href="{{ route('ingresos-asistencia.pdf-asistencia', ['fecha_inicio' => request('fecha_inicio'), 'fecha_fin' => request('fecha_fin')]) }}" 
               class="px-4 py-2 bg-white text-blue-600 rounded-lg hover:bg-blue-50 transition-colors flex items-center gap-2 font-semibold shadow-md" target="_blank">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
                Descargar PDF
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo Culto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Asistencia</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capilla</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clases</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($cultos as $culto)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ $culto->fecha->format('d/m/Y') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            {{ ucfirst($culto->tipo_culto) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if($culto->asistencia)
                            <span class="text-lg font-bold text-green-600">{{ $culto->asistencia->total_asistencia }}</span>
                        @else
                            <span class="text-sm text-gray-400">Sin registro</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        @if($culto->asistencia)
                            {{ $culto->asistencia->chapel_hombres + $culto->asistencia->chapel_mujeres + 
                               $culto->asistencia->chapel_adultos_mayores + $culto->asistencia->chapel_adultos + 
                               $culto->asistencia->chapel_jovenes }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        @if($culto->asistencia)
                            {{ $culto->asistencia->total_asistencia - 
                               ($culto->asistencia->chapel_hombres + $culto->asistencia->chapel_mujeres + 
                                $culto->asistencia->chapel_adultos_mayores + $culto->asistencia->chapel_adultos + 
                                $culto->asistencia->chapel_jovenes) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                        @if($culto->asistencia)
                            <div class="flex items-center justify-end gap-3">
                                @if(!$culto->asistencia->cerrado)
                                    <a href="{{ route('asistencia.edit', $culto->asistencia) }}" class="text-blue-600 hover:text-blue-900">Editar</a>
                                    <form action="{{ route('asistencia.destroy', $culto->asistencia) }}" method="POST" class="inline" onsubmit="return confirm('¿Estás seguro de eliminar esta asistencia?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">Eliminar</button>
                                    </form>
                                @else
                                    <span class="text-gray-400 text-xs mr-3">Cerrada</span>
                                    @if(auth()->user()->rol === 'admin')
                                        <form action="{{ route('asistencia.destroy', $culto->asistencia) }}" method="POST" class="inline" onsubmit="return confirm('⚠️ Esta asistencia está cerrada. ¿Estás seguro de eliminarla?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">Eliminar</button>
                                        </form>
                                    @endif
                                @endif
                                <a href="{{ route('ingresos-asistencia.pdf-asistencia-culto', $culto) }}" class="text-green-600 hover:text-green-900" target="_blank">PDF</a>
                            </div>
                        @else
                            <span class="text-gray-400 text-sm">Sin registro</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        No hay registros de asistencia
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <!-- Descargas por Mes -->
    <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl shadow-md p-6 border border-purple-100">
        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2 text-gray-800">
            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Descargas Mensuales
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($mesesDisponibles as $mes)
            <div class="p-4 bg-white border border-purple-200 rounded-lg hover:border-purple-400 hover:shadow-lg transition-all duration-200">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">{{ $mes['nombre'] }}</p>
                        <p class="text-lg font-semibold text-purple-700">{{ $mes['año'] }}</p>
                    </div>
                    <a href="{{ route('ingresos-asistencia.pdf-asistencia-mes', ['mes' => $mes['numero'], 'año' => $mes['año']]) }}" 
                       class="px-3 py-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:from-purple-700 hover:to-pink-700 text-sm font-semibold flex items-center gap-2 shadow-md transition-all" target="_blank">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                        PDF
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

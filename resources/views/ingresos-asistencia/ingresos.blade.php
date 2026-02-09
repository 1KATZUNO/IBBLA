@extends('layouts.admin')

@section('title', 'IBBSC - Reportes de Ingresos')
@section('page-title', 'Reportes de Ingresos')

@section('content')
<div class="space-y-6">
    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" action="{{ route('ingresos-asistencia.ingresos') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="tipo_reporte" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Reporte</label>
                    <select name="tipo_reporte" id="tipo_reporte" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="culto" {{ request('tipo_reporte') == 'culto' ? 'selected' : '' }}>Por Culto</option>
                        <option value="semana" {{ request('tipo_reporte') == 'semana' ? 'selected' : '' }}>Por Semana</option>
                        <option value="mes" {{ request('tipo_reporte') == 'mes' ? 'selected' : '' }}>Por Mes</option>
                    </select>
                </div>
                <div>
                    <label for="fecha_inicio" class="block text-sm font-medium text-gray-700 mb-2">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ request('fecha_inicio') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="fecha_fin" class="block text-sm font-medium text-gray-700 mb-2">Fecha Fin</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="{{ request('fecha_fin') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        Buscar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Botón de Descarga PDF -->
    <div class="flex justify-end">
        <a href="{{ route('ingresos-asistencia.pdf-ingresos', ['tipo_reporte' => request('tipo_reporte', 'culto'), 'fecha_inicio' => request('fecha_inicio'), 'fecha_fin' => request('fecha_fin')]) }}" 
           class="px-6 py-3 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors font-semibold" target="_blank">
            Descargar PDF Completo
        </a>
            <a href="{{ route('ingresos-asistencia.pdf-ingresos-transferencias', ['tipo_reporte' => request('tipo_reporte', 'culto'), 'fecha_inicio' => request('fecha_inicio'), 'fecha_fin' => request('fecha_fin')]) }}" 
                target="_blank"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors ml-2">
                Solo Transferencias (PDF)
            </a>
    </div>

    <!-- Tabla de Ingresos -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold">Detalle de Ingresos</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Diezmo</th>
                           <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ofrenda Especial</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Misiones</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Seminario</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Campamento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Construcción</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Préstamo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Micro</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Suelto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($registros as $registro)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $registro['fecha'] }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ $registro['tipo'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format($registro['diezmo'], 2) }}</td>
                           <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format($registro['ofrenda_especial'] ?? 0, 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format($registro['misiones'], 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format($registro['seminario'], 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format($registro['campa'], 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format($registro['construccion'], 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format($registro['prestamo'], 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format($registro['micro'], 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format($registro['suelto'], 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-green-600">₡{{ number_format($registro['total'], 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                            @if(isset($registro['culto_id']))
                            <a href="{{ route('ingresos-asistencia.pdf-recuento-individual', $registro['culto_id']) }}" 
                               class="inline-flex items-center px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700 transition-colors" 
                               target="_blank">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                                PDF
                            </a>
                                    <span class="mx-2">|</span>
                                    <a href="{{ route('ingresos-asistencia.pdf-recuento-transferencias', $registro['culto_id']) }}" 
                                        target="_blank" 
                                        class="text-blue-600 hover:text-blue-900">Transferencias</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="px-6 py-12 text-center text-gray-500">
                            No hay registros de ingresos
                        </td>
                    </tr>
                    @endforelse
                    @if(count($registros) > 0)
                    <tr class="bg-gray-100 font-bold">
                        <td colspan="2" class="px-6 py-4 text-sm text-gray-900">TOTALES</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format(collect($registros)->sum('diezmo'), 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format(collect($registros)->sum('ofrenda_especial'), 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format(collect($registros)->sum('misiones'), 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format(collect($registros)->sum('seminario'), 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format(collect($registros)->sum('campa'), 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format(collect($registros)->sum('construccion'), 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format(collect($registros)->sum('prestamo'), 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format(collect($registros)->sum('micro'), 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₡{{ number_format(collect($registros)->sum('suelto'), 2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">₡{{ number_format(collect($registros)->sum('total'), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title', tenant_title('Asistencia por clase'))
@section('page-title', 'Asistencia por clase')

@section('content')
@php
    $formato = fn ($promedio) => \App\Services\ReporteAsistenciaClasesService::formatearPromedio($promedio);
    $general = $reporte['general'];
    $variasClases = count($reporte['clases']) > 1;
@endphp
<div class="space-y-6">
    @if($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">
        <ul class="list-disc list-inside text-sm">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" action="{{ route('ingresos-asistencia.asistencia-clases') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="desde" class="block text-sm font-medium text-gray-700 mb-2">Desde</label>
                    <input type="date" name="desde" id="desde" value="{{ $filtros['desde']->toDateString() }}" required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label for="hasta" class="block text-sm font-medium text-gray-700 mb-2">Hasta</label>
                    <input type="date" name="hasta" id="hasta" value="{{ $filtros['hasta']->toDateString() }}" required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label for="clase_id" class="block text-sm font-medium text-gray-700 mb-2">Clase</label>
                    <select name="clase_id" id="clase_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Todas las clases</option>
                        @foreach($clases as $clase)
                            <option value="{{ $clase->id }}" @selected($filtros['clase_id'] === $clase->id)>{{ $clase->nombre }}{{ $clase->activa ? '' : ' (inactiva)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-brand-600 text-white rounded-md hover:bg-brand-700 transition-colors">
                        Consultar
                    </button>
                    <a href="{{ route('ingresos-asistencia.asistencia-clases') }}" title="Volver a los últimos 2 meses, todas las clases"
                       class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                        Limpiar
                    </a>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="incluir_ceros" value="1" @checked($filtros['incluir_ceros'])
                       class="rounded border-gray-300 text-brand-600 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                Contar también las fechas en 0 (clase con 0 niños, 0 niñas y 0 maestros)
            </label>
        </form>
    </div>

    <!-- Cómo se calcula -->
    <div class="bg-brand-50 border border-brand-200 rounded-lg p-4 text-sm text-brand-900">
        <p class="font-semibold mb-1">Cómo se calcula el promedio</p>
        <ul class="list-disc list-inside space-y-1">
            <li>Promedio de una clase = suma de la clase en el rango ÷ fechas en que la clase tiene registro. Lo mismo para niños, niñas, maestros y total.</li>
            <li>Las fechas sin registro (el culto no tiene asistencia, o ese día no se capturó la clase) no cuentan, así que no bajan el promedio.</li>
            <li>Promedio general = suma de todas las clases ÷ fechas con al menos una clase registrada.</li>
            <li>Total = niños + niñas + maestros. Si el mismo día hubo dos cultos con la misma clase, se suman en una sola fila.</li>
            <li>
                @if($filtros['incluir_ceros'])
                    Se están contando también las fechas en 0.
                @else
                    Las fechas con 0 niños, 0 niñas y 0 maestros se tratan como sin registro (los formularios guardan 0 en todas las clases aunque ese día la clase no se reúna). Marque «Contar también las fechas en 0» para incluirlas.
                @endif
            </li>
        </ul>
    </div>

    <!-- Resultado -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
        <div class="px-6 py-4 bg-brand-700 flex flex-wrap justify-between items-center gap-3">
            <div>
                <h3 class="text-lg font-semibold text-white">
                    Del {{ $filtros['desde']->format('d/m/Y') }} al {{ $filtros['hasta']->format('d/m/Y') }}
                </h3>
                <p class="text-brand-100 text-sm mt-1">
                    {{ $reporte['clase_nombre'] ?? 'Todas las clases' }}
                    · {{ $general['fechas'] }} {{ $general['fechas'] === 1 ? 'fecha con registro' : 'fechas con registro' }}
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('ingresos-asistencia.asistencia-clases.pdf', $descarga) }}"
                   class="px-4 py-2 bg-white text-brand-600 rounded-lg hover:bg-brand-50 transition-colors font-semibold shadow-md">
                    PDF
                </a>
                <a href="{{ route('ingresos-asistencia.asistencia-clases.csv', $descarga) }}"
                   class="px-4 py-2 bg-white text-brand-600 rounded-lg hover:bg-brand-50 transition-colors font-semibold shadow-md">
                    CSV
                </a>
                <a href="{{ route('ingresos-asistencia.asistencia-clases.excel', $descarga) }}"
                   class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-semibold shadow-md">
                    Excel
                </a>
            </div>
        </div>

        @if(count($reporte['clases']) === 0)
            <div class="px-6 py-12 text-center text-gray-500">
                No hay asistencia por clase registrada en este rango.
                @if($reporte['cultos_en_rango'] > 0)
                    Hay {{ $reporte['cultos_en_rango'] }} {{ $reporte['cultos_en_rango'] === 1 ? 'culto' : 'cultos' }} en el rango, pero ninguno tiene niños, niñas ni maestros registrados.
                @endif
            </div>
        @else
            <!-- Promedio general por fecha -->
            <div class="p-6 grid grid-cols-2 md:grid-cols-4 gap-4 border-b border-gray-100">
                <div class="bg-brand-50 border-l-4 border-brand-500 rounded-lg p-4">
                    <p class="text-xs text-brand-700 uppercase font-medium">Promedio niños</p>
                    <p class="text-2xl font-bold text-brand-900 mt-1">{{ $formato($general['promedios']['ninos']) }}</p>
                </div>
                <div class="bg-gray-50 border-l-4 border-gray-500 rounded-lg p-4">
                    <p class="text-xs text-gray-700 uppercase font-medium">Promedio niñas</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $formato($general['promedios']['ninas']) }}</p>
                </div>
                <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4">
                    <p class="text-xs text-green-700 uppercase font-medium">Promedio maestros</p>
                    <p class="text-2xl font-bold text-green-900 mt-1">{{ $formato($general['promedios']['maestros']) }}</p>
                </div>
                <div class="bg-gray-50 border-l-4 border-gray-500 rounded-lg p-4">
                    <p class="text-xs text-gray-700 uppercase font-medium">Promedio total por fecha</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $formato($general['promedios']['total']) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Culto</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Niños</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Niñas</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Maestros</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($reporte['clases'] as $clase)
                            <tr style="background-color: {{ $clase['color'] }}15;">
                                <td colspan="6" class="px-3 py-3 text-base font-semibold text-gray-900">
                                    {{ $clase['nombre'] }}{{ $clase['activa'] ? '' : ' (inactiva)' }}
                                </td>
                            </tr>
                            @foreach($clase['filas'] as $fila)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $fila['fecha']->isoFormat('ddd DD/MM/YYYY') }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-500">{{ $fila['cultos'] }}</td>
                                    <td class="px-3 py-3 text-sm text-center text-gray-700">{{ $fila['ninos'] }}</td>
                                    <td class="px-3 py-3 text-sm text-center text-gray-700">{{ $fila['ninas'] }}</td>
                                    <td class="px-3 py-3 text-sm text-center text-gray-700">{{ $fila['maestros'] }}</td>
                                    <td class="px-3 py-3 text-sm text-center font-bold text-gray-900">{{ $fila['total'] }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-brand-50 font-semibold">
                                <td colspan="2" class="px-3 py-3 text-sm text-brand-900">Total {{ $clase['nombre'] }} ({{ $clase['fechas'] }} {{ $clase['fechas'] === 1 ? 'fecha' : 'fechas' }})</td>
                                <td class="px-3 py-3 text-sm text-center text-brand-900">{{ $clase['totales']['ninos'] }}</td>
                                <td class="px-3 py-3 text-sm text-center text-brand-900">{{ $clase['totales']['ninas'] }}</td>
                                <td class="px-3 py-3 text-sm text-center text-brand-900">{{ $clase['totales']['maestros'] }}</td>
                                <td class="px-3 py-3 text-sm text-center text-brand-900">{{ $clase['totales']['total'] }}</td>
                            </tr>
                            <tr class="bg-brand-50 font-semibold">
                                <td colspan="2" class="px-3 py-3 text-sm text-brand-900">
                                    Promedio {{ $clase['nombre'] }}
                                    <span class="font-normal text-xs text-brand-700">(total ÷ {{ $clase['fechas'] }} {{ $clase['fechas'] === 1 ? 'fecha' : 'fechas' }})</span>
                                </td>
                                <td class="px-3 py-3 text-sm text-center text-brand-900">{{ $formato($clase['promedios']['ninos']) }}</td>
                                <td class="px-3 py-3 text-sm text-center text-brand-900">{{ $formato($clase['promedios']['ninas']) }}</td>
                                <td class="px-3 py-3 text-sm text-center text-brand-900">{{ $formato($clase['promedios']['maestros']) }}</td>
                                <td class="px-3 py-3 text-sm text-center text-brand-900">{{ $formato($clase['promedios']['total']) }}</td>
                            </tr>
                        @endforeach

                        @if($variasClases)
                            <tr class="bg-gray-100 font-bold">
                                <td colspan="2" class="px-3 py-3 text-sm text-gray-900">Total general ({{ $general['fechas'] }} {{ $general['fechas'] === 1 ? 'fecha' : 'fechas' }})</td>
                                <td class="px-3 py-3 text-sm text-center text-gray-900">{{ $general['totales']['ninos'] }}</td>
                                <td class="px-3 py-3 text-sm text-center text-gray-900">{{ $general['totales']['ninas'] }}</td>
                                <td class="px-3 py-3 text-sm text-center text-gray-900">{{ $general['totales']['maestros'] }}</td>
                                <td class="px-3 py-3 text-sm text-center text-gray-900">{{ $general['totales']['total'] }}</td>
                            </tr>
                            <tr class="bg-gray-100 font-bold">
                                <td colspan="2" class="px-3 py-3 text-sm text-gray-900">
                                    Promedio general
                                    <span class="font-normal text-xs text-gray-600">(total ÷ {{ $general['fechas'] }} {{ $general['fechas'] === 1 ? 'fecha' : 'fechas' }})</span>
                                </td>
                                <td class="px-3 py-3 text-sm text-center text-gray-900">{{ $formato($general['promedios']['ninos']) }}</td>
                                <td class="px-3 py-3 text-sm text-center text-gray-900">{{ $formato($general['promedios']['ninas']) }}</td>
                                <td class="px-3 py-3 text-sm text-center text-gray-900">{{ $formato($general['promedios']['maestros']) }}</td>
                                <td class="px-3 py-3 text-sm text-center text-gray-900">{{ $formato($general['promedios']['total']) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif

        @if(count($reporte['fechas_sin_registro']) > 0)
            <div class="px-6 py-4 border-t border-gray-100 text-sm text-gray-600">
                <span class="font-medium text-gray-700">Fechas del rango sin registro{{ $filtros['clase_id'] ? ' de esta clase' : ' de clases' }} (no cuentan en el promedio):</span>
                {{ collect($reporte['fechas_sin_registro'])->map(fn ($fecha) => $fecha->format('d/m/Y'))->implode(', ') }}
            </div>
        @endif
    </div>
</div>
@endsection

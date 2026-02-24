@extends('layouts.admin')

@section('title', 'Reporte de Servidores')
@section('page-title', 'Reporte de Servidores')

@section('content')
<div class="space-y-6">
    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" action="{{ route('admin.servidores.reporte') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="mes" class="block text-sm font-medium text-gray-700 mb-2">Mes</label>
                    <select name="mes" id="mes" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach($meses as $numMes => $nombreMes)
                            <option value="{{ $numMes }}" {{ $mes == $numMes ? 'selected' : '' }}>{{ $nombreMes }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="ano" class="block text-sm font-medium text-gray-700 mb-2">Ano</label>
                    <select name="ano" id="ano" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @for($y = now()->year; $y >= now()->year - 3; $y--)
                            <option value="{{ $y }}" {{ $ano == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
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

    <!-- Resumen -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl shadow-lg p-6 text-white">
            <p class="text-sm text-blue-100 font-medium mb-1">Total Servidores</p>
            <p class="text-3xl font-bold">{{ $servidores->count() }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-xl shadow-lg p-6 text-white">
            <p class="text-sm text-green-100 font-medium mb-1">Cultos en {{ $meses[$mes] }}</p>
            <p class="text-3xl font-bold">{{ $totalCultosMes }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl shadow-lg p-6 text-white">
            <p class="text-sm text-purple-100 font-medium mb-1">Promedio Asistencia</p>
            <p class="text-3xl font-bold">
                @if($servidores->count() > 0 && $totalCultosMes > 0)
                    {{ round($asistenciasPorServidor->sum() / $servidores->count() / $totalCultosMes * 100) }}%
                @else
                    0%
                @endif
            </p>
        </div>
    </div>

    <!-- Tabla de Servidores -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold">Servidores - {{ $meses[$mes] }} {{ $ano }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Servidor</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Asistencia</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">% Asistencia</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Promesas</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($servidores as $servidor)
                    @php
                        $asist = $asistenciasPorServidor[$servidor->id] ?? 0;
                        $porcAsist = $totalCultosMes > 0 ? round(($asist / $totalCultosMes) * 100) : 0;
                        $promesas = $promesasPorServidor[$servidor->id] ?? collect();
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <span class="text-blue-600 font-semibold text-sm">{{ strtoupper(substr($servidor->name, 0, 1)) }}</span>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $servidor->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $servidor->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                            <span class="font-semibold">{{ $asist }}</span> / {{ $totalCultosMes }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <div class="flex items-center justify-center gap-2">
                                <div class="w-24 bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full {{ $porcAsist >= 80 ? 'bg-green-500' : ($porcAsist >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                         style="width: {{ $porcAsist }}%"></div>
                                </div>
                                <span class="text-sm font-medium {{ $porcAsist >= 80 ? 'text-green-600' : ($porcAsist >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ $porcAsist }}%
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                            @if($promesas->count() > 0)
                                <div class="space-y-1">
                                    @foreach($promesas as $promesa)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ ucfirst($promesa->categoria) }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-gray-400 text-xs">Sin promesas</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                            No hay servidores registrados
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Volver -->
    <div class="flex justify-start">
        <a href="{{ route('admin.servidores.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
            Volver a Servidores
        </a>
    </div>
</div>
@endsection

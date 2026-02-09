@extends('layouts.admin')

@section('title', 'IBBSC - Ingresos y Asistencia')
@section('page-title', 'Ingresos y Asistencia')

@section('content')
<div class="space-y-6">
    <!-- Botones Principales -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Botón Asistencia -->
        <a href="{{ route('ingresos-asistencia.asistencia') }}" class="block">
            <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 p-8 text-white cursor-pointer">
                <div class="flex items-center justify-between mb-4">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold mb-2">Asistencia</h2>
                <p class="text-blue-100 text-lg">Ver y descargar registros de asistencia</p>
            </div>
        </a>

        <!-- Botón Ingresos -->
        <a href="{{ route('ingresos-asistencia.ingresos') }}" class="block">
            <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 p-8 text-white cursor-pointer">
                <div class="flex items-center justify-between mb-4">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold mb-2">Ingresos</h2>
                <p class="text-green-100 text-lg">Ver y descargar reportes de ingresos</p>
            </div>
        </a>

        <!-- Botón Promesas -->
        <a href="{{ route('ingresos-asistencia.promesas') }}" class="block">
            <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 p-8 text-white cursor-pointer">
                <div class="flex items-center justify-between mb-4">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold mb-2">Promesas</h2>
                <p class="text-purple-100 text-lg">Ver reporte de promesas y compromisos</p>
            </div>
        </a>
    </div>

    <!-- Resumen Rápido -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-xl font-bold mb-4 text-gray-800">Resumen de esta Semana</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg border border-blue-200">
                <p class="text-sm text-blue-600 font-medium mb-1">Total de Cultos</p>
                <p class="text-3xl font-bold text-blue-700">{{ $cultosSemanales->count() }}</p>
            </div>
            <div class="p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-lg border border-green-200">
                <p class="text-sm text-green-600 font-medium mb-1">Total Ingresos</p>
                <p class="text-3xl font-bold text-green-700">₡{{ number_format($totalSemanal, 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg border border-purple-200">
                <p class="text-sm text-purple-600 font-medium mb-1">Asistencia Total</p>
                <p class="text-3xl font-bold text-purple-700">{{ $cultosSemanales->sum(fn($c) => $c->asistencia?->total_asistencia ?? 0) }}</p>
            </div>
        </div>
    </div>

    <!-- Distribución por Categorías -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Distribución por Categorías</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-4 bg-blue-50 rounded-lg">
                <p class="text-sm text-gray-600">Diezmo</p>
                <p class="text-xl font-bold text-blue-600">₡{{ number_format($categorias['diezmo'], 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-green-50 rounded-lg">
                <p class="text-sm text-gray-600">Misiones</p>
                <p class="text-xl font-bold text-green-600">₡{{ number_format($categorias['misiones'], 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-yellow-50 rounded-lg">
                <p class="text-sm text-gray-600">Seminario</p>
                <p class="text-xl font-bold text-yellow-600">₡{{ number_format($categorias['seminario'], 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-purple-50 rounded-lg">
                <p class="text-sm text-gray-600">Construcción</p>
                <p class="text-xl font-bold text-purple-600">₡{{ number_format($categorias['construccion'], 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-red-50 rounded-lg">
                <p class="text-sm text-gray-600">Campamento</p>
                <p class="text-xl font-bold text-red-600">₡{{ number_format($categorias['campa'], 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-pink-50 rounded-lg">
                <p class="text-sm text-gray-600">Préstamo</p>
                <p class="text-xl font-bold text-pink-600">₡{{ number_format($categorias['prestamo'], 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-indigo-50 rounded-lg">
                <p class="text-sm text-gray-600">Micro</p>
                <p class="text-xl font-bold text-indigo-600">₡{{ number_format($categorias['micro'], 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600">Suelto</p>
                <p class="text-xl font-bold text-gray-600">₡{{ number_format($categorias['suelto'], 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    <!-- Cultos de la Semana -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold">Cultos de la Semana</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ingresos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asistencia</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($cultosSemanales as $culto)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $culto->fecha->format('d/m/Y') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            {{ ucfirst($culto->tipo_culto) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                        ₡{{ $culto->totales ? number_format($culto->totales->total_general, 0, ',', '.') : '0' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $culto->asistencia ? $culto->asistencia->total_asistencia : '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                        No hay cultos registrados esta semana
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

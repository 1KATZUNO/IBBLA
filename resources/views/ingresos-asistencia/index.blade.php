@extends('layouts.admin')

@section('title', tenant_title('Ingresos y Asistencia'))
@section('page-title', 'Ingresos y Asistencia')

@section('content')
<div class="space-y-6">
    <!-- Botones Principales: cada reporte aparece solo si el rol lo puede abrir (antes el de asistencia daba 403 a tesoreria) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
        @if(Auth::user()->canAccessAsistencia())
        <!-- Botón Asistencia -->
        <a href="{{ route('ingresos-asistencia.asistencia') }}" class="block">
            <div class="h-full bg-brand-600 rounded-xl shadow-lg hover:shadow-2xl transition-shadow duration-300 p-6 xl:p-8 text-white cursor-pointer">
                <div class="flex items-center justify-between mb-4">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl xl:text-3xl font-bold mb-2">Asistencia</h2>
                <p class="text-brand-100 text-lg">Ver y descargar registros de asistencia</p>
            </div>
        </a>

        @if(Route::has('ingresos-asistencia.asistencia-clases'))
        <!-- Asistencia por clase en un rango de fechas (pantalla nueva de la rama de asistencia por rango) -->
        <a href="{{ route('ingresos-asistencia.asistencia-clases') }}" class="block">
            <div class="h-full bg-brand-800 rounded-xl shadow-lg hover:shadow-2xl transition-shadow duration-300 p-6 xl:p-8 text-white cursor-pointer">
                <div class="flex items-center justify-between mb-4">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl xl:text-3xl font-bold mb-2">Asistencia por clase</h2>
                <p class="text-brand-100 text-lg">Ninos, ninas, maestros y promedio en un rango de fechas</p>
            </div>
        </a>
        @endif
        @endif

        <!-- Botón Ingresos -->
        <a href="{{ route('ingresos-asistencia.ingresos') }}" class="block">
            <div class="h-full bg-green-600 rounded-xl shadow-lg hover:shadow-2xl transition-shadow duration-300 p-6 xl:p-8 text-white cursor-pointer">
                <div class="flex items-center justify-between mb-4">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl xl:text-3xl font-bold mb-2">Ingresos</h2>
                <p class="text-green-100 text-lg">Ver y descargar reportes de ingresos</p>
            </div>
        </a>

        <!-- Botón Promesas -->
        <a href="{{ route('ingresos-asistencia.promesas') }}" class="block">
            <div class="h-full bg-gray-700 rounded-xl shadow-lg hover:shadow-2xl transition-shadow duration-300 p-6 xl:p-8 text-white cursor-pointer">
                <div class="flex items-center justify-between mb-4">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                </div>
                <h2 class="text-2xl xl:text-3xl font-bold mb-2">Promesas</h2>
                <p class="text-gray-100 text-lg">Ver reporte de promesas y compromisos</p>
            </div>
        </a>
    </div>

    <!-- Resumen Rápido -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-xl font-bold mb-4 text-gray-800">Resumen de esta Semana</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-brand-50 rounded-lg border border-brand-200">
                <p class="text-sm text-brand-600 font-medium mb-1">Total de Cultos</p>
                <p class="text-3xl font-bold text-brand-700">{{ $cultosSemanales->count() }}</p>
            </div>
            <div class="p-4 bg-green-50 rounded-lg border border-green-200">
                <p class="text-sm text-green-600 font-medium mb-1">Total Ingresos</p>
                <p class="text-3xl font-bold text-green-700">₡{{ number_format($totalSemanal, 0, ',', '.') }}</p>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                <p class="text-sm text-gray-600 font-medium mb-1">Asistencia Total</p>
                <p class="text-3xl font-bold text-gray-700">{{ $cultosSemanales->sum(fn($c) => $c->asistencia?->total_asistencia ?? 0) }}</p>
            </div>
        </div>
    </div>

    <!-- Distribución por Categorías -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Distribución por Categorías</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($categories as $cat)
            <div class="p-4 rounded-lg" style="background-color: {{ $cat->color }}15;">
                <p class="text-sm text-gray-600">{{ $cat->nombre }}</p>
                <p class="text-xl font-bold" style="color: {{ $cat->color }};">₡{{ number_format($categorias[$cat->slug] ?? 0, 0, ',', '.') }}</p>
            </div>
            @endforeach
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600">Suelto</p>
                <p class="text-xl font-bold text-gray-600">₡{{ number_format($categorias['suelto'] ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    <!-- Cultos de la Semana -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold">Cultos de la Semana</h3>
        </div>
        <div class="table-scroll">
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
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-brand-100 text-brand-800">
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
</div>
@endsection

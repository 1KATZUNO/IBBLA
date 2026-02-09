@extends('layouts.admin')

@section('title', 'IBBSC - Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6 dashboard-content">
    <!-- Selector de Mes -->
    <div class="bg-white rounded-lg shadow p-4">
        <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-4">
            <div>
                <label for="mes" class="block text-sm font-medium text-gray-700 mb-1">Mes</label>
                <select name="mes" id="mes" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach(['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $index => $nombreMes)
                        <option value="{{ $index + 1 }}" {{ ($index + 1) == $mes ? 'selected' : '' }}>{{ $nombreMes }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="año" class="block text-sm font-medium text-gray-700 mb-1">Año</label>
                <select name="año" id="año" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" {{ $y == $año ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="pt-6">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    Actualizar
                </button>
            </div>
        </form>
    </div>

    <!-- Estadísticas Rápidas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 stats-grid">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Mensual</dt>
                        <dd class="text-lg font-semibold text-gray-900">₡{{ number_format($totalesMes['total_general'], 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Diezmos</dt>
                        <dd class="text-lg font-semibold text-gray-900">₡{{ number_format($totalesMes['total_diezmo'], 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Misiones</dt>
                        <dd class="text-lg font-semibold text-gray-900">₡{{ number_format($totalesMes['total_misiones'], 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Construcción</dt>
                        <dd class="text-lg font-semibold text-gray-900">₡{{ number_format($totalesMes['total_construccion'], 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Suelto</dt>
                        <dd class="text-lg font-semibold text-gray-900">₡{{ number_format($totalesMes['total_suelto'], 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-orange-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Seminario</dt>
                        <dd class="text-lg font-semibold text-gray-900">₡{{ number_format($totalesMes['total_seminario'], 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-pink-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Campamento</dt>
                        <dd class="text-lg font-semibold text-gray-900">₡{{ number_format($totalesMes['total_campa'], 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Préstamo</dt>
                        <dd class="text-lg font-semibold text-gray-900">₡{{ number_format($totalesMes['total_prestamo'], 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-teal-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Micro</dt>
                        <dd class="text-lg font-semibold text-gray-900">₡{{ number_format($totalesMes['total_micro'], 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Gráfico de Barras - Ingresos por Culto -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Ingresos por Culto (Últimos 10)</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="ingresosChart"></canvas>
            </div>
        </div>

        <!-- Gráfico Circular - Distribución por Categorías -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Distribución por Categorías (Mes Actual)</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="distribucionChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Asistencia y Promesas -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Línea de Tiempo - Asistencia -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Tendencia de Asistencia</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="asistenciaChart"></canvas>
            </div>
        </div>

        <!-- Promesas Cumplidas vs Pendientes -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Estado de Promesas (Mes Actual)</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="promesasChart"></canvas>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Gráfico de Ingresos por Culto
    const ingresosCtx = document.getElementById('ingresosChart').getContext('2d');
    new Chart(ingresosCtx, {
        type: 'bar',
        data: {
            labels: @json($cultosRecientes->map(fn($c) => $c->fecha->format('d/m'))),
            datasets: [{
                label: 'Ingresos Totales',
                data: @json($cultosRecientes->map(fn($c) => $c->totales ? $c->totales->total_general : 0)),
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            height: 300,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₡' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // Gráfico de Distribución por Categorías
    const distribucionCtx = document.getElementById('distribucionChart').getContext('2d');
    new Chart(distribucionCtx, {
        type: 'doughnut',
        data: {
            labels: ['Diezmo', 'Misiones', 'Seminario', 'Campamento', 'Préstamo', 'Construcción', 'Micro', 'Suelto'],
            datasets: [{
                data: [
                    {{ $distribucion['diezmo'] }},
                    {{ $distribucion['misiones'] }},
                    {{ $distribucion['seminario'] }},
                    {{ $distribucion['campa'] }},
                    {{ $distribucion['prestamo'] }},
                    {{ $distribucion['construccion'] }},
                    {{ $distribucion['micro'] }},
                    {{ $distribucion['suelto'] }}
                ],
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(139, 92, 246, 0.8)',
                    'rgba(236, 72, 153, 0.8)',
                    'rgba(14, 165, 233, 0.8)',
                    'rgba(156, 163, 175, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            height: 300
        }
    });

    // Gráfico de Asistencia
    const asistenciaCtx = document.getElementById('asistenciaChart').getContext('2d');
    new Chart(asistenciaCtx, {
        type: 'line',
        data: {
            labels: @json($asistencias->pluck('fecha')),
            datasets: [{
                label: 'Asistencia Total',
                data: @json($asistencias->pluck('total')),
                borderColor: 'rgba(16, 185, 129, 1)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            height: 300,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Gráfico de Promesas
    const promesasCtx = document.getElementById('promesasChart').getContext('2d');
    new Chart(promesasCtx, {
        type: 'pie',
        data: {
            labels: ['Cumplidas', 'Pendientes'],
            datasets: [{
                data: [{{ $promesasStatus['cumplidas'] }}, {{ $promesasStatus['pendientes'] }}],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(239, 68, 68, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            height: 300
        }
    });
</script>
@endpush

@push('styles')
<style>
    /* Animaciones de entrada para el dashboard */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    /* Aplicar animaciones */
    .dashboard-content {
        animation: fadeInUp 0.6s ease-out;
    }
    
    .stats-grid > div {
        opacity: 0;
        animation: slideInRight 0.5s ease-out forwards;
    }
    
    .stats-grid > div:nth-child(1) { animation-delay: 0.1s; }
    .stats-grid > div:nth-child(2) { animation-delay: 0.2s; }
    .stats-grid > div:nth-child(3) { animation-delay: 0.3s; }
    .stats-grid > div:nth-child(4) { animation-delay: 0.4s; }
    .stats-grid > div:nth-child(5) { animation-delay: 0.5s; }
    .stats-grid > div:nth-child(6) { animation-delay: 0.6s; }
    .stats-grid > div:nth-child(7) { animation-delay: 0.7s; }
    .stats-grid > div:nth-child(8) { animation-delay: 0.8s; }
    .stats-grid > div:nth-child(9) { animation-delay: 0.9s; }
    
    /* Hover effect mejorado para las tarjetas */
    .stats-grid > div {
        transition: all 0.3s ease;
    }
    
    .stats-grid > div:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }
    
    /* Animación para los gráficos */
    canvas {
        opacity: 0;
        animation: scaleIn 0.8s ease-out 0.5s forwards;
    }
</style>
@endpush

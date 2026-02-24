@extends('layouts.admin')

@section('title', 'IBBSC - Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-8 animate-fade-in">
    <!-- Selector de Mes -->
    <div class="glass-card p-6">
        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="mes" class="block text-sm font-semibold text-gray-700 mb-2">Mes</label>
                <select name="mes" id="mes" class="input-primary">
                    @foreach(['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $index => $nombreMes)
                        <option value="{{ $index + 1 }}" {{ ($index + 1) == $mes ? 'selected' : '' }}>{{ $nombreMes }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[150px]">
                <label for="año" class="block text-sm font-semibold text-gray-700 mb-2">Año</label>
                <select name="año" id="año" class="input-primary">
                    @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" {{ $y == $año ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <button type="submit" class="btn-primary flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Actualizar
                </button>
            </div>
        </form>
    </div>

    <!-- Estadísticas Rápidas -->
    <div>
        <h3 class="text-lg font-display font-bold text-gray-900 mb-4">Estadísticas del Mes</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            <!-- Total Mensual -->
            <div class="stat-card">
                <div class="flex flex-col h-full">
                    <div class="bg-blue-600 rounded-lg p-2.5 w-fit mb-3">
                        <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <dl class="flex-1">
                        <dt class="text-sm font-medium text-gray-500 mb-1">Total Mensual</dt>
                        <dd class="text-lg font-display font-bold text-blue-700">₡{{ number_format($totalesMes['total_general'], 2) }}</dd>
                    </dl>
                </div>
            </div>

            @foreach($categories as $cat)
            <div class="stat-card">
                <div class="flex flex-col h-full">
                    <div class="rounded-lg p-2.5 w-fit mb-3" style="background-color: {{ $cat->color ?? '#6366f1' }}">
                        <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <dl class="flex-1">
                        <dt class="text-sm font-medium text-gray-500 mb-1">{{ $cat->nombre }}</dt>
                        <dd class="text-lg font-display font-bold" style="color: {{ $cat->color ?? '#6366f1' }}">₡{{ number_format($totalesMes[$cat->slug] ?? 0, 2) }}</dd>
                    </dl>
                </div>
            </div>
            @endforeach

            <!-- Suelto -->
            <div class="stat-card">
                <div class="flex flex-col h-full">
                    <div class="bg-indigo-500 rounded-lg p-2.5 w-fit mb-3">
                        <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <dl class="flex-1">
                        <dt class="text-sm font-medium text-gray-500 mb-1">Suelto</dt>
                        <dd class="text-lg font-display font-bold text-indigo-600">₡{{ number_format($totalesMes['total_suelto'], 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div>
        <h3 class="text-lg font-display font-bold text-gray-900 mb-4">Análisis Visual</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Gráfico de Barras - Ingresos por Culto -->
            <div class="glass-card p-6">
                <h3 class="text-base font-display font-semibold text-gray-900 mb-4">Ingresos por Culto (Últimos 10)</h3>
                <div style="height: 300px; position: relative;">
                    <canvas id="ingresosChart"></canvas>
                </div>
            </div>

            <!-- Gráfico Circular - Distribución por Categorías -->
            <div class="glass-card p-6">
                <h3 class="text-base font-display font-semibold text-gray-900 mb-4">Distribución por Categorías (Mes Actual)</h3>
                <div style="height: 300px; position: relative;">
                    <canvas id="distribucionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Asistencia y Promesas -->
    <div>
        <h3 class="text-lg font-display font-bold text-gray-900 mb-4">Tendencias</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Línea de Tiempo - Asistencia -->
            <div class="glass-card p-6">
                <h3 class="text-base font-display font-semibold text-gray-900 mb-4">Tendencia de Asistencia</h3>
                <div style="height: 300px; position: relative;">
                    <canvas id="asistenciaChart"></canvas>
                </div>
            </div>

            <!-- Promesas Cumplidas vs Pendientes -->
            <div class="glass-card p-6">
                <h3 class="text-base font-display font-semibold text-gray-900 mb-4">Estado de Promesas (Mes Actual)</h3>
                <div style="height: 300px; position: relative;">
                    <canvas id="promesasChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Paleta de colores azul corporativo
    const colors = {
        primary: 'rgba(37, 99, 235, 0.8)',
        primaryBorder: 'rgba(37, 99, 235, 1)',
        secondary: 'rgba(59, 130, 246, 0.8)',
        secondaryBorder: 'rgba(59, 130, 246, 1)',
        green: 'rgba(34, 197, 94, 0.8)',
        greenBorder: 'rgba(34, 197, 94, 1)',
        gradient: (ctx) => {
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(37, 99, 235, 0.8)');
            gradient.addColorStop(1, 'rgba(37, 99, 235, 0.2)');
            return gradient;
        }
    };

    // Gráfico de Ingresos por Culto
    const ingresosCtx = document.getElementById('ingresosChart').getContext('2d');
    new Chart(ingresosCtx, {
        type: 'bar',
        data: {
            labels: @json($cultosRecientes->map(fn($c) => $c->fecha->format('d/m'))),
            datasets: [{
                label: 'Ingresos Totales',
                data: @json($cultosRecientes->map(fn($c) => $c->totales ? $c->totales->total_general : 0)),
                backgroundColor: colors.gradient(ingresosCtx),
                borderColor: colors.primaryBorder,
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '₡' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Gráfico de Distribución por Categorías - Dynamic
    const distribucionCtx = document.getElementById('distribucionChart').getContext('2d');
    @php
        $distLabels = collect($categories)->pluck('nombre')->push('Suelto');
        $distData = collect($categories)->map(fn($c) => $distribucion[$c->slug] ?? 0)->push($distribucion['suelto'] ?? 0);
        $distColors = collect($categories)->pluck('color')->map(fn($c) => $c ? $c . 'cc' : 'rgba(99,102,241,0.8)')->push('rgba(99, 102, 241, 0.8)');
    @endphp
    const distLabels = @json($distLabels);
    const distData = @json($distData);
    const distColors = @json($distColors);
    new Chart(distribucionCtx, {
        type: 'doughnut',
        data: {
            labels: distLabels,
            datasets: [{
                data: distData,
                backgroundColor: distColors,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            }
        }
    });

    // Gráfico de Asistencia
    const asistenciaCtx = document.getElementById('asistenciaChart').getContext('2d');
    const asistenciaGradient = asistenciaCtx.createLinearGradient(0, 0, 0, 300);
    asistenciaGradient.addColorStop(0, 'rgba(34, 197, 94, 0.3)');
    asistenciaGradient.addColorStop(1, 'rgba(34, 197, 94, 0)');

    new Chart(asistenciaCtx, {
        type: 'line',
        data: {
            labels: @json($asistencias->pluck('fecha')),
            datasets: [{
                label: 'Asistencia Total',
                data: @json($asistencias->pluck('total')),
                borderColor: 'rgba(34, 197, 94, 1)',
                backgroundColor: asistenciaGradient,
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: 'rgba(34, 197, 94, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
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
                    'rgba(34, 197, 94, 0.9)',
                    'rgba(59, 130, 246, 0.9)'
                ],
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            }
        }
    });
</script>
@endpush

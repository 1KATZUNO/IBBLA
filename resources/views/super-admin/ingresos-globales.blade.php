@extends('layouts.super-admin')

@section('title', 'Ingresos Globales')
@section('page-title', 'Ingresos Globales')

@section('content')
<div class="space-y-6">
    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="GET" action="{{ route('super-admin.ingresos-globales') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="mes" class="block text-sm font-medium text-gray-700 mb-2">Mes</label>
                    <select name="mes" id="mes" class="w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @php
                            $meses = [
                                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                            ];
                        @endphp
                        @foreach($meses as $numMes => $nombreMes)
                            <option value="{{ $numMes }}" {{ $mes == $numMes ? 'selected' : '' }}>{{ $nombreMes }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="año" class="block text-sm font-medium text-gray-700 mb-2">Ano</label>
                    <select name="año" id="año" class="w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @foreach($añosDisponibles as $añoDisponible)
                            <option value="{{ $añoDisponible }}" {{ $año == $añoDisponible ? 'selected' : '' }}>{{ $añoDisponible }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="tenant_id" class="block text-sm font-medium text-gray-700 mb-2">Iglesia</label>
                    <select name="tenant_id" id="tenant_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">Todas las iglesias</option>
                        @foreach($tenants as $t)
                            <option value="{{ $t->id }}" {{ $tenantId == $t->id ? 'selected' : '' }}>{{ $t->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-slate-700 text-white rounded-md hover:bg-slate-800 transition-colors font-medium">
                        Actualizar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Cards de Resumen -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total General</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">₡{{ number_format($resumen['total_general'], 0, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Suelto</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">₡{{ number_format($resumen['total_suelto'], 0, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Egresos</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">₡{{ number_format($resumen['total_egresos'], 0, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Cantidad de Cultos</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $resumen['cantidad_cultos'] }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    @if($tenantId && $tenantSeleccionado)
        <!-- Modo: Una iglesia - Tabla por Categoria -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900">
                    Desglose por Categoria - {{ $tenantSeleccionado->nombre }}
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $meses[(int)$mes] ?? '' }} {{ $año }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-700 text-white">
                            <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Categoria</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($datosPorCategoria as $slug => $cat)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full" style="background-color: {{ $cat['color'] ?? '#6b7280' }}"></div>
                                    <span class="font-medium text-gray-900">{{ $cat['nombre'] }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right font-semibold text-gray-900">
                                ₡{{ number_format($cat['total'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2" class="px-6 py-12 text-center text-gray-500">
                                No hay datos de ingresos para este periodo
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Doughnut Chart para una iglesia -->
        @if(count($datosPorCategoria) > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Distribucion por Categoria</h3>
            <div class="max-w-md mx-auto">
                <canvas id="doughnutChart" height="300"></canvas>
            </div>
        </div>
        @endif
    @else
        <!-- Modo: Todas las iglesias - Tabla comparativa -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900">Comparativa por Iglesia</h3>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $meses[(int)$mes] ?? '' }} {{ $año }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-700 text-white">
                            <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Iglesia</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Total</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Suelto</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Egresos</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold uppercase tracking-wider">Cultos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($datosPorIglesia as $dato)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-sm font-bold" style="background-color: {{ \App\Models\Tenant::COLOR_THEMES[$dato['tenant']->color_theme]['600'] ?? '#475569' }}">
                                        {{ substr($dato['tenant']->siglas, 0, 2) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $dato['tenant']->nombre }}</p>
                                        <p class="text-xs text-gray-500">{{ $dato['tenant']->siglas }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right font-semibold text-green-600">
                                ₡{{ number_format($dato['total_general'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-blue-600">
                                ₡{{ number_format($dato['total_suelto'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-red-600">
                                ₡{{ number_format($dato['total_egresos'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-center text-sm text-gray-700">
                                {{ $dato['cantidad_cultos'] }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                No hay iglesias activas
                            </td>
                        </tr>
                        @endforelse
                        @if(count($datosPorIglesia) > 0)
                        <tr class="bg-gray-100 font-bold">
                            <td class="px-6 py-4 text-sm text-gray-900">TOTALES</td>
                            <td class="px-6 py-4 text-right text-green-700">₡{{ number_format($resumen['total_general'], 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right text-blue-700">₡{{ number_format($resumen['total_suelto'], 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right text-red-700">₡{{ number_format($resumen['total_egresos'], 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-center text-gray-900">{{ $resumen['cantidad_cultos'] }}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bar Chart comparativo -->
        @if(count($datosPorIglesia) > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Comparativa de Ingresos por Iglesia</h3>
            <canvas id="barChart" height="300"></canvas>
        </div>
        @endif
    @endif

    <!-- Tendencia Mensual (siempre visible) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4">
            Tendencia Mensual {{ $año }}
            @if($tenantSeleccionado)
                - {{ $tenantSeleccionado->nombre }}
            @else
                - Todas las Iglesias
            @endif
        </h3>
        <canvas id="lineChart" height="200"></canvas>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tendencia mensual (Line Chart)
    const tendenciaData = @json($tendencia);
    new Chart(document.getElementById('lineChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: tendenciaData.map(d => d.mes),
            datasets: [{
                label: 'Total General',
                data: tendenciaData.map(d => d.total),
                borderColor: '#475569',
                backgroundColor: 'rgba(71, 85, 105, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 4,
                pointBackgroundColor: '#475569',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return '₡' + new Intl.NumberFormat('es-CR').format(ctx.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₡' + new Intl.NumberFormat('es-CR').format(value);
                        }
                    }
                }
            }
        }
    });

    @if($tenantId && $tenantSeleccionado && count($datosPorCategoria) > 0)
    // Doughnut Chart (una iglesia)
    const catData = @json(array_values($datosPorCategoria));
    new Chart(document.getElementById('doughnutChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: catData.map(d => d.nombre),
            datasets: [{
                data: catData.map(d => d.total),
                backgroundColor: catData.map(d => d.color || '#6b7280'),
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.label + ': ₡' + new Intl.NumberFormat('es-CR').format(ctx.raw);
                        }
                    }
                }
            }
        }
    });
    @endif

    @if(!$tenantId && count($datosPorIglesia) > 0)
    // Bar Chart (todas las iglesias)
    const iglesiaData = @json($datosPorIglesia);
    new Chart(document.getElementById('barChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: iglesiaData.map(d => d.tenant.siglas),
            datasets: [{
                label: 'Total General',
                data: iglesiaData.map(d => d.total_general),
                backgroundColor: '#475569',
                borderRadius: 6,
            }, {
                label: 'Suelto',
                data: iglesiaData.map(d => d.total_suelto),
                backgroundColor: '#3b82f6',
                borderRadius: 6,
            }, {
                label: 'Egresos',
                data: iglesiaData.map(d => d.total_egresos),
                backgroundColor: '#ef4444',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': ₡' + new Intl.NumberFormat('es-CR').format(ctx.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₡' + new Intl.NumberFormat('es-CR').format(value);
                        }
                    }
                }
            }
        }
    });
    @endif
});
</script>
@endpush

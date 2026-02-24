@extends('layouts.super-admin')

@section('title', 'Promesas Globales')
@section('page-title', 'Promesas Globales')

@section('content')
<div class="space-y-6">
    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="GET" action="{{ route('super-admin.promesas-globales') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="mes" class="block text-sm font-medium text-gray-700 mb-2">Mes</label>
                    <select name="mes" id="mes" class="w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="todos" {{ $mes == 'todos' ? 'selected' : '' }}>Todos los meses</option>
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
                        Buscar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Cards de Resumen (gradient) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
            <p class="text-sm text-blue-100 font-medium mb-1">Total Prometido</p>
            <p class="text-3xl font-bold">₡{{ number_format($resumen['prometido'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p class="text-sm text-green-100 font-medium mb-1">Total Dado</p>
            <p class="text-3xl font-bold">₡{{ number_format($resumen['dado'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-gradient-to-br from-red-500 to-red-700 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p class="text-sm text-red-100 font-medium mb-1">Total Faltante</p>
            <p class="text-3xl font-bold">₡{{ number_format($resumen['faltante'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
            <p class="text-sm text-purple-100 font-medium mb-1">Total Profit</p>
            <p class="text-3xl font-bold">₡{{ number_format($resumen['profit'], 0, ',', '.') }}</p>
        </div>
    </div>

    @if($tenantId && $tenantSeleccionado)
        <!-- Modo: Una iglesia - Tabla por Categoria -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900">
                    Detalle por Categoria - {{ $tenantSeleccionado->nombre }}
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                    @if($mes == 'todos')
                        Todo el ano {{ $año }}
                    @else
                        {{ $meses[(int)$mes] ?? '' }} {{ $año }}
                    @endif
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-700 text-white">
                            <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Categoria</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Prometido</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Dado</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Faltante</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($datosPorCategoria as $cat)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                {{ $cat['categoria'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-blue-600 font-medium">
                                ₡{{ number_format($cat['total_prometido'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600 font-medium">
                                ₡{{ number_format($cat['total_dado'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 font-bold">
                                ₡{{ number_format($cat['faltante'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-purple-600 font-bold">
                                ₡{{ number_format($cat['profit'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                No hay datos de promesas para este periodo
                            </td>
                        </tr>
                        @endforelse
                        @if(count($datosPorCategoria) > 0)
                        <tr class="bg-gray-100 font-bold text-gray-900">
                            <td class="px-6 py-4 text-sm">TOTALES GENERALES</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-blue-700">
                                ₡{{ number_format($resumen['prometido'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-700">
                                ₡{{ number_format($resumen['dado'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-700">
                                ₡{{ number_format($resumen['faltante'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-purple-700">
                                ₡{{ number_format($resumen['profit'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <!-- Modo: Todas las iglesias - Tabla comparativa -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900">Cumplimiento de Promesas por Iglesia</h3>
                <p class="text-sm text-gray-500 mt-1">
                    @if($mes == 'todos')
                        Todo el ano {{ $año }}
                    @else
                        {{ $meses[(int)$mes] ?? '' }} {{ $año }}
                    @endif
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-700 text-white">
                            <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Iglesia</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Prometido</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Dado</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Faltante</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Profit</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold uppercase tracking-wider">% Cumplimiento</th>
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
                            <td class="px-6 py-4 text-right text-sm text-blue-600 font-medium">
                                ₡{{ number_format($dato['prometido'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-green-600 font-medium">
                                ₡{{ number_format($dato['dado'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-red-600 font-bold">
                                ₡{{ number_format($dato['faltante'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm text-purple-600 font-bold">
                                ₡{{ number_format($dato['profit'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $pct = $dato['porcentaje'];
                                    $colorClass = $pct >= 100 ? 'bg-green-100 text-green-800' : ($pct >= 75 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $colorClass }}">
                                    {{ $pct }}%
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                No hay iglesias activas
                            </td>
                        </tr>
                        @endforelse
                        @if(count($datosPorIglesia) > 0)
                        @php
                            $pctTotal = $resumen['prometido'] > 0 ? round(($resumen['dado'] / $resumen['prometido']) * 100, 1) : ($resumen['dado'] > 0 ? 100 : 0);
                        @endphp
                        <tr class="bg-gray-100 font-bold text-gray-900">
                            <td class="px-6 py-4 text-sm">TOTALES</td>
                            <td class="px-6 py-4 text-right text-sm text-blue-700">₡{{ number_format($resumen['prometido'], 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right text-sm text-green-700">₡{{ number_format($resumen['dado'], 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right text-sm text-red-700">₡{{ number_format($resumen['faltante'], 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right text-sm text-purple-700">₡{{ number_format($resumen['profit'], 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-center text-sm">{{ $pctTotal }}%</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bar Chart horizontal con % cumplimiento -->
        @if(count($datosPorIglesia) > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">% Cumplimiento por Iglesia</h3>
            <canvas id="barChart" height="300"></canvas>
        </div>
        @endif
    @endif

    <!-- Nota informativa -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    <span class="font-bold">Nota:</span> El calculo de montos prometidos considera la frecuencia de cada promesa (semanal = domingos x monto, quincenal = 2 x monto, mensual = monto). El profit representa el monto extra dado por encima de lo prometido.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if(!$tenantId && count($datosPorIglesia) > 0)
    // Bar Chart horizontal con % cumplimiento
    const iglesiaData = @json($datosPorIglesia);
    new Chart(document.getElementById('barChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: iglesiaData.map(d => d.tenant.siglas),
            datasets: [{
                label: '% Cumplimiento',
                data: iglesiaData.map(d => d.porcentaje),
                backgroundColor: iglesiaData.map(d => {
                    if (d.porcentaje >= 100) return '#22c55e';
                    if (d.porcentaje >= 75) return '#eab308';
                    return '#ef4444';
                }),
                borderRadius: 6,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.raw + '% cumplimiento';
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: Math.max(100, ...iglesiaData.map(d => d.porcentaje)) + 10,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
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

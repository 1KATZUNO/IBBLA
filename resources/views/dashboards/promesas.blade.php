@extends('layouts.admin')

@section('title', 'IBBSC - Dashboard Promesas')
@section('page-title', 'Dashboard de Promesas')

@section('content')
<div class="space-y-6">

    {{-- Filtro de año --}}
    <div class="bg-white rounded-lg shadow p-4">
        <form method="GET" class="flex items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Año</label>
                <select name="año" onchange="this.form.submit()"
                        class="rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                    @foreach($añosDisponibles as $a)
                        <option value="{{ $a }}" {{ $a == $año ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                    @if(!$añosDisponibles->contains($año))
                        <option value="{{ $año }}" selected>{{ $año }}</option>
                    @endif
                </select>
            </div>
            <a href="{{ route('ingresos-asistencia.excel-promesas', ['año' => $año]) }}"
               class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 font-semibold">
                Descargar Excel
            </a>
        </form>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-purple-50 border-l-4 border-purple-500 rounded-lg p-4">
            <p class="text-xs text-purple-700 uppercase font-medium">Prometido {{ $año }}</p>
            <p class="text-2xl font-bold text-purple-900 mt-1">₡{{ number_format($prometido, 2, '.', ',') }}</p>
        </div>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 rounded-lg p-4">
            <p class="text-xs text-emerald-700 uppercase font-medium">Dado {{ $año }}</p>
            <p class="text-2xl font-bold text-emerald-900 mt-1">₡{{ number_format($dado, 2, '.', ',') }}</p>
        </div>
        <div class="bg-{{ $saldo >= 0 ? 'blue' : 'red' }}-50 border-l-4 border-{{ $saldo >= 0 ? 'blue' : 'red' }}-500 rounded-lg p-4">
            <p class="text-xs text-{{ $saldo >= 0 ? 'blue' : 'red' }}-700 uppercase font-medium">Saldo</p>
            <p class="text-2xl font-bold text-{{ $saldo >= 0 ? 'blue' : 'red' }}-900 mt-1">
                {{ $saldo >= 0 ? '+' : '' }}₡{{ number_format($saldo, 2, '.', ',') }}
            </p>
        </div>
        <div class="bg-amber-50 border-l-4 border-amber-500 rounded-lg p-4">
            <p class="text-xs text-amber-700 uppercase font-medium">Cumplimiento</p>
            <p class="text-2xl font-bold text-amber-900 mt-1">{{ $cumplimiento }}%</p>
        </div>
    </div>

    {{-- Gráfico mensual --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Prometido vs Dado por mes — {{ $año }}</h3>
        <canvas id="chartMensual" height="80"></canvas>
    </div>

    {{-- Top contribuyentes --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Top 10 contribuyentes — {{ $año }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Persona</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 uppercase">Prometido</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 uppercase">Dado</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 uppercase">Saldo</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 uppercase">%</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($topContribuyentes as $p)
                    <tr>
                        <td class="px-4 py-2 text-sm font-medium">{{ $p['nombre'] }}</td>
                        <td class="px-4 py-2 text-sm text-right">₡{{ number_format($p['prometido'], 2, '.', ',') }}</td>
                        <td class="px-4 py-2 text-sm text-right text-emerald-700 font-semibold">₡{{ number_format($p['dado'], 2, '.', ',') }}</td>
                        <td class="px-4 py-2 text-sm text-right {{ $p['saldo'] >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                            {{ $p['saldo'] >= 0 ? '+' : '' }}₡{{ number_format($p['saldo'], 2, '.', ',') }}
                        </td>
                        <td class="px-4 py-2 text-sm text-right font-semibold">{{ $p['pct'] }}%</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const serie = @json($serie);
new Chart(document.getElementById('chartMensual'), {
    type: 'bar',
    data: {
        labels: serie.map(r => r.mes),
        datasets: [
            {
                label: 'Prometido',
                data: serie.map(r => r.prometido),
                backgroundColor: 'rgba(168, 85, 247, 0.7)',
                borderColor: 'rgb(126, 34, 206)',
                borderWidth: 1,
            },
            {
                label: 'Dado',
                data: serie.map(r => r.dado),
                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                borderColor: 'rgb(5, 150, 105)',
                borderWidth: 1,
            },
        ],
    },
    options: {
        responsive: true,
        plugins: {
            tooltip: {
                callbacks: {
                    label: (ctx) => ctx.dataset.label + ': ₡' + ctx.parsed.y.toLocaleString('es-CR'),
                },
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: (v) => '₡' + v.toLocaleString('es-CR') },
            },
        },
    },
});
</script>
@endsection

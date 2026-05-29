@extends('layouts.admin')

@section('title', tenant_title('Dashboard Asistencia'))
@section('page-title', 'Dashboard de Asistencia')

@section('content')
<div class="space-y-6">

    {{-- Filtro de año --}}
    <div class="bg-white rounded-lg shadow p-4">
        <form method="GET" class="flex items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Año</label>
                <select name="año" onchange="this.form.submit()"
                        class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($añosDisponibles as $a)
                        <option value="{{ $a }}" {{ $a == $año ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                    @if(!$añosDisponibles->contains($año))
                        <option value="{{ $año }}" selected>{{ $año }}</option>
                    @endif
                </select>
            </div>
            <a href="{{ route('ingresos-asistencia.excel-asistencia', ['fecha_inicio' => $año.'-01-01', 'fecha_fin' => $año.'-12-31']) }}"
               class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 font-semibold">
                Descargar Excel
            </a>
        </form>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-4">
            <p class="text-xs text-blue-700 uppercase font-medium">Total {{ $año }}</p>
            <p class="text-2xl font-bold text-blue-900 mt-1">{{ number_format($totalAño) }}</p>
        </div>
        <div class="bg-indigo-50 border-l-4 border-indigo-500 rounded-lg p-4">
            <p class="text-xs text-indigo-700 uppercase font-medium">Promedio / culto</p>
            <p class="text-2xl font-bold text-indigo-900 mt-1">{{ $promedio }}</p>
        </div>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 rounded-lg p-4">
            <p class="text-xs text-emerald-700 uppercase font-medium">Capilla</p>
            <p class="text-2xl font-bold text-emerald-900 mt-1">{{ number_format($capilla) }}</p>
        </div>
        <div class="bg-purple-50 border-l-4 border-purple-500 rounded-lg p-4">
            <p class="text-xs text-purple-700 uppercase font-medium">Niños</p>
            <p class="text-2xl font-bold text-purple-900 mt-1">{{ number_format($ninos) }}</p>
        </div>
        <div class="bg-amber-50 border-l-4 border-amber-500 rounded-lg p-4">
            <p class="text-xs text-amber-700 uppercase font-medium">Transmisión</p>
            <p class="text-2xl font-bold text-amber-900 mt-1">{{ number_format($transmision) }}</p>
        </div>
        <div class="bg-pink-50 border-l-4 border-pink-500 rounded-lg p-4">
            <p class="text-xs text-pink-700 uppercase font-medium">Visitas</p>
            <p class="text-2xl font-bold text-pink-900 mt-1">{{ number_format($visitas) }}</p>
        </div>
        <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4">
            <p class="text-xs text-green-700 uppercase font-medium">Salvos</p>
            <p class="text-2xl font-bold text-green-900 mt-1">{{ number_format($salvos) }}</p>
        </div>
        <div class="bg-sky-50 border-l-4 border-sky-500 rounded-lg p-4">
            <p class="text-xs text-sky-700 uppercase font-medium">Bautismos</p>
            <p class="text-2xl font-bold text-sky-900 mt-1">{{ number_format($bautismos) }}</p>
        </div>
    </div>

    {{-- Tendencia mensual --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Tendencia de asistencia mensual — {{ $año }}</h3>
        <canvas id="chartMensual" height="80"></canvas>
    </div>

    {{-- Por clase --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Asistencia por clase — {{ $año }}</h3>
        @if($porClase->isEmpty())
            <p class="text-gray-500 text-sm">No hay datos de asistencia por clase en {{ $año }}.</p>
        @else
            <canvas id="chartClases" height="100"></canvas>
            <div class="overflow-x-auto mt-6">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Clase</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($porClase as $c)
                        <tr>
                            <td class="px-4 py-2 text-sm">
                                <span class="inline-block w-3 h-3 rounded-full mr-2" style="background:{{ $c['color'] }}"></span>
                                {{ $c['nombre'] }}
                            </td>
                            <td class="px-4 py-2 text-sm text-right font-semibold">{{ number_format($c['total']) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const serie = @json($serieMensual);
new Chart(document.getElementById('chartMensual'), {
    type: 'line',
    data: {
        labels: serie.map(r => r.mes),
        datasets: [{
            label: 'Asistencia total',
            data: serie.map(r => r.total),
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.2)',
            fill: true,
            tension: 0.3,
        }],
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true } },
    },
});

const porClase = @json($porClase);
if (porClase.length > 0 && document.getElementById('chartClases')) {
    new Chart(document.getElementById('chartClases'), {
        type: 'bar',
        data: {
            labels: porClase.map(c => c.nombre),
            datasets: [{
                label: 'Asistencia total',
                data: porClase.map(c => c.total),
                backgroundColor: porClase.map(c => c.color + 'B0'),
                borderColor: porClase.map(c => c.color),
                borderWidth: 1,
            }],
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            scales: { x: { beginAtZero: true } },
        },
    });
}
</script>
@endsection

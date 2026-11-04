@extends('layouts.admin')

@section('title', tenant_title('Dashboard Asistencia'))
@section('page-title', 'Dashboard de Asistencia')

@section('content')
<div class="space-y-6">

    {{-- Filtro de período: mes + año (acceso rápido) o rango de fechas --}}
    @include('dashboards._filtro-rango', ['ruta' => 'dashboard.asistencia'])

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-brand-50 border-l-4 border-brand-500 rounded-lg p-4">
            <p class="text-xs text-brand-700 uppercase font-medium">Total del período</p>
            <p class="text-2xl font-bold text-brand-900 mt-1">{{ number_format($totalPeriodo) }}</p>
        </div>
        <div class="bg-brand-50 border-l-4 border-brand-500 rounded-lg p-4">
            <p class="text-xs text-brand-700 uppercase font-medium">Promedio / culto</p>
            <p class="text-2xl font-bold text-brand-900 mt-1">{{ $promedio }}</p>
        </div>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 rounded-lg p-4">
            <p class="text-xs text-emerald-700 uppercase font-medium">Capilla</p>
            <p class="text-2xl font-bold text-emerald-900 mt-1">{{ number_format($capilla) }}</p>
        </div>
        <div class="bg-gray-50 border-l-4 border-gray-500 rounded-lg p-4">
            <p class="text-xs text-gray-700 uppercase font-medium">Niños</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($ninos) }}</p>
        </div>
        <div class="bg-amber-50 border-l-4 border-amber-500 rounded-lg p-4">
            <p class="text-xs text-amber-700 uppercase font-medium">Transmisión</p>
            <p class="text-2xl font-bold text-amber-900 mt-1">{{ number_format($transmision) }}</p>
        </div>
        <div class="bg-gray-50 border-l-4 border-gray-500 rounded-lg p-4">
            <p class="text-xs text-gray-700 uppercase font-medium">Visitas</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($visitas) }}</p>
        </div>
        <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4">
            <p class="text-xs text-green-700 uppercase font-medium">Salvos</p>
            <p class="text-2xl font-bold text-green-900 mt-1">{{ number_format($salvos) }}</p>
        </div>
        <div class="bg-brand-50 border-l-4 border-brand-500 rounded-lg p-4">
            <p class="text-xs text-brand-700 uppercase font-medium">Bautismos</p>
            <p class="text-2xl font-bold text-brand-900 mt-1">{{ number_format($bautismos) }}</p>
        </div>
    </div>

    {{-- Tendencia (mensual, o por culto si el rango es de uno o dos meses) --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">{{ $serie['titulo'] }} — {{ $rango->etiqueta() }}</h3>
        @if($cultosEnRango === 0)
            <p class="text-gray-500 text-sm">No hay asistencias registradas en este período.</p>
        @else
            <canvas id="chartMensual" height="80"></canvas>
        @endif
    </div>

    {{-- Por clase --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Asistencia por clase — {{ $rango->etiqueta() }}</h3>
        @if($porClase->isEmpty())
            <p class="text-gray-500 text-sm">No hay datos de asistencia por clase en este período.</p>
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
const serie = @json($serie['filas']);
if (document.getElementById('chartMensual')) {
    new Chart(document.getElementById('chartMensual'), {
        type: 'line',
        data: {
            labels: serie.map(r => r.etiqueta),
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
}

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

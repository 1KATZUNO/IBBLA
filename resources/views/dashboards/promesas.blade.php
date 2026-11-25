@extends('layouts.admin')

@section('title', tenant_title('Dashboard Promesas'))
@section('page-title', 'Dashboard de Promesas')

@section('content')
<div class="space-y-6">

    {{-- Filtro de período: mes + año (acceso rápido) o rango de fechas --}}
    @include('dashboards._filtro-rango', [
        'ruta' => 'dashboard.promesas',
        'avisoMeses' => $rango->esMesesCompletos()
            ? null
            : 'Los compromisos se calculan por mes: se incluyen los meses completos ('.$rangoMeses->etiqueta().').',
    ])

    @php
        $formato = fn (float $monto, bool $signo = false) => \App\Services\Reportes\Dinero::formato($monto, 0, $signo);
        $mesEnCurso = $resumen->mesEnCurso();
    @endphp

    {{-- Cifras del motor único (QE-008): las mismas del Cumplimiento de promesas al mismo corte. --}}
    <div class="text-sm text-gray-600 bg-blue-50 border border-blue-100 rounded-lg px-4 py-3">
        <p>
            <span class="font-semibold">Prometido = meses completos{{ $resumen->hayMesesCobrables() ? ' ('.$resumen->periodo->textoMesesCobrables().')' : '' }}.</span>
            @if($mesEnCurso)
                {{ $mesEnCurso->nombre }} está en curso y todavía no se cobra: se muestra aparte y no entra en el saldo ni en el porcentaje.
            @endif
            Dado = lo que las personas con promesa dieron en lo que prometieron, hasta hoy.
            Son las mismas cifras de
            <a href="{{ route('reportes.cumplimiento') }}" class="text-blue-700 underline">Cumplimiento de promesas</a> (le correspondía y ha dado).
        </p>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 {{ $mesEnCurso ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }} gap-4">
        <div class="bg-gray-50 border-l-4 border-gray-500 rounded-lg p-4">
            <p class="text-xs text-gray-700 uppercase font-medium">Prometido (meses completos)</p>
            <p class="text-2xl font-bold text-gray-900 mt-1" data-kpi="prometido">{{ $formato($prometido) }}</p>
        </div>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 rounded-lg p-4">
            <p class="text-xs text-emerald-700 uppercase font-medium">Dado (hasta hoy)</p>
            <p class="text-2xl font-bold text-emerald-900 mt-1" data-kpi="dado">{{ $formato($dado) }}</p>
        </div>
        <div class="bg-{{ $saldo >= 0 ? 'blue' : 'red' }}-50 border-l-4 border-{{ $saldo >= 0 ? 'blue' : 'red' }}-500 rounded-lg p-4">
            <p class="text-xs text-{{ $saldo >= 0 ? 'blue' : 'red' }}-700 uppercase font-medium">Saldo</p>
            <p class="text-2xl font-bold text-{{ $saldo >= 0 ? 'blue' : 'red' }}-900 mt-1" data-kpi="saldo">{{ $formato($saldo, true) }}</p>
        </div>
        <div class="bg-amber-50 border-l-4 border-amber-500 rounded-lg p-4">
            <p class="text-xs text-amber-700 uppercase font-medium">Cumplimiento</p>
            <p class="text-2xl font-bold text-amber-900 mt-1">{{ $cumplimiento }}%</p>
        </div>
        @if($mesEnCurso)
        <div class="bg-white border border-dashed border-gray-400 rounded-lg p-4">
            <p class="text-xs text-gray-600 uppercase font-medium">{{ $resumen->rotuloEnCurso() }}</p>
            <p class="text-lg font-bold text-gray-700 mt-1" data-kpi="en-curso">{{ $formato($resumen->prometidoEnCurso()) }} prometido</p>
            <p class="text-sm text-gray-600">{{ $formato($resumen->dadoEnCurso()) }} dado en el mes</p>
        </div>
        @endif
    </div>

    {{-- Gráfico mensual --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Prometido vs Dado por mes — {{ $rangoMeses->etiqueta() }}</h3>
        <canvas id="chartMensual" height="80"></canvas>
    </div>

    {{-- Top contribuyentes --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Top 10 contribuyentes — {{ $rangoMeses->etiqueta() }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Persona</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 uppercase">Prometido (meses completos)</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 uppercase">Dado</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 uppercase">Saldo</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 uppercase">%</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($topContribuyentes as $p)
                    <tr>
                        <td class="px-4 py-2 text-sm font-medium">{{ $p['nombre'] }}</td>
                        <td class="px-4 py-2 text-sm text-right">{{ $formato($p['prometido']) }}</td>
                        <td class="px-4 py-2 text-sm text-right text-emerald-700 font-semibold">{{ $formato($p['dado']) }}</td>
                        <td class="px-4 py-2 text-sm text-right {{ $p['saldo'] >= 0 ? 'text-brand-700' : 'text-red-700' }}">{{ $formato($p['saldo'], true) }}</td>
                        <td class="px-4 py-2 text-sm text-right font-semibold">{{ $p['pct'] }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-6 text-sm text-gray-600 text-center">No hay personas con promesa en este periodo. Las promesas se registran en Personas &gt; Promesas.</td></tr>
                    @endforelse
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
                backgroundColor: serie.map(r => r.en_curso ? 'rgba(168, 85, 247, 0.25)' : 'rgba(168, 85, 247, 0.7)'),
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
                    label: (ctx) => ctx.dataset.label + ': ₡' + Math.round(ctx.parsed.y).toLocaleString('en-US') + (serie[ctx.dataIndex].en_curso && ctx.datasetIndex === 0 ? ' (en curso, todavía no se cobra)' : ''),
                },
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: (v) => '₡' + Number(v).toLocaleString('en-US') },
            },
        },
    },
});
</script>
@endsection

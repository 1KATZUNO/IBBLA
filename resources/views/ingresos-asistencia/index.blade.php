@extends('layouts.admin')

@section('title', tenant_title('Ingresos y Asistencia'))
@section('page-title', 'Ingresos y Asistencia')

@section('content')
<div class="space-y-6">
    {{-- Centro de reportes: una tarjeta por reporte, solo los que el rol puede abrir (misma regla que la ruta) --}}
    @php
        $verDinero = Auth::user()->canAccessRecuento();
        $verAsistencia = Auth::user()->canAccessAsistencia();
        $tarjetas = array_values(array_filter([
            ['ruta' => 'reportes.cumplimiento', 'visible' => $verDinero, 'icono' => 'check', 'titulo' => 'Cumplimiento de promesas', 'texto' => 'Quién está al día y quién debe, con fecha de corte.', 'formatos' => 'Pantalla, PDF y Excel'],
            ['ruta' => 'reportes.estados-cuenta', 'visible' => $verDinero, 'icono' => 'documento', 'titulo' => 'Estados de cuenta en lote', 'texto' => 'El estado de cuenta de muchas personas a la vez.', 'formatos' => 'PDF, ZIP y Excel'],
            ['ruta' => 'personas.reporte-general', 'params' => ['accion' => 'ver'], 'visible' => $verDinero, 'icono' => 'barras', 'titulo' => 'Reporte General', 'texto' => 'Lo dado contra lo esperado de todas las personas activas.', 'formatos' => 'PDF'],
            ['ruta' => 'personas.reporte-pdf', 'params' => ['accion' => 'ver'], 'visible' => $verDinero, 'icono' => 'reporte', 'titulo' => 'Reporte de contribuciones', 'texto' => 'Contribuciones de todas las personas, de enero al mes actual.', 'formatos' => 'PDF'],
            ['ruta' => 'ingresos-asistencia.ingresos', 'visible' => $verDinero, 'icono' => 'reporte', 'titulo' => 'Ingresos', 'texto' => 'Lo recibido por culto, semana o mes.', 'formatos' => 'Pantalla, PDF y Excel'],
            ['ruta' => 'ingresos-asistencia.promesas', 'visible' => $verDinero, 'icono' => 'documento', 'titulo' => 'Promesas', 'texto' => 'Promesas y compromisos por persona.', 'formatos' => 'Pantalla, PDF y Excel'],
            ['ruta' => 'ingresos-asistencia.asistencia', 'visible' => $verAsistencia, 'icono' => 'grupo', 'titulo' => 'Asistencia', 'texto' => 'Asistencia por culto y resumen por mes.', 'formatos' => 'Pantalla, PDF y Excel'],
            ['ruta' => 'ingresos-asistencia.asistencia-clases', 'visible' => $verAsistencia, 'icono' => 'calendario', 'titulo' => 'Asistencia por clase', 'texto' => 'Niños, niñas, maestros y promedio en un rango de fechas.', 'formatos' => 'Pantalla, PDF, CSV y Excel'],
        ], fn ($t) => $t['visible'] && \Illuminate\Support\Facades\Route::has($t['ruta'])));
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4" data-testid="centro-reportes">
        @foreach($tarjetas as $t)
        <a href="{{ route($t['ruta'], $t['params'] ?? []) }}" @if(isset($t['params'])) target="_blank" rel="noopener" @endif class="block h-full bg-white rounded-xl border border-gray-200 shadow-card hover:shadow-card-hover transition-shadow p-4 sm:p-6">
            <div class="flex items-start gap-3">
                <span class="shrink-0 inline-flex items-center justify-center w-11 h-11 rounded-lg bg-brand-50 text-brand-700">
                    <x-icono :nombre="$t['icono']" class="w-6 h-6" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">{{ $t['titulo'] }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $t['texto'] }}</p>
                    <p class="text-xs font-medium text-brand-700 mt-2">{{ $t['formatos'] }}</p>
                </div>
            </div>
        </a>
        @endforeach
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

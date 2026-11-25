@extends('layouts.admin')

@section('title', tenant_title('Reporte de ingresos'))
@section('page-title', 'Reporte de ingresos')

@section('content')
@php
    $formato = fn (float $monto, bool $signo = false) => \App\Services\Reportes\Dinero::formato($monto, 0, $signo);
    $porcentaje = fn (?float $p) => \App\Services\Reportes\Ingresos\ReporteIngresos::textoPorcentaje($p);
    $color = fn (float $d) => $d > 0 ? 'text-green-700' : ($d < 0 ? 'text-red-700' : 'text-gray-700');
    $actual = $reporte->actual->totales();
    $total = $reporte->total();
    $conteo = $actual['cultos'] === 1 ? '1 culto' : $actual['cultos'].' cultos';
    $tablas = ['Ingresos por categoría' => $reporte->porCategoria(), 'Ingresos por método de pago' => $reporte->porMetodo()];
@endphp
<div class="space-y-6">
    @include('reportes._filtro-cultos', [
        'ruta' => 'reportes.ingresos',
        'conteo' => $conteo,
        'exportar' => [
            ['ruta' => 'reportes.ingresos.pdf', 'params' => ['accion' => 'ver'], 'texto' => 'Ver PDF', 'clase' => 'btn-secondary', 'nueva' => true],
            ['ruta' => 'reportes.ingresos.pdf', 'params' => ['accion' => 'descargar'], 'texto' => 'Descargar PDF', 'clase' => 'btn-secondary'],
            ['ruta' => 'reportes.ingresos.excel', 'texto' => 'Descargar Excel', 'clase' => 'btn-success'],
        ],
    ])

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3" data-testid="resumen-ingresos">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Ingresos del periodo</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ $formato($actual['ingresos']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $conteo }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Periodo anterior</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ $formato($total['anterior']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ ucfirst($reporte->etiquetaAnterior()) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Variación</p>
            <p class="text-xl font-bold {{ $color($total['diferencia']) }} mt-1">{{ $porcentaje($total['porcentaje']) }}</p>
            <p class="text-xs {{ $color($total['diferencia']) }} mt-1">{{ $formato($total['diferencia'], true) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Egresos</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ $formato($actual['egresos']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Neto (ingresos − egresos)</p>
            <p class="text-xl font-bold text-green-700 mt-1">{{ $formato($actual['total']) }}</p>
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 text-sm text-blue-900">
        <p><span class="font-semibold">{{ $filtro->etiquetaPeriodo() }}. {{ $reporte->textoComparacion() }}.</span>
            Cifras en colones con la misma regla del recuento de cada culto (los dólares al tipo de cambio del sobre). El detalle culto por culto está en el <a href="{{ route('reportes.cultos', $filtro->aParametros()) }}" class="underline font-medium">Reporte de cultos</a>.</p>
    </div>

    @if($reporte->actual->vacio())
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-12 text-center">
            <p class="text-gray-700 font-medium">No hay cultos con ingresos en este rango.</p>
            <p class="text-sm text-gray-500 mt-1">Amplíe las fechas (por ejemplo con el atajo «Este año») o quite los tipos de culto marcados.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        @foreach($tablas as $titulo => $lineas)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <h2 class="px-4 py-3 border-b border-gray-200 text-base font-semibold text-gray-900">{{ $titulo }}</h2>
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Concepto</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Este periodo</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Periodo anterior</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Diferencia</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Variación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($lineas as $linea)
                        <tr>
                            <td class="px-3 py-2">{{ $linea['nombre'] }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $formato($linea['actual']) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $formato($linea['anterior']) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap {{ $color($linea['diferencia']) }}">{{ $formato($linea['diferencia'], true) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap {{ $color($linea['diferencia']) }}">{{ $porcentaje($linea['porcentaje']) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 font-semibold">
                        <tr>
                            <td class="px-3 py-3">Total de ingresos</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">{{ $formato($total['actual']) }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">{{ $formato($total['anterior']) }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap {{ $color($total['diferencia']) }}">{{ $formato($total['diferencia'], true) }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap {{ $color($total['diferencia']) }}">{{ $porcentaje($total['porcentaje']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection

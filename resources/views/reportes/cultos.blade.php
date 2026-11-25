@extends('layouts.admin')

@section('title', tenant_title('Reporte de cultos'))
@section('page-title', 'Reporte de cultos')

@section('content')
@php
    $formato = fn (float $monto) => \App\Services\Reportes\Dinero::formato($monto);
    $t = $reporte->totales();
    $conteo = $t['cultos'] === 1 ? '1 culto' : $t['cultos'].' cultos';
@endphp
<div class="space-y-6">
    @include('reportes._filtro-cultos', [
        'ruta' => 'reportes.cultos',
        'conteo' => $conteo,
        'exportar' => [
            ['ruta' => 'reportes.cultos.pdf', 'params' => ['accion' => 'ver'], 'texto' => 'Ver PDF', 'clase' => 'btn-secondary', 'nueva' => true],
            ['ruta' => 'reportes.cultos.pdf', 'params' => ['accion' => 'descargar'], 'texto' => 'Descargar PDF', 'clase' => 'btn-secondary'],
            ['ruta' => 'reportes.cultos.excel', 'texto' => 'Descargar Excel', 'clase' => 'btn-success'],
        ],
    ])

    {{-- Resumen del rango --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3" data-testid="resumen-cultos">
        @foreach([
            ['Cultos', $t['cultos'].' ('.$t['cerrados'].' cerrados)', 'text-gray-900'],
            ['Sobres', number_format($t['sobres']), 'text-gray-900'],
            ['Ingresos', $formato($t['ingresos']), 'text-gray-900'],
            ['Egresos', $formato($t['egresos']), 'text-gray-900'],
            ['Total del rango', $formato($t['total']), 'text-green-700'],
            ['Sin firmas completas', (string) $t['sin_firmas'], $t['sin_firmas'] > 0 ? 'text-red-700' : 'text-green-700'],
        ] as [$etiqueta, $valor, $color])
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">{{ $etiqueta }}</p>
            <p class="text-xl font-bold {{ $color }} mt-1">{{ $valor }}</p>
        </div>
        @endforeach
    </div>

    <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 text-sm text-blue-900">
        <p><span class="font-semibold">{{ $filtro->etiquetaPeriodo() }} · {{ implode(' · ', $filtro->descripcion($tipos)) }}.</span>
            Cifras en colones con la misma regla del recuento de cada culto (los dólares al tipo de cambio del sobre). Total = sobres en efectivo + transferencias + suelto − egresos. «Firmas completas» = firmó el pastor y al menos un tesorero.</p>
    </div>

    {{-- Una fila por culto --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Cultos ({{ $t['cultos'] }})</h2>
        </div>
        @if($reporte->vacio())
            <div class="px-6 py-12 text-center">
                <p class="text-gray-700 font-medium">No hay cultos en este rango con los filtros elegidos.</p>
                <p class="text-sm text-gray-500 mt-1">Amplíe las fechas (por ejemplo con el atajo «Este año»), quite los tipos de culto marcados o desmarque los cultos elegidos.</p>
            </div>
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-gray-200 text-sm" data-testid="tabla-cultos">
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach(['Fecha' => 'left', 'Tipo' => 'left', 'Estado' => 'center', 'Sobres' => 'right', 'Sobres en efectivo' => 'right', 'Transferencias' => 'right', 'Suelto' => 'right', 'Egresos' => 'right', 'Total' => 'right', 'Firmas completas' => 'center'] as $titulo => $alinear)
                                <th class="px-3 py-3 text-{{ $alinear }} text-xs font-medium text-gray-500 uppercase whitespace-nowrap">{{ $titulo }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($reporte->filas as $fila)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <a href="{{ route('recuento.culto', $fila->culto->id) }}" class="text-blue-700 hover:underline inline-flex items-center min-h-[44px]">{{ $fila->culto->fecha->format('d/m/Y') }}</a>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $fila->tipo }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $fila->culto->cerrado ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-800' }}">{{ $fila->estado() }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($fila->sobres) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $formato($fila->sobresEfectivo) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $formato($fila->transferencias) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $formato($fila->sueltos) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $formato($fila->egresos) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap font-semibold">{{ $formato($fila->total) }}</td>
                            <td class="px-3 py-2 text-center">
                                @if($fila->firmasCompletas)
                                    <span class="text-green-700 font-semibold">Sí</span>
                                @else
                                    <span class="text-red-700 font-semibold">No</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 font-semibold">
                        <tr>
                            <td class="px-3 py-3" colspan="3">Total del rango</td>
                            <td class="px-3 py-3 text-right">{{ number_format($t['sobres']) }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">{{ $formato($t['sobres_efectivo']) }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">{{ $formato($t['transferencias']) }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">{{ $formato($t['sueltos']) }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">{{ $formato($t['egresos']) }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap" data-testid="total-rango">{{ $formato($t['total']) }}</td>
                            <td class="px-3 py-3 text-center">{{ $t['sin_firmas'] }} sin</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    @unless($reporte->vacio())
        @include('reportes._desgloses-cultos', ['reporte' => $reporte])
    @endunless
</div>
@endsection

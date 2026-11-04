@extends('layouts.admin')

@section('title', tenant_title('Estados de cuenta'))
@section('page-title', 'Estados de cuenta')

@section('content')
@php
    $formato = fn (float $monto, bool $signo = false) => \App\Services\Reportes\Dinero::formato($monto, 2, $signo);
    $filtro = $lote->filtro;
    $periodo = $lote->periodo;
    $seleccionadas = $filtro->grupo === \App\Services\Reportes\FiltroPersonas::SELECCION ? $filtro->personaIds : [];
    $cantidad = $lote->cantidad();
    $zipPermitido = $cantidad > 0 && $cantidad <= $maxZip;
    $nombresMeses = array_slice(\App\Services\Reportes\PeriodoReporte::NOMBRES_MESES, 1, null, true);
    $anios = range(now()->year, now()->year - 4);
    $esMeses = $entrada->modo === \App\Support\Reportes\EntradaPeriodo::MESES;
    $esFechas = $entrada->modo === \App\Support\Reportes\EntradaPeriodo::FECHAS;
@endphp
<div class="space-y-6">

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg" role="alert">{{ session('error') }}</div>
    @endif

    @if($errores->isNotEmpty())
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg" role="alert">
        <p class="font-semibold">Hay filtros que no se pudieron aplicar; se muestran los valores por defecto:</p>
        <ul class="list-disc list-inside text-sm mt-1">
            @foreach($errores->all() as $mensaje)
                <li>{{ $mensaje }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Filtros y exportar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
        <form method="GET" action="{{ route('reportes.estados-cuenta') }}"
              x-data="Object.assign(selectorPersonas(@js($personas->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre, 'activo' => (bool) $p->activo])->values()), @js($seleccionadas), @js($filtro->grupo)), { periodo: @js($entrada->modo) })">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="periodo" class="block text-sm font-medium text-gray-700 mb-1">Periodo</label>
                    <select name="periodo" id="periodo" x-model="periodo"
                            class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="meses">Por meses</option>
                        <option value="fechas">Fechas exactas</option>
                        <option value="corte">A una fecha de corte</option>
                    </select>
                </div>

                {{-- Por meses: anio y de que mes a que mes --}}
                <div x-show="periodo === 'meses'" x-cloak class="grid grid-cols-3 gap-2 sm:col-span-2">
                    <div>
                        <label for="anio" class="block text-sm font-medium text-gray-700 mb-1">Año</label>
                        <select name="anio" id="anio" :disabled="periodo !== 'meses'"
                                class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach($anios as $anio)
                                <option value="{{ $anio }}" @selected($esMeses && $periodo->desde->year === $anio)>{{ $anio }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="mes_desde" class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                        <select name="mes_desde" id="mes_desde" :disabled="periodo !== 'meses'"
                                class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach($nombresMeses as $numero => $nombre)
                                <option value="{{ $numero }}" @selected($esMeses ? $periodo->desde->month === $numero : $numero === 1)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="mes_hasta" class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                        <select name="mes_hasta" id="mes_hasta" :disabled="periodo !== 'meses'"
                                class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach($nombresMeses as $numero => $nombre)
                                <option value="{{ $numero }}" @selected($esMeses ? $periodo->hasta->month === $numero : $numero === now()->month)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Fechas exactas --}}
                <div x-show="periodo === 'fechas'" x-cloak class="grid grid-cols-2 gap-2 sm:col-span-2">
                    <div>
                        <label for="desde" class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                        <input type="date" name="desde" id="desde" :disabled="periodo !== 'fechas'"
                               value="{{ $esFechas ? $periodo->desde->toDateString() : now()->startOfYear()->toDateString() }}"
                               class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="hasta" class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                        <input type="date" name="hasta" id="hasta" :disabled="periodo !== 'fechas'"
                               value="{{ $esFechas ? $periodo->hasta->toDateString() : now()->toDateString() }}"
                               class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                {{-- A una fecha de corte --}}
                <div x-show="periodo === 'corte'" x-cloak>
                    <label for="corte" class="block text-sm font-medium text-gray-700 mb-1">Fecha de corte</label>
                    <input type="date" name="corte" id="corte" :disabled="periodo !== 'corte'" max="{{ now()->toDateString() }}"
                           value="{{ $periodo->corte?->toDateString() ?? now()->toDateString() }}"
                           class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                @include('reportes._filtro-personas')
            </div>

            <div class="flex flex-wrap items-center gap-3 mt-4">
                <button type="submit" class="btn-primary">Consultar</button>
                <a href="{{ route('reportes.estados-cuenta') }}" class="btn-secondary" title="Enero al mes actual, todas las personas activas">Limpiar</a>
                <div class="flex flex-wrap gap-2 ml-auto">
                    <a href="{{ route('reportes.estados-cuenta.pdf', $parametros + ['accion' => 'ver']) }}" target="_blank" rel="noopener" class="btn-secondary">Ver PDF</a>
                    <a href="{{ route('reportes.estados-cuenta.pdf', $parametros + ['accion' => 'descargar']) }}" class="btn-secondary">Descargar PDF</a>
                    @if($zipPermitido)
                        <a href="{{ route('reportes.estados-cuenta.zip', $parametros) }}" class="btn-secondary" title="Un PDF por persona, en un archivo ZIP">ZIP (un PDF por persona)</a>
                    @else
                        <span class="btn-secondary opacity-60 cursor-not-allowed" title="El ZIP admite hasta {{ $maxZip }} personas; use el PDF en un solo archivo o elija menos personas.">ZIP (un PDF por persona)</span>
                    @endif
                    <a href="{{ route('reportes.estados-cuenta.excel', $parametros) }}" class="btn-success">Descargar Excel</a>
                </div>
            </div>
        </form>
    </div>

    <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 text-sm text-blue-900">
        <p>
            <span class="font-semibold">{{ $periodo->etiqueta() }} · {{ $filtro->descripcion() }} · {{ $cantidad }} {{ $cantidad === 1 ? 'persona' : 'personas' }}.</span>
            El PDF trae una portada, un índice y el estado de cuenta de cada persona en páginas aparte (el mismo contenido del estado de cuenta individual).
            El Excel trae un resumen por persona, sus compromisos y el detalle de cada sobre.
            {{ $lote->explicacionEsperado() }}
        </p>
        @if($lote->recortado())
            <p class="mt-1 font-semibold">El filtro tiene {{ $lote->totalPersonas }} personas; cada documento lleva como máximo {{ $cantidad }}.</p>
        @endif
    </div>

    {{-- Vista previa: quienes entran y con que cifras --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        @if($cantidad === 0)
            <div class="px-6 py-12 text-center">
                <p class="text-gray-700 font-medium">No hay personas que cumplan el filtro.</p>
                <p class="text-sm text-gray-500 mt-1">Pruebe con «Todas las activas» o elija personas.</p>
            </div>
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Total contribuido</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Le corresponde</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Ha dado en compromisos</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Diferencia</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($lote->estados as $datos)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
                            <td class="px-3 py-2 font-medium text-gray-900 whitespace-nowrap">{{ $datos['persona']->nombre }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $formato($datos['total_dado']) }}</td>
                            @if($datos['tiene_compromisos'])
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $formato($datos['total_esperado']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $formato($datos['dado_en_compromisos']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap font-medium">{{ $formato($datos['diferencia'], true) }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if($datos['al_dia'])
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">AL DÍA</span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">PENDIENTE</span>
                                    @endif
                                </td>
                            @else
                                <td class="px-3 py-2 text-right text-gray-400">—</td>
                                <td class="px-3 py-2 text-right text-gray-400">—</td>
                                <td class="px-3 py-2 text-right text-gray-400">—</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 whitespace-nowrap">SIN PROMESA</span>
                                </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@include('reportes._selector-personas-js')

@extends('layouts.admin')

@section('title', tenant_title('Cumplimiento de promesas'))
@section('page-title', 'Cumplimiento de promesas')

@section('content')
@php
    $formato = fn (float $monto, bool $signo = false) => \App\Services\Reportes\Dinero::formato($monto, 0, $signo);
    $filtro = $reporte->filtro;
    $conPromesa = $reporte->conPromesa();
    [$totalCorrespondia, $totalDado, $totalDiferencia] = \App\Services\Reportes\ReporteCumplimiento::totales($conPromesa);
    $abreviatura = $reporte->periodo->abreviaturaMesesCobrables();
    $puedeVerPersonas = auth()->user()->canAccessAdmin();
    $insignias = [
        \App\Services\Reportes\EstadoCuenta::PENDIENTE => 'bg-red-100 text-red-800',
        \App\Services\Reportes\EstadoCuenta::AL_DIA => 'bg-green-100 text-green-800',
        \App\Services\Reportes\EstadoCuenta::SIN_PROMESA => 'bg-gray-100 text-gray-700',
    ];
    $grupos = [
        'todos' => ['Todos', count($reporte->filas)],
        'pendiente' => ['Pendientes', $resumen['pendientes']],
        'al-dia' => ['Al día', $resumen['al_dia']],
        'sin-promesa' => ['Sin promesa', $resumen['sin_promesa']],
    ];
    $claveGrupo = fn ($fila) => match ($fila->estado) {
        \App\Services\Reportes\EstadoCuenta::PENDIENTE => 'pendiente',
        \App\Services\Reportes\EstadoCuenta::AL_DIA => 'al-dia',
        default => 'sin-promesa',
    };
    $seleccionadas = $filtro->grupo === \App\Services\Reportes\FiltroPersonas::SELECCION ? $filtro->personaIds : [];
@endphp
<div class="space-y-6">

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
        <form method="GET" action="{{ route('reportes.cumplimiento') }}"
              x-data="selectorPersonas(@js($personas->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre, 'activo' => (bool) $p->activo])->values()), @js($seleccionadas), @js($filtro->grupo))">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="corte" class="block text-sm font-medium text-gray-700 mb-1">Fecha de corte</label>
                    <input type="date" name="corte" id="corte" required max="{{ now()->toDateString() }}"
                           value="{{ $reporte->periodo->corte?->toDateString() }}"
                           class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                @include('reportes._filtro-personas')
            </div>

            <div class="flex flex-wrap items-center gap-3 mt-4">
                <button type="submit" class="btn-primary">Consultar</button>
                <a href="{{ route('reportes.cumplimiento') }}" class="btn-secondary" title="Corte a hoy, todas las personas activas">Limpiar</a>
                <div class="flex flex-wrap gap-2 ml-auto">
                    <a href="{{ route('reportes.cumplimiento.pdf', $parametros + ['accion' => 'ver']) }}" target="_blank" rel="noopener" class="btn-secondary">Ver PDF</a>
                    <a href="{{ route('reportes.cumplimiento.pdf', $parametros + ['accion' => 'descargar']) }}" class="btn-secondary">Descargar PDF</a>
                    <a href="{{ route('reportes.cumplimiento.excel', $parametros) }}" class="btn-success">Descargar Excel</a>
                </div>
            </div>
        </form>
    </div>

    {{-- Resumen --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Con promesa</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $resumen['con_promesa'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Al día</p>
            <p class="text-2xl font-bold text-green-700 mt-1">{{ $resumen['al_dia'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Pendientes</p>
            <p class="text-2xl font-bold text-red-700 mt-1">{{ $resumen['pendientes'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Le correspondía ({{ $abreviatura }})</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ $formato($resumen['le_correspondia']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Ha dado (al corte)</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ $formato($resumen['ha_dado']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Faltante de pendientes</p>
            <p class="text-xl font-bold text-red-700 mt-1">{{ $formato($resumen['faltante']) }}</p>
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 text-sm text-blue-900">
        <p><span class="font-semibold">Corte al {{ $reporte->corteTexto() }} · {{ $filtro->descripcion() }}.</span> {{ $reporte->explicacion() }}</p>
    </div>

    {{-- Tabla --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" x-data="{ ver: 'todos', nombre: '' }">
        <div class="px-4 py-3 border-b border-gray-200 flex flex-wrap items-center gap-2">
            <div class="flex flex-wrap gap-1" role="tablist" aria-label="Filtrar por estado">
                @foreach($grupos as $clave => [$etiqueta, $cuantos])
                    <button type="button" role="tab" @click="ver = '{{ $clave }}'" :aria-selected="ver === '{{ $clave }}'"
                            :class="ver === '{{ $clave }}' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="min-h-[44px] px-4 rounded-lg text-sm font-semibold">
                        {{ $etiqueta }} <span class="font-normal">({{ $cuantos }})</span>
                    </button>
                @endforeach
            </div>
            <input type="search" x-model="nombre" placeholder="Buscar por nombre" aria-label="Buscar por nombre"
                   class="ml-auto w-full sm:max-w-xs min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>

        @if($reporte->filas === [])
            <div class="px-6 py-12 text-center">
                <p class="text-gray-700 font-medium">No hay personas con promesa ni aportes para este filtro.</p>
                <p class="text-sm text-gray-500 mt-1">Pruebe con «Todas las personas activas» o con otra fecha de corte.</p>
            </div>
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Le correspondía</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Ha dado</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Diferencia</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase whitespace-nowrap">{{ \App\Services\Reportes\FilaCumplimiento::TITULO_ULTIMO_APORTE }}</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observación</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($reporte->filas as $fila)
                        <tr class="hover:bg-gray-50"
                            x-show="(ver === 'todos' || ver === '{{ $claveGrupo($fila) }}') && (nombre === '' || @js(mb_strtolower(\Illuminate\Support\Str::ascii($fila->nombre))).includes(nombre.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')))">
                            <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
                            <td class="px-3 py-2">
                                @if($puedeVerPersonas)
                                    <a href="{{ route('compromisos.show', $fila->personaId) }}" class="font-medium text-gray-900 hover:text-blue-700 hover:underline whitespace-nowrap">{{ $fila->nombre }}</a>
                                @else
                                    <span class="font-medium text-gray-900 whitespace-nowrap">{{ $fila->nombre }}</span>
                                @endif
                                @if($fila->promesa !== '')
                                    <p class="text-xs text-gray-500 truncate max-w-xs" title="{{ $fila->promesa }}">{{ $fila->promesa }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $fila->tienePromesa() ? $formato($fila->leCorresponde()) : '—' }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $formato($fila->haDado()) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap font-medium">{{ $fila->tienePromesa() ? $formato($fila->diferencia(), true) : '—' }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap {{ $insignias[$fila->estado] }}">{{ $fila->estado }}</span>
                            </td>
                            <td class="px-3 py-2 text-center whitespace-nowrap text-gray-600">{{ $fila->ultimoAporteTexto() }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $fila->observacion }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    @if($conPromesa !== [])
                    <tfoot class="bg-gray-50 font-semibold" x-show="ver === 'todos' && nombre === ''">
                        <tr>
                            <td class="px-3 py-3"></td>
                            <td class="px-3 py-3">Total con promesa ({{ count($conPromesa) }})</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">{{ $formato($totalCorrespondia) }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">{{ $formato($totalDado) }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">{{ $formato($totalDiferencia, true) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        @endif
    </div>

    @if($reporte->sinMovimiento > 0)
        <p class="text-xs text-gray-500">No se listan {{ $reporte->sinMovimiento }} {{ $reporte->sinMovimiento === 1 ? 'persona' : 'personas' }} sin promesa y sin aportes en el periodo.</p>
    @endif
</div>
@endsection

@include('reportes._selector-personas-js')

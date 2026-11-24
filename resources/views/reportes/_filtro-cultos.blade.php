{{--
    Filtros de los reportes de cultos e ingresos (HU-R10, HU-R11): rango de fechas con atajos,
    tipos de culto y, en el de cultos, "Elegir cultos" dentro del rango. GET: la URL se puede copiar.

    Variables: $ruta, $filtro (FiltroCultos), $tipos, $entrada, $atajos, $cultosDelRango (opcional),
               $exportar (lista de ['ruta', 'texto', 'clase', 'params'?, 'nueva'?]), $conteo (texto del conteo)
--}}
@php
    $valorInicio = $entrada['fecha_inicio'] ?? $filtro->rango->inicio->toDateString();
    $valorFin = $entrada['fecha_fin'] ?? $filtro->rango->fin->toDateString();
    $parametros = $filtro->aParametros();
    $cultosDelRango = $cultosDelRango ?? null;
@endphp

@if($filtro->errores->isNotEmpty())
<div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-lg" role="alert">
    <p class="font-semibold">Revise el filtro:</p>
    <ul class="list-disc list-inside text-sm mt-1">
        @foreach($filtro->errores->all() as $mensaje)
            <li>{{ $mensaje }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
    <form method="GET" action="{{ route($ruta) }}" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="fecha_inicio" class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $valorInicio }}" required
                       class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="fecha_fin" class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                <input type="date" name="fecha_fin" id="fecha_fin" value="{{ $valorFin }}" required
                       class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <fieldset class="sm:col-span-2">
                <legend class="block text-sm font-medium text-gray-700 mb-1">Tipo de culto <span class="font-normal text-gray-500">(ninguno marcado = todos)</span></legend>
                <div class="flex flex-wrap gap-2">
                    @foreach($tipos as $tipo)
                        <label class="inline-flex items-center gap-2 min-h-[44px] px-3 rounded-lg border border-gray-300 bg-white text-sm cursor-pointer has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                            <input type="checkbox" name="tipos[]" value="{{ $tipo->slug }}" class="rounded border-gray-300 text-blue-600"
                                   @checked(in_array($tipo->slug, $filtro->tipos, true))>
                            {{ $tipo->nombre }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="text-gray-500">Atajos:</span>
            @foreach($atajos as $etiqueta => $fechas)
                <a href="{{ route($ruta, $fechas + array_filter(['tipos' => $filtro->tipos])) }}" class="inline-flex items-center min-h-[44px] px-3 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">{{ $etiqueta }}</a>
            @endforeach
        </div>

        @if($cultosDelRango !== null)
        <details class="rounded-lg border border-gray-200" @if($filtro->cultos !== []) open @endif>
            <summary class="cursor-pointer min-h-[44px] flex items-center px-4 text-sm font-medium text-gray-700">
                Elegir cultos ({{ $filtro->cultos === [] ? 'todos los del rango' : count($filtro->cultos).' elegidos' }})
            </summary>
            <div class="px-4 pb-4">
                @if($cultosDelRango->isEmpty())
                    <p class="text-sm text-gray-500">No hay cultos en este rango. Cambie las fechas y pulse «Consultar».</p>
                @else
                    <p class="text-sm text-gray-500 mb-2">Marque solo los cultos que quiere en el reporte; sin ninguno marcado salen todos los del rango.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-72 overflow-y-auto">
                        @foreach($cultosDelRango as $c)
                            <label class="flex items-center gap-2 min-h-[44px] px-3 rounded-lg border border-gray-200 text-sm cursor-pointer has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                                <input type="checkbox" name="cultos[]" value="{{ $c->id }}" class="rounded border-gray-300 text-blue-600" @checked(in_array($c->id, $filtro->cultos, true))>
                                <span>{{ $c->fecha->format('d/m/Y') }} · {{ $c->tipo_nombre }}</span>
                                <span class="ml-auto text-xs {{ $c->cerrado ? 'text-gray-500' : 'text-amber-700' }}">{{ $c->cerrado ? 'Cerrado' : 'Abierto' }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
        </details>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="btn-primary">Consultar</button>
            <a href="{{ route($ruta) }}" class="btn-secondary" title="Mes actual, todos los tipos">Limpiar</a>
            <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
                <span class="text-sm text-gray-600">Exportar {{ $conteo }}:</span>
                @foreach($exportar as $boton)
                    <a href="{{ route($boton['ruta'], $parametros + ($boton['params'] ?? [])) }}" class="{{ $boton['clase'] }}"
                       @if(! empty($boton['nueva'])) target="_blank" rel="noopener" @endif>{{ $boton['texto'] }}</a>
                @endforeach
            </div>
        </div>
    </form>
</div>

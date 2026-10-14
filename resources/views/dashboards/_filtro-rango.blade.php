{{--
    Filtro de período de los dashboards: acceso rápido mes + año, o un rango de fechas libre.
    Cada formulario es un GET independiente (solo envía sus propios campos), así que la URL
    resultante se puede copiar y compartir tal cual.

    Variables:
      $rango             App\Support\RangoFechas ya resuelto
      $ruta              nombre de la ruta del dashboard
      $añosDisponibles   años del selector
      $entrada           ['fecha_inicio' => ?string, 'fecha_fin' => ?string] lo escrito en la URL
      $excelUrl, $excelNota   (opcionales) enlace de descarga o aviso de por qué no hay
      $avisoMeses        (opcional) texto sobre lo que se calcula por meses completos
--}}
@php
    $nombresMeses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $mesSeleccionado = $rango->mesSeleccionado();
    $esPersonalizado = $rango->modo === \App\Support\RangoFechas::MODO_PERSONALIZADO;
    $valorInicio = $entrada['fecha_inicio'] ?? null;
    $valorFin = $entrada['fecha_fin'] ?? null;
    // Sin fechas escritas, el formulario de rango muestra el período que se está viendo.
    if (! $valorInicio && ! $valorFin) {
        $valorInicio = $rango->inicio->toDateString();
        $valorFin = $rango->fin->toDateString();
    }
@endphp

<div class="card space-y-4 p-4 sm:p-6">
    {{-- Los tres dashboards, conservando el período que se está viendo --}}
    <div class="flex flex-wrap items-center gap-3 text-sm">
        <span class="text-gray-500">Dashboard:</span>
        <nav class="tabs" aria-label="Dashboards">
            @foreach(['dashboard' => 'General', 'dashboard.asistencia' => 'Asistencia', 'dashboard.promesas' => 'Promesas'] as $rutaPestana => $nombrePestana)
                <a href="{{ route($rutaPestana, $rango->parametros()) }}"
                   class="tab {{ $rutaPestana === $ruta ? 'tab-active' : '' }}"
                   @if($rutaPestana === $ruta) aria-current="page" @endif>{{ $nombrePestana }}</a>
            @endforeach
        </nav>
    </div>

    @if($rango->errores->isNotEmpty())
    <div class="alert-error" role="alert">
        <p class="font-semibold">No se pudo aplicar el filtro; se muestra el período por defecto.</p>
        @foreach($rango->errores->all() as $mensaje)
            <p class="text-sm">{{ $mensaje }}</p>
        @endforeach
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Acceso rápido: mes y año --}}
        <form method="GET" action="{{ route($ruta) }}" class="space-y-3 {{ $esPersonalizado ? '' : 'border-l-4 border-brand-500 pl-4' }}">
            <p class="text-sm font-semibold text-gray-700">Mes y año</p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[150px]">
                    <label for="mes" class="block text-sm text-gray-600 mb-1">Mes</label>
                    <select name="mes" id="mes" class="input-primary">
                        <option value="" {{ $mesSeleccionado === '' ? 'selected' : '' }}>Todo el año</option>
                        @foreach($nombresMeses as $indice => $nombreMes)
                            <option value="{{ $indice + 1 }}" {{ $mesSeleccionado === $indice + 1 ? 'selected' : '' }}>{{ $nombreMes }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[150px]">
                    <label for="año" class="block text-sm text-gray-600 mb-1">Año</label>
                    <select name="año" id="año" class="input-primary">
                        @foreach($añosDisponibles as $anio)
                            <option value="{{ $anio }}" {{ $anio === $rango->anioSeleccionado() ? 'selected' : '' }}>{{ $anio }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary">Ver</button>
            </div>
        </form>

        {{-- Rango de fechas libre --}}
        <form method="GET" action="{{ route($ruta) }}" class="space-y-3 {{ $esPersonalizado ? 'border-l-4 border-brand-500 pl-4' : '' }}">
            <p class="text-sm font-semibold text-gray-700">Rango de fechas</p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[150px]">
                    <label for="fecha_inicio" class="block text-sm text-gray-600 mb-1">Desde</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $valorInicio }}" required class="input-primary">
                </div>
                <div class="flex-1 min-w-[150px]">
                    <label for="fecha_fin" class="block text-sm text-gray-600 mb-1">Hasta</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="{{ $valorFin }}" required class="input-primary">
                </div>
                <button type="submit" class="btn-primary">Aplicar</button>
            </div>
        </form>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="text-gray-500">Atajos:</span>
        @foreach(\App\Support\RangoFechas::atajos() as $nombreAtajo => $parametrosAtajo)
            <a href="{{ route($ruta, $parametrosAtajo) }}" class="chip">{{ $nombreAtajo }}</a>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
        <p class="text-sm text-gray-600">
            Mostrando <strong class="text-gray-900">{{ $rango->etiqueta() }}</strong>
            <span class="text-gray-500">· {{ $rango->dias() }} {{ $rango->dias() === 1 ? 'día' : 'días' }}</span>
            @isset($avisoMeses)
                <span class="block text-xs text-gray-500 mt-1">{{ $avisoMeses }}</span>
            @endisset
        </p>

        @if(! empty($excelUrl))
            <a href="{{ $excelUrl }}" class="btn-secondary">
                <x-icono nombre="descargar" class="h-5 w-5" />
                Descargar Excel
            </a>
        @elseif(! empty($excelNota))
            <span class="text-xs text-gray-500">{{ $excelNota }}</span>
        @endif
    </div>
</div>

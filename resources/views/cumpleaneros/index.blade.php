@php
    $t = tenant();
    $tColors = $t ? $t->colors : \App\Models\Tenant::COLOR_THEMES['blue'];
@endphp

@extends('layouts.admin')

@section('title', 'Cumpleañeros')
@section('page-title', 'Cumpleañeros')

@section('content')
<div class="space-y-6">
    <!-- Resumen del mes -->
    <div class="card flex items-center gap-4 p-5">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
            <x-icono nombre="regalo" class="h-6 w-6" />
        </span>
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Cumpleañeros de {{ $nombreMes }}</h2>
            <p class="text-sm text-gray-500">{{ $cumpleaneros->count() }} {{ $cumpleaneros->count() === 1 ? 'persona cumple' : 'personas cumplen' }} años este mes</p>
        </div>
    </div>

    <!-- Selector de mes -->
    <div class="card p-4">
        <form method="GET" action="{{ route('cumpleaneros.index') }}" class="flex flex-col sm:flex-row items-center gap-3">
            <label for="mes" class="text-sm font-medium text-gray-700 whitespace-nowrap">Seleccionar mes:</label>
            <select name="mes" id="mes" class="input-primary flex-1 sm:max-w-xs" onchange="this.form.submit()">
                @foreach($meses as $num => $nombre)
                    <option value="{{ $num }}" {{ $mesSeleccionado == $num ? 'selected' : '' }}>
                        {{ $nombre }}{{ $num == $mesHoyReal ? ' (actual)' : '' }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    @if($cumpleaneros->count() > 0)
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($cumpleaneros as $persona)
        @php
            $dia = $persona->fecha_nacimiento->day;
            $hoy = now()->day;
            $esMesActual = ($mesSeleccionado === $mesHoyReal);
            $esCumpleHoy = ($esMesActual && $dia === $hoy);
            $yaPaso = $esMesActual && $dia < $hoy;
        @endphp
        <div class="card p-5 flex items-center gap-4 transition-shadow hover:shadow-card-hover {{ $esCumpleHoy ? 'ring-2' : '' }}" style="{{ $esCumpleHoy ? 'ring-color: ' . $tColors['500'] : '' }}">
            <div class="flex-shrink-0 w-14 h-14 rounded-full flex items-center justify-center text-white font-bold text-lg {{ $esCumpleHoy ? '' : ($yaPaso ? 'bg-gray-400' : '') }}"
                 style="{{ $esCumpleHoy ? 'background-color: ' . $tColors['500'] : (!$yaPaso ? 'background-color: ' . $tColors['600'] : '') }}">
                {{ $dia }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-900 truncate">{{ $persona->nombre }}</p>
                <p class="text-sm text-gray-500">
                    {{ $dia }} de {{ $nombreMes }}
                </p>
                @if($esCumpleHoy)
                <span class="inline-block mt-1 text-xs font-semibold px-2 py-0.5 rounded-full text-white" style="background-color: {{ $tColors['500'] }};">
                    &#127881; Hoy!
                </span>
                @elseif($yaPaso)
                <span class="inline-block mt-1 text-xs text-gray-400">Ya paso</span>
                @elseif($esMesActual)
                <span class="inline-block mt-1 text-xs" style="color: {{ $tColors['600'] }};">Proximo</span>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="card">
        <x-ui.vacio icono="regalo" :titulo="'No hay cumpleañeros en '.$nombreMes" texto="Agrega la fecha de nacimiento a las personas para que aparezcan aquí." />
    </div>
    @endif
</div>
@endsection

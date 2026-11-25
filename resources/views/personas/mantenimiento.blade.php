@extends('layouts.admin')

{{--
    QA-034: confirmación de las acciones masivas de Personas > Mantenimiento (solo admin).
    Variables: $accion ('inactivas' | 'promesas'), $personas, $cantidadPersonas, $cantidadPromesas,
    $cantidadCompromisos, $cantidadUsuarios, $cantidadSobres.
--}}
@php
    $esInactivas = $accion === 'inactivas';
    $titulo = $esInactivas ? 'Eliminar personas inactivas' : 'Borrar todas las promesas';
    $nada = $esInactivas ? $cantidadPersonas === 0 : $cantidadPromesas === 0 && $cantidadCompromisos === 0;
    $plural = fn (int $n, string $uno, string $varios) => number_format($n, 0, ',', '.').' '.($n === 1 ? $uno : $varios);
@endphp

@section('title', tenant_title($titulo))
@section('page-title', $titulo)
@section('page-subtitle', 'Acción que no se puede deshacer: revise bien antes de confirmar.')

@section('content')
<div class="max-w-3xl space-y-6">
    @if($nada)
        <div class="card p-6">
            <x-ui.vacio icono="lupa"
                :titulo="$esInactivas ? 'No hay personas inactivas' : 'No hay promesas que borrar'"
                texto="No hay nada que borrar. Vuelva a Personas para seguir trabajando." />
            <div class="mt-4 flex justify-center">
                <a href="{{ route('personas.index') }}" class="btn-secondary">Volver a Personas</a>
            </div>
        </div>
    @else
        <div class="card p-6 border border-red-200">
            <h2 class="text-lg font-semibold text-red-800">Esto es lo que se va a borrar</h2>
            <ul class="mt-3 list-disc pl-5 space-y-1 text-sm text-gray-800">
                @if($esInactivas)
                    <li>{{ $plural($cantidadPersonas, 'persona inactiva', 'personas inactivas') }}, con sus datos y su ficha.</li>
                    <li>{{ $plural($cantidadPromesas, 'promesa', 'promesas') }} y {{ $plural($cantidadCompromisos, 'compromiso mensual', 'compromisos mensuales') }} de esas personas.</li>
                    @if($cantidadUsuarios > 0)
                        <li>{{ $plural($cantidadUsuarios, 'usuario', 'usuarios') }} para entrar al sistema que tenían esas personas.</li>
                    @endif
                @else
                    <li>{{ $plural($cantidadPromesas, 'promesa', 'promesas') }} de {{ $plural($cantidadPersonas, 'persona', 'personas') }}: toda la iglesia queda sin promesas.</li>
                    <li>{{ $plural($cantidadCompromisos, 'compromiso mensual', 'compromisos mensuales') }} (el historial de lo prometido y lo dado por mes).</li>
                @endif
            </ul>

            <h3 class="mt-5 text-sm font-semibold text-gray-900">Lo que NO se borra</h3>
            <p class="mt-1 text-sm text-gray-700">
                @if($esInactivas)
                    Los sobres quedan registrados con su dinero{{ $cantidadSobres > 0 ? ' ('.$plural($cantidadSobres, 'sobre', 'sobres').')' : '' }}, pero sin persona.
                @else
                    Los sobres dados quedan como historial. Las personas siguen registradas.
                @endif
            </p>

            @if($esInactivas)
                <details class="mt-4">
                    <summary class="cursor-pointer min-h-[44px] flex items-center text-sm font-medium text-brand-700">Ver las {{ $cantidadPersonas }} personas</summary>
                    <ul class="mt-2 columns-1 sm:columns-2 text-sm text-gray-700">
                        @foreach($personas as $p)
                            <li>{{ $p->nombre }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>

        <form method="POST" action="{{ $esInactivas ? route('personas.limpiar-inactivas') : route('personas.resetear-promesas') }}" class="card p-6 space-y-4">
            @csrf
            <div>
                <label for="confirmacion" class="block text-sm font-medium text-gray-900">
                    Para continuar, escriba <strong>{{ \App\Http\Controllers\PersonaController::PALABRA_CONFIRMACION }}</strong>
                </label>
                <input type="text" name="confirmacion" id="confirmacion" autocomplete="off" required
                       class="input-primary mt-1 w-full sm:w-64" value="">
                @error('confirmacion')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">Queda en la auditoría quién lo hizo, cuándo y qué se borró.</p>
            </div>
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <a href="{{ route('personas.index') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-danger">{{ $esInactivas ? 'Eliminar personas inactivas' : 'Borrar todas las promesas' }}</button>
            </div>
        </form>
    @endif
</div>
@endsection

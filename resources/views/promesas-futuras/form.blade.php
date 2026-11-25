@extends('layouts.admin')

@php $nuevo = ! $corte->exists; @endphp

@section('title', tenant_title($nuevo ? 'Nuevo corte de promesas' : 'Editar corte'))
@section('page-title', $nuevo ? 'Nuevo corte de promesas' : 'Editar corte')
@section('page-subtitle', 'Las promesas del corte entran en vigor el día 1 del mes que elijas y ese día reemplazan a todas las anteriores.')

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ $nuevo ? route('promesas-futuras.store') : route('promesas-futuras.update', $corte) }}" class="card p-4 sm:p-6 space-y-5">
        @csrf
        @unless($nuevo)
            @method('PUT')
        @endunless

        <div>
            <label for="nombre" class="field-label">Nombre del corte</label>
            <input id="nombre" name="nombre" type="text" maxlength="120" required class="input-primary w-full"
                value="{{ old('nombre', $corte->nombre ?? '') }}" placeholder="Promesas 2027">
            <p class="field-hint">Cómo lo vas a reconocer en la lista.</p>
            @error('nombre')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="mes" class="field-label">Entran en vigor el día 1 de</label>
            <input id="mes" name="mes" type="month" required class="input-primary w-full sm:w-64"
                value="{{ old('mes', $mesSugerido) }}" min="{{ now()->addMonthNoOverflow()->format('Y-m') }}">
            <p class="field-hint">Tiene que ser un mes que todavía no llegó. Solo puede haber un corte por mes.</p>
            @error('mes')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="action-bar">
            <a href="{{ $nuevo ? route('promesas-futuras.index') : route('promesas-futuras.show', $corte) }}" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary">{{ $nuevo ? 'Crear corte' : 'Guardar cambios' }}</button>
        </div>
    </form>

    @unless($nuevo)
        <div class="card p-4 sm:p-6 mt-6 border border-red-200">
            <h2 class="section-title text-red-800">Eliminar el corte</h2>
            <p class="text-sm text-gray-700 mt-1">Borra el corte con todas sus promesas programadas. Las promesas que rigen hoy no se tocan.</p>
            <a href="{{ route('promesas-futuras.eliminar', $corte) }}" class="btn-danger-soft mt-3">Eliminar corte…</a>
        </div>
    @endunless
</div>
@endsection

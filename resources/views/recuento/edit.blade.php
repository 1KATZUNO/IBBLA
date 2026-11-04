@extends('layouts.admin')

@section('title', tenant_title('Editar sobre'))
@section('page-title', 'Editar sobre #' . $sobre->numero_sobre)

@section('content')
@php
    $diaCulto = ucfirst($culto->fecha->locale('es')->isoFormat('dddd'));
@endphp
<div class="max-w-5xl mx-auto space-y-4">
    {{-- Solo el culto de este sobre --}}
    <section class="bg-white rounded-lg shadow px-4 sm:px-6 py-4" aria-label="Culto">
        <a href="{{ route('recuento.culto', $culto) }}" class="inline-flex items-center gap-1 min-h-[44px] -mt-3 text-sm font-medium text-blue-700 hover:text-blue-900 hover:underline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver al culto
        </a>
        <h2 class="text-xl font-bold text-gray-900">Sobre #{{ $sobre->numero_sobre }} · {{ $diaCulto }} {{ $culto->fecha->format('d/m/Y') }} · {{ $culto->tipo_nombre }}</h2>
        @if($culto->cerrado)
            <p class="mt-1 text-sm text-amber-800">Este culto está cerrado: estás corrigiendo el sobre y los totales se recalculan.</p>
        @endif
    </section>

    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
        <form action="{{ route('recuento.update', $sobre) }}" method="POST" id="sobreForm" class="space-y-5">
            @csrf
            @method('PUT')

            @include('recuento.partials.form-sobre', ['sobre' => $sobre])

            {{-- Barra fija: total y guardar siempre a la vista --}}
            <div class="action-bar">
                @include('recuento.partials.barra-persona', ['sobre' => $sobre])
                <div class="text-sm text-gray-600">
                    Total: <span class="text-lg font-bold text-blue-700 tabular-nums" id="totalBarra">₡0.00</span>
                    <span class="hidden ml-1 text-xs text-gray-500" id="totalConvertido"></span>
                </div>
                <a href="{{ route('recuento.culto', $culto) }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary min-w-[12rem]" id="btnPrincipalSobre" data-boton-guardar>Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
@endsection

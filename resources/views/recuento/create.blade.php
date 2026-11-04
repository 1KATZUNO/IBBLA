@extends('layouts.admin')

@section('title', tenant_title('Registrar sobres'))
@section('page-title', 'Registrar sobres')

@section('content')
@php
    $diaCulto = ucfirst($culto->fecha->locale('es')->isoFormat('dddd'));
@endphp
<div class="max-w-5xl mx-auto space-y-4">
    {{-- Solo este culto: cual es y como va --}}
    <section class="bg-white rounded-lg shadow px-4 sm:px-6 py-4 flex flex-wrap items-start justify-between gap-4" aria-label="Culto">
        <div class="min-w-0">
            <a href="{{ route('recuento.culto', $culto) }}" class="inline-flex items-center gap-1 min-h-[44px] -mt-3 text-sm font-medium text-blue-700 hover:text-blue-900 hover:underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver al culto
            </a>
            <h2 class="text-xl font-bold text-gray-900">{{ $diaCulto }} {{ $culto->fecha->format('d/m/Y') }} · {{ $culto->tipo_nombre }}</h2>
            @if($culto->cerrado)
                <p class="mt-1 text-sm text-amber-800">Este culto está cerrado: estás agregando el sobre como corrección.</p>
            @endif
        </div>
        <dl class="flex gap-8">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Sobres</dt>
                <dd class="text-lg font-semibold tabular-nums text-gray-900">{{ $resumen->cantidadSobres }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Total del culto</dt>
                <dd class="text-lg font-semibold tabular-nums text-blue-700">₡{{ number_format($resumen->total(), 2) }}</dd>
            </div>
        </dl>
    </section>

    @if($ultimoSobre)
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm">
            <span class="text-gray-500">Último guardado:</span>
            <span class="font-semibold text-gray-900">#{{ $ultimoSobre->numero_sobre }}</span>
            <span class="text-gray-800">{{ $ultimoSobre->persona?->nombre ?? 'Anónimo' }}</span>
            <span class="tabular-nums text-gray-800">{{ $ultimoSobre->simbolo_moneda }}{{ number_format((float) $ultimoSobre->total_declarado, 2) }}</span>
            <a href="{{ route('recuento.edit', $ultimoSobre) }}" class="ml-auto inline-flex items-center min-h-[44px] font-medium text-blue-700 hover:underline">Editar ese sobre</a>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
        <form action="{{ route('recuento.store') }}" method="POST" id="sobreForm" class="space-y-5">
            @csrf
            <input type="hidden" name="culto_id" value="{{ $culto->id }}">
            <input type="hidden" name="envio" value="{{ $envio }}">

            @include('recuento.partials.form-sobre', ['sobre' => null])

            {{-- Barra fija: total y guardar siempre a la vista. Enter en un monto = Guardar y siguiente. --}}
            <div class="action-bar">
                @include('recuento.partials.barra-persona', ['sobre' => null])
                <div class="text-sm text-gray-600">
                    Total: <span class="text-lg font-bold text-blue-700 tabular-nums" id="totalBarra">₡0.00</span>
                    <span class="hidden ml-1 text-xs text-gray-500" id="totalConvertido"></span>
                </div>
                <button type="submit" name="siguiente" value="0" class="btn-secondary" data-boton-guardar>Guardar y terminar</button>
                <button type="submit" name="siguiente" value="1" class="btn-primary min-w-[12rem]" id="btnPrincipalSobre" data-boton-guardar>Guardar y siguiente</button>
            </div>
        </form>
    </div>
</div>
@endsection

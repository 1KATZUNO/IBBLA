@extends('layouts.admin')

@section('title', tenant_title('Tarjetas con QR'))
@section('page-title', 'Tarjetas con QR de acceso')
@section('page-subtitle', 'Un PDF para imprimir: 8 tarjetas por hoja carta, con guías de corte, ordenadas por clase y nombre.')

@section('page-actions')
    <a href="{{ route('personas.index') }}" class="btn-secondary">Volver a Personas</a>
@endsection

@section('content')
<div class="space-y-6">
    <form method="GET" action="{{ route('personas.tarjetas.pdf') }}" target="_blank" class="card">
        <div class="card-header"><h2 class="card-title">¿A quiénes incluye?</h2></div>
        <div class="card-body space-y-3">
            <label class="flex min-h-[44px] cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                <input type="radio" name="filtro" value="promesa" class="mt-1 h-4 w-4 text-brand-600" @checked($filtro === 'promesa')>
                <span>
                    <span class="font-medium text-gray-900">Personas con promesa</span>
                    <span class="badge-neutral ml-1">{{ $conteos['promesa'] }}</span>
                    <span class="block text-sm text-gray-500">Con promesa vigente hoy o programada en un corte de Promesas futuras.</span>
                </span>
            </label>
            <label class="flex min-h-[44px] cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                <input type="radio" name="filtro" value="activas" class="mt-1 h-4 w-4 text-brand-600" @checked($filtro === 'activas')>
                <span>
                    <span class="font-medium text-gray-900">Todas las personas activas</span>
                    <span class="badge-neutral ml-1">{{ $conteos['activas'] }}</span>
                </span>
            </label>
            <label class="flex min-h-[44px] cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                <input type="radio" name="filtro" value="clase" class="mt-1 h-4 w-4 text-brand-600" @checked($filtro === 'clase') @disabled($clases->isEmpty())>
                <span class="flex-1">
                    <span class="font-medium text-gray-900">Una clase</span>
                    @if ($conteoClase !== null)
                        <span class="badge-neutral ml-1">{{ $conteoClase }}</span>
                    @endif
                    @if ($clases->isEmpty())
                        <span class="block text-sm text-gray-500">No hay clases activas. Créalas en Clases.</span>
                    @else
                        <select id="clase" name="clase" aria-label="Clase" class="input-primary mt-2 w-full sm:max-w-xs" onchange="this.form.querySelector('input[value=clase]').checked = true">
                            @foreach ($clases as $clase)
                                <option value="{{ $clase->id }}" @selected($claseId === $clase->id)>{{ $clase->nombre }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-sm text-gray-500">Las personas activas de esa clase.</span>
                    @endif
                </span>
            </label>
            <p class="field-hint">A quien todavía no tiene QR se le genera al descargar. Las tarjetas que ya se entregaron siguen sirviendo.</p>
        </div>
        <div class="card-footer flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button type="submit" name="accion" value="ver" class="btn-secondary">Ver PDF</button>
            <button type="submit" name="accion" value="descargar" class="btn-primary">Descargar PDF</button>
        </div>
    </form>

    <div class="card">
        <div class="card-body text-sm text-gray-600 space-y-2">
            <p><strong class="text-gray-900">Cómo imprimirlas:</strong> hoja carta, escala al 100 % (sin «ajustar a la página»). Corta por las marcas negras del margen; la línea gris fina marca el borde de cada tarjeta.</p>
            <p><strong class="text-gray-900">Una sola tarjeta:</strong> en <a href="{{ route('personas.index') }}" class="text-brand-700 underline">Personas</a>, botón <em>Tarjeta</em> de la persona.</p>
        </div>
    </div>
</div>
@endsection

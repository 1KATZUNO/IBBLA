@extends('layouts.admin')

@php
    use App\Models\CortePromesas;
    use App\Models\PromesaProgramada;
@endphp

@section('title', tenant_title('Promesas futuras'))
@section('page-title', 'Promesas futuras')
@section('page-subtitle', 'Carga desde ahora las promesas que empiezan en una fecha futura. Hasta ese día no cambia nada; ese día reemplazan a todas las promesas anteriores.')

@section('page-actions')
    <a href="{{ route('promesas-futuras.plantilla') }}" class="btn-secondary">Descargar plantilla</a>
    <a href="{{ route('promesas-futuras.create') }}" class="btn-primary">+ Nuevo corte</a>
@endsection

@section('content')
<div class="space-y-6">
    @if($filas->isEmpty())
        <div class="card p-6">
            <x-ui.vacio icono="calendario" titulo="Todavía no hay cortes de promesas"
                texto="Crea un corte (por ejemplo «Promesas 2027», desde el 1 de enero), y después agrega a las personas a mano o importa el Excel.">
                <x-slot name="acciones"><a href="{{ route('promesas-futuras.create') }}" class="btn-primary">+ Nuevo corte</a></x-slot>
            </x-ui.vacio>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Corte</th>
                            <th>Estado</th>
                            <th class="col-num">Personas</th>
                            <th class="col-num hidden md:table-cell">Promesas</th>
                            <th class="hidden lg:table-cell">Totales por categoría</th>
                            <th class="col-actions">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($filas as $f)
                            @php $c = $f['corte']; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('promesas-futuras.show', $c) }}" class="font-medium text-brand-700 hover:underline">{{ $c->nombre }}</a>
                                    <div class="text-xs text-gray-500">Desde el {{ $c->inicia_el->format('d/m/Y') }}</div>
                                </td>
                                <td>
                                    @if($f['estado'] === CortePromesas::PROGRAMADO)
                                        <x-ui.badge tipo="warning" punto>Programado</x-ui.badge>
                                        <div class="text-xs text-gray-500 mt-1">
                                            Entra en vigor el {{ $c->inicia_el->format('d/m/Y') }}@if($c->diasParaIniciar() > 0), {{ $c->diasParaIniciar() === 1 ? 'falta 1 día' : 'faltan '.$c->diasParaIniciar().' días' }}@endif
                                        </div>
                                        @if($f['promesas'] === 0)
                                            <div class="text-xs text-red-700 mt-1">Sin promesas: no entrará en vigor.</div>
                                        @endif
                                    @elseif($f['estado'] === CortePromesas::VIGENTE)
                                        <x-ui.badge tipo="success" punto>Vigente</x-ui.badge>
                                        <div class="text-xs text-gray-500 mt-1">Desde el {{ $c->inicia_el->format('d/m/Y') }}</div>
                                    @else
                                        <x-ui.badge tipo="neutral">Anterior</x-ui.badge>
                                    @endif
                                </td>
                                <td class="col-num tabular-nums">{{ $f['personas'] }}</td>
                                <td class="col-num tabular-nums hidden md:table-cell">{{ $f['promesas'] }}</td>
                                <td class="hidden lg:table-cell text-sm">
                                    @forelse($f['totales'] as $t)
                                        <div><span class="text-gray-600">{{ $t['nombre'] }}:</span> <span class="tabular-nums">{{ PromesaProgramada::formatoMonto($t['total'], $t['moneda']) }}</span> <span class="text-xs text-gray-500">{{ $t['frecuencia'] }}</span></div>
                                    @empty
                                        <span class="text-gray-400">—</span>
                                    @endforelse
                                </td>
                                <td class="col-actions">
                                    <a href="{{ route('promesas-futuras.show', $c) }}" class="btn-row">Ver</a>
                                    @if($c->estaProgramado())
                                        <a href="{{ route('promesas-futuras.edit', $c) }}" class="btn-row">Editar</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

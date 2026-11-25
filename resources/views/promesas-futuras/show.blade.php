@extends('layouts.admin')

@php
    use App\Models\PromesaProgramada;
    $programado = $corte->estaProgramado();
    $celda = function ($lista) {
        // Suma por moneda y frecuencia (casi siempre hay una sola promesa por categoría)
        return $lista->groupBy(fn ($p) => $p->moneda.'|'.$p->frecuencia)->map(function ($g) {
            $p = $g->first();

            return PromesaProgramada::formatoMonto((float) $g->sum('monto'), $p->moneda).' '.PromesaProgramada::frecuenciaCorta($p->frecuencia);
        })->implode(' + ');
    };
@endphp

@section('title', tenant_title($corte->nombre))
@section('page-title', $corte->nombre)
@section('page-subtitle')
    @if($programado)
        Programado: entra en vigor el {{ $corte->inicia_el->format('d/m/Y') }}{{ $corte->diasParaIniciar() > 0 ? ' (faltan '.$corte->diasParaIniciar().' días)' : '' }}. Hasta ese día nada cambia.
    @else
        En vigor desde el {{ $corte->inicia_el->format('d/m/Y') }}. Solo lectura.
    @endif
@endsection

@section('page-actions')
    @if($programado)
        <a href="{{ route('promesas-futuras.personas.agregar', $corte) }}" class="btn-primary">+ Agregar persona</a>
        <a href="{{ route('promesas-futuras.importar', $corte) }}" class="btn-secondary">Importar Excel</a>
        <a href="{{ route('promesas-futuras.plantilla') }}" class="btn-secondary">Descargar plantilla</a>
        <a href="{{ route('promesas-futuras.edit', $corte) }}" class="btn-secondary">Editar corte</a>
    @endif
@endsection

@section('content')
<div class="space-y-6">
    <a href="{{ route('promesas-futuras.index') }}" class="btn-ghost">← Todos los cortes</a>

    @if($hayRevision && $programado)
        <div class="alert-info">Tienes un Excel subido esperando revisión. <a href="{{ route('promesas-futuras.importar.revisar', $corte) }}" class="font-semibold underline">Seguir revisándolo</a></div>
    @endif

    @if($programado && $totalPersonas === 0)
        <div class="alert-warning">Este corte todavía no tiene promesas: <strong>no entrará en vigor</strong> hasta que le agregues al menos una persona.</div>
    @endif

    @if($categoriasNuevas->isNotEmpty())
        <div class="alert-info">Categorías nuevas de este corte (no aparecen en los sobres hasta el {{ $corte->inicia_el->format('d/m/Y') }}): {{ $categoriasNuevas->implode(', ') }}.</div>
    @endif

    <div class="summary-grid">
        <x-ui.resumen etiqueta="Personas" :valor="$totalPersonas" />
        @foreach($totales as $t)
            <x-ui.resumen :etiqueta="$t['nombre']" :valor="PromesaProgramada::formatoMonto($t['total'], $t['moneda'])" :nota="$t['frecuencia'].' · '.$t['personas'].' '.($t['personas'] === 1 ? 'persona' : 'personas')" />
        @endforeach
    </div>

    @if($totalPersonas > 0)
        <form method="GET" class="filter-bar">
            <div class="filter-field">
                <label class="filter-label" for="q">Buscar en el corte</label>
                <input id="q" name="q" value="{{ $q }}" class="input-primary" placeholder="Nombre o PIN">
            </div>
            <div class="filter-actions">
                <button class="btn-primary">Buscar</button>
                @if($q)
                    <a href="{{ route('promesas-futuras.show', $corte) }}" class="btn-ghost">Limpiar</a>
                @endif
            </div>
        </form>
    @endif

    @if($totalPersonas === 0)
        <div class="card p-6">
            <x-ui.vacio icono="personas" titulo="Nadie en este corte todavía"
                :texto="$programado ? 'Agrega a las personas una por una o importa el Excel con todas (descarga la plantilla para ver cómo debe venir).' : 'Este corte no tiene promesas.'">
                @if($programado)
                    <x-slot name="acciones">
                        <a href="{{ route('promesas-futuras.importar', $corte) }}" class="btn-primary">Importar Excel</a>
                        <a href="{{ route('promesas-futuras.personas.agregar', $corte) }}" class="btn-secondary">+ Agregar persona</a>
                    </x-slot>
                @endif
            </x-ui.vacio>
        </div>
    @elseif($personas->isEmpty())
        <div class="card p-6">
            <x-ui.vacio icono="lupa" titulo="Nadie coincide con la búsqueda" texto="Revisa el nombre o quita el filtro.">
                <x-slot name="acciones"><a href="{{ route('promesas-futuras.show', $corte) }}" class="btn-secondary">Quitar filtro</a></x-slot>
            </x-ui.vacio>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Persona</th>
                            <th class="hidden md:table-cell">Clase</th>
                            @foreach($categorias as $cat)
                                <th class="col-num">{{ $cat->nombre }}@if(! $cat->activa) <span class="badge-info">nueva</span>@endif</th>
                            @endforeach
                            @if($programado)
                                <th class="col-actions">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($personas as $fila)
                            <tr>
                                <td class="min-w-[12rem]">
                                    {{ $fila['persona']->nombre }}
                                    @if(! $fila['persona']->activo)
                                        <span class="badge-danger">inactiva</span>
                                    @endif
                                </td>
                                <td class="hidden md:table-cell text-sm text-gray-600">{{ $fila['clase'] ?: '—' }}</td>
                                @foreach($categorias as $cat)
                                    <td class="col-num tabular-nums text-sm whitespace-nowrap">
                                        @if(isset($fila['promesas'][$cat->slug]))
                                            {{ $celda($fila['promesas'][$cat->slug]) }}
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                @if($programado)
                                    <td class="col-actions whitespace-nowrap">
                                        <a href="{{ route('promesas-futuras.personas.edit', [$corte, $fila['persona']]) }}" class="btn-row">Editar</a>
                                        <form method="POST" action="{{ route('promesas-futuras.personas.destroy', [$corte, $fila['persona']]) }}" class="inline"
                                            onsubmit="return confirm('¿Quitar a {{ addslashes($fila['persona']->nombre) }} de este corte? Sus promesas de hoy no cambian.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-row text-red-700">Quitar</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

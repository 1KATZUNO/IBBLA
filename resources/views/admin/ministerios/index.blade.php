@extends('layouts.admin')

@section('title', tenant_title('Ministerios'))
@section('page-title', 'Ministerios / Áreas de Servicio')

{{-- Los mensajes de éxito / error y el resumen de errores de validación (session success|error, $errors) los muestra el layout. --}}
@section('page-subtitle')
    Las áreas de servicio (Alabanza, Transmisión, Ujieres…); cada persona puede estar en varios.
    <span class="font-semibold text-gray-700">{{ $ministerios->count() }}</span> en total,
    <span class="font-semibold text-gray-700">{{ $ministerios->where('activo', true)->count() }}</span> activos.
@endsection

@section('page-actions')
    <a href="{{ route('admin.ministerios.create') }}" class="btn-primary">+ Nuevo ministerio</a>
@endsection

@section('content')
<div class="space-y-6">
    <div class="card overflow-hidden">
        <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-num">Orden</th>
                    <th>Nombre</th>
                    <th class="hidden lg:table-cell">Descripción</th>
                    <th class="text-center">Personas</th>
                    <th class="text-center">Estado</th>
                    <th class="col-actions">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ministerios as $m)
                <tr class="{{ $m->activo ? '' : 'bg-gray-50 text-gray-500' }}">
                    <td class="col-num">{{ $m->orden }}</td>
                    <td>
                        <span class="inline-flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full" style="background:{{ $m->color }}"></span>
                            <strong class="font-semibold text-gray-900">{{ $m->nombre }}</strong>
                        </span>
                    </td>
                    <td class="hidden text-gray-600 lg:table-cell">{{ $m->descripcion ?? '—' }}</td>
                    <td class="text-center">
                        <span class="font-semibold">{{ $m->personas_activas_count }}</span>
                        @if($m->personas_count > $m->personas_activas_count)
                            <span class="text-xs text-gray-500">(+{{ $m->personas_count - $m->personas_activas_count }} anteriores)</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <x-ui.badge :tipo="$m->activo ? 'success' : 'neutral'" punto>{{ $m->activo ? 'Activo' : 'Inactivo' }}</x-ui.badge>
                    </td>
                    <td class="col-actions">
                        <a href="{{ route('admin.ministerios.edit', $m) }}" class="btn-row text-brand-700 hover:bg-brand-50">Editar</a>

                        <form method="POST" action="{{ route('admin.ministerios.toggle', $m) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn-row text-orange-700 hover:bg-orange-50">
                                {{ $m->activo ? 'Desactivar' : 'Activar' }}
                            </button>
                        </form>

                        @if($m->personas_count > 0)
                            <span class="btn-row cursor-not-allowed text-gray-400 hover:bg-transparent"
                                  title="Tiene {{ $m->personas_count }} persona(s) asignada(s). Desactívalo en su lugar o quita las asignaciones desde Personas.">Eliminar</span>
                        @else
                            <form method="POST" action="{{ route('admin.ministerios.destroy', $m) }}" class="inline" onsubmit="return confirm({{ \Illuminate\Support\Js::from('¿Eliminar el ministerio '.$m->nombre.'? No tiene personas asignadas.') }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-row text-red-700 hover:bg-red-50">Eliminar</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="!p-0">
                        <x-ui.vacio icono="estrella" titulo="No hay ministerios registrados" texto="Crea el primero para poder asignar personas a un área de servicio.">
                            <x-slot name="acciones"><a href="{{ route('admin.ministerios.create') }}" class="btn-primary">+ Nuevo ministerio</a></x-slot>
                        </x-ui.vacio>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <p class="text-xs text-gray-500">
        Un ministerio con personas asignadas no se puede eliminar para no perder el historial de servicio: desactívalo y dejará de ofrecerse al asignar personas.
    </p>
</div>
@endsection

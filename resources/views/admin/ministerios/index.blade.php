@extends('layouts.admin')

@section('title', tenant_title('Ministerios'))
@section('page-title', 'Ministerios / Áreas de Servicio')

{{-- Los mensajes de éxito / error (session success|error) los muestra el layout. --}}
@section('content')
<div class="space-y-6">

    @if($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        @foreach($errors->all() as $mensaje)
            <p>{{ $mensaje }}</p>
        @endforeach
    </div>
    @endif

    <div class="flex flex-wrap justify-between items-center gap-3">
        <p class="text-sm text-gray-600">
            Gestiona los ministerios o áreas de servicio (Alabanza, Transmisión, Ujieres, etc.). Cada persona puede estar en varios.
            <span class="font-semibold text-gray-800">{{ $ministerios->count() }}</span> en total,
            <span class="font-semibold text-gray-800">{{ $ministerios->where('activo', true)->count() }}</span> activos.
        </p>
        <a href="{{ route('admin.ministerios.create') }}"
           class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-semibold">
            + Nuevo ministerio
        </a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Orden</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Nombre</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Descripción</th>
                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-700 uppercase">Personas</th>
                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-700 uppercase">Estado</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($ministerios as $m)
                <tr class="{{ $m->activo ? '' : 'bg-gray-50 text-gray-500' }}">
                    <td class="px-4 py-2 text-sm">{{ $m->orden }}</td>
                    <td class="px-4 py-2 text-sm">
                        <span class="inline-flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" style="background:{{ $m->color }}"></span>
                            <strong>{{ $m->nombre }}</strong>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-600">{{ $m->descripcion ?? '—' }}</td>
                    <td class="px-4 py-2 text-sm text-center">
                        <span class="font-semibold">{{ $m->personas_activas_count }}</span>
                        @if($m->personas_count > $m->personas_activas_count)
                            <span class="text-xs text-gray-500">(+{{ $m->personas_count - $m->personas_activas_count }} anteriores)</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-sm text-center">
                        @if($m->activo)
                            <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs">Activo</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 text-xs">Inactivo</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-sm text-right whitespace-nowrap">
                        <a href="{{ route('admin.ministerios.edit', $m) }}" class="text-blue-600 hover:underline mr-3">Editar</a>

                        <form method="POST" action="{{ route('admin.ministerios.toggle', $m) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-orange-600 hover:underline mr-3">
                                {{ $m->activo ? 'Desactivar' : 'Activar' }}
                            </button>
                        </form>

                        @if($m->personas_count > 0)
                            <span class="text-gray-400 cursor-not-allowed"
                                  title="Tiene {{ $m->personas_count }} persona(s) asignada(s). Desactívalo en su lugar o quita las asignaciones desde Personas.">Eliminar</span>
                        @else
                            <form method="POST" action="{{ route('admin.ministerios.destroy', $m) }}" class="inline" onsubmit="return confirm({{ \Illuminate\Support\Js::from('¿Eliminar el ministerio '.$m->nombre.'? No tiene personas asignadas.') }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                        No hay ministerios registrados. <a href="{{ route('admin.ministerios.create') }}" class="text-blue-600 underline">Crea el primero</a>.
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

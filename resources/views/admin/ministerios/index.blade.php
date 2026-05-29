@extends('layouts.admin')

@section('title', tenant_title('Ministerios'))
@section('page-title', 'Ministerios / Áreas de Servicio')

@section('content')
<div class="space-y-6">

    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        {{ session('success') }}
    </div>
    @endif

    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-600">
            Gestiona los ministerios (Alabanza, Transmisión, Ujieres, etc.). Cada persona puede estar en varios.
        </p>
        <a href="{{ route('admin.ministerios.create') }}"
           class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-semibold">
            + Nuevo ministerio
        </a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Orden</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Nombre</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Slug</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Descripción</th>
                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-700 uppercase">Activos</th>
                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-700 uppercase">Estado</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($ministerios as $m)
                <tr>
                    <td class="px-4 py-2 text-sm">{{ $m->orden }}</td>
                    <td class="px-4 py-2 text-sm">
                        <span class="inline-flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" style="background:{{ $m->color }}"></span>
                            <strong>{{ $m->nombre }}</strong>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-500"><code>{{ $m->slug }}</code></td>
                    <td class="px-4 py-2 text-sm text-gray-600">{{ $m->descripcion ?? '—' }}</td>
                    <td class="px-4 py-2 text-sm text-center font-semibold">{{ $m->personas_activas_count }}</td>
                    <td class="px-4 py-2 text-sm text-center">
                        @if($m->activo)
                            <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs">Activo</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 text-xs">Inactivo</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-sm text-right">
                        <a href="{{ route('admin.ministerios.edit', $m) }}" class="text-blue-600 hover:underline mr-3">Editar</a>
                        <form method="POST" action="{{ route('admin.ministerios.destroy', $m) }}" class="inline" onsubmit="return confirm('¿Eliminar este ministerio? Las asignaciones de personas también se borrarán.')">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                        No hay ministerios registrados. <a href="{{ route('admin.ministerios.create') }}" class="text-blue-600 underline">Crea el primero</a>.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

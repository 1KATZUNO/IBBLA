@extends('layouts.admin')

@section('title', tenant_title('Gestión de Clases'))
@section('page-title', 'Administrar Clases de Asistencia')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex flex-wrap justify-between items-center gap-3">
        <p class="text-gray-600">Configura las clases que aparecerán en el registro de asistencia</p>
        <a href="{{ route('admin.clases.create') }}" class="btn-primary">
            + Nueva Clase
        </a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="table-scroll">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orden</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden xl:table-cell">Slug</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden xl:table-cell">Color</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Maestros</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($clases as $clase)
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ $clase->orden }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                        <span class="inline-flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full flex-shrink-0 xl:hidden" style="background-color: {{ $clase->color }}" aria-hidden="true"></span>
                            {{ $clase->nombre }}
                        </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 font-mono hidden xl:table-cell">
                        {{ $clase->slug }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm hidden xl:table-cell">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded" style="background-color: {{ $clase->color }}"></div>
                            <span class="text-gray-600">{{ $clase->color }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                        @if($clase->tiene_maestros)
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Sí</span>
                        @else
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">No</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                        @if($clase->activa)
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Activa</span>
                        @else
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inactiva</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('admin.clases.edit', $clase) }}" class="btn-row text-blue-700 hover:bg-blue-50">Editar</a>
                        <button type="button" onclick="mostrarModalEliminar({{ $clase->id }}, @js($clase->nombre))" class="btn-row text-red-700 hover:bg-red-50">
                            Eliminar
                        </button>
                        <form id="form-eliminar-{{ $clase->id }}" action="{{ route('admin.clases.destroy', $clase) }}" method="POST" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <p class="mt-4">No hay clases configuradas</p>
                        <p class="mt-2"><a href="{{ route('admin.clases.create') }}" class="text-blue-600 hover:underline">Crear la primera clase</a></p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Modal: Confirmar Eliminación -->
<div id="modalEliminar" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-auto sm:w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-red-900">⚠️ Eliminar Clase</h3>
                <button type="button" onclick="cerrarModalEliminar()" class="btn-icon" aria-label="Cerrar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-yellow-800 mb-2">
                    ¿Estás seguro de que deseas eliminar la clase <strong id="nombreClase"></strong>?
                </p>
                <p class="text-sm text-yellow-700 mt-2">
                    Los datos de asistencia ya registrados no se eliminarán.
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" 
                        onclick="cerrarModalEliminar()"
                        class="btn-secondary">
                    Cancelar
                </button>
                <button type="button" 
                        onclick="confirmarEliminacion()"
                        class="btn-danger">
                    Sí, Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let claseIdEliminar = null;

function mostrarModalEliminar(id, nombre) {
    claseIdEliminar = id;
    document.getElementById('nombreClase').textContent = nombre;
    document.getElementById('modalEliminar').classList.remove('hidden');
}

function cerrarModalEliminar() {
    document.getElementById('modalEliminar').classList.add('hidden');
    claseIdEliminar = null;
}

function confirmarEliminacion() {
    if (claseIdEliminar) {
        document.getElementById('form-eliminar-' + claseIdEliminar).submit();
    }
}
</script>
@endsection

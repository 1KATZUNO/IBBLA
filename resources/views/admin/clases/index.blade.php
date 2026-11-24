@extends('layouts.admin')

@section('title', tenant_title('Gestión de Clases'))
@section('page-title', 'Administrar Clases de Asistencia')

@section('page-subtitle', 'Las clases que aparecen en el registro de asistencia y en la App de Clases.')

@section('page-actions')
    <a href="{{ route('admin.clases.create') }}" class="btn-primary">+ Nueva Clase</a>
@endsection

@section('content')
<div class="max-w-6xl">
    <div class="card overflow-hidden">
        <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-num">Orden</th>
                    <th>Nombre</th>
                    <th class="hidden xl:table-cell">Slug</th>
                    <th class="hidden xl:table-cell">Color</th>
                    <th>Maestros</th>
                    <th>Estado</th>
                    <th class="col-actions">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clases as $clase)
                <tr>
                    <td class="col-num whitespace-nowrap font-medium text-gray-900">{{ $clase->orden }}</td>
                    <td class="whitespace-nowrap text-gray-900">
                        <span class="inline-flex items-center gap-2">
                            <span class="h-3 w-3 flex-shrink-0 rounded-full xl:hidden" style="background-color: {{ $clase->color }}" aria-hidden="true"></span>
                            {{ $clase->nombre }}
                        </span>
                    </td>
                    <td class="hidden whitespace-nowrap font-mono text-gray-500 xl:table-cell">{{ $clase->slug }}</td>
                    <td class="hidden whitespace-nowrap xl:table-cell">
                        <div class="flex items-center gap-2">
                            <div class="h-6 w-6 rounded" style="background-color: {{ $clase->color }}"></div>
                            <span class="text-gray-600">{{ $clase->color }}</span>
                        </div>
                    </td>
                    <td class="whitespace-nowrap">
                        <x-ui.badge :tipo="$clase->tiene_maestros ? 'success' : 'neutral'">{{ $clase->tiene_maestros ? 'Sí' : 'No' }}</x-ui.badge>
                    </td>
                    <td class="whitespace-nowrap">
                        <x-ui.badge :tipo="$clase->activa ? 'success' : 'danger'" punto>{{ $clase->activa ? 'Activa' : 'Inactiva' }}</x-ui.badge>
                    </td>
                    <td class="col-actions">
                        <a href="{{ route('admin.clases.edit', $clase) }}" class="btn-row text-brand-700 hover:bg-brand-50">Editar</a>
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
                    <td colspan="7" class="!p-0">
                        <x-ui.vacio icono="ajustes" titulo="No hay clases configuradas" texto="Crea la primera clase para registrar la asistencia por clase.">
                            <x-slot name="acciones"><a href="{{ route('admin.clases.create') }}" class="btn-primary">+ Nueva Clase</a></x-slot>
                        </x-ui.vacio>
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

@extends('layouts.admin')

@section('title', tenant_title('Gestión de Usuarios'))
@section('page-title', 'Gestión de Usuarios')

@section('page-subtitle', 'Quién entra al sistema y con qué rol. Cada rol ve solo lo suyo.')

@section('page-actions')
    <a href="{{ route('usuarios.create') }}" class="btn-primary">+ Nuevo Usuario</a>
@endsection

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap justify-end gap-2">
        @if(Route::has('admin.accesos.index'))
            <a href="{{ route('admin.accesos.index') }}" class="btn-secondary">Accesos de edición</a>
        @endif
        <a href="{{ route('usuarios.create') }}" class="btn-primary">
            + Nuevo Usuario
        </a>
    </div>

    <div class="card overflow-hidden">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th class="hidden xl:table-cell">Fecha de registro</th>
                        <th class="col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($usuarios as $usuario)
                    <tr>
                        <td class="whitespace-nowrap font-medium text-gray-900">{{ $usuario->name }}</td>
                        <td class="whitespace-nowrap">{{ $usuario->email }}</td>
                        <td class="whitespace-nowrap">
                            <x-ui.badge :tipo="in_array($usuario->rol, ['admin', 'tesorero', 'asistente']) ? 'brand' : 'neutral'">{{ $usuario->rol_nombre }}</x-ui.badge>
                        </td>
                        <td class="hidden whitespace-nowrap xl:table-cell">{{ $usuario->created_at->format('d/m/Y') }}</td>
                        <td class="col-actions">
                            <a href="{{ route('usuarios.edit', $usuario) }}" class="btn-row text-brand-700 hover:bg-brand-50">Editar</a>
                            @if($usuario->id !== auth()->id())
                            <button type="button" onclick="mostrarModalEliminar({{ $usuario->id }}, @js($usuario->name))" class="btn-row text-red-700 hover:bg-red-50">
                                Eliminar
                            </button>
                            <form id="form-eliminar-{{ $usuario->id }}" action="{{ route('usuarios.destroy', $usuario) }}" method="POST" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="!p-0">
                            <x-ui.vacio icono="usuario" titulo="Todavía no hay usuarios" texto="Crea el primero para darle acceso al sistema.">
                                <x-slot name="acciones"><a href="{{ route('usuarios.create') }}" class="btn-primary">+ Nuevo Usuario</a></x-slot>
                            </x-ui.vacio>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($usuarios->hasPages())
        <div class="card-footer">
            {{ $usuarios->links() }}
        </div>
        @endif
    </div>
</div>

    <!-- Modal: Confirmar Eliminación -->
    <div id="modalEliminar" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-auto sm:w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-red-900">⚠️ Eliminar Usuario</h3>
                    <button type="button" onclick="cerrarModalEliminar()" class="btn-icon" aria-label="Cerrar">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-yellow-800 mb-2">
                        ¿Estás seguro de que deseas eliminar al usuario <strong id="nombreUsuario"></strong>?
                    </p>
                    <p class="text-xs text-red-600 mt-2">
                        ⚠️ Esta acción no se puede deshacer.
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
    let usuarioIdEliminar = null;

    function mostrarModalEliminar(id, nombre) {
        usuarioIdEliminar = id;
        document.getElementById('nombreUsuario').textContent = nombre;
        document.getElementById('modalEliminar').classList.remove('hidden');
    }

    function cerrarModalEliminar() {
        document.getElementById('modalEliminar').classList.add('hidden');
        usuarioIdEliminar = null;
    }

    function confirmarEliminacion() {
        if (usuarioIdEliminar) {
            document.getElementById('form-eliminar-' + usuarioIdEliminar).submit();
        }
    }
    </script>
@endsection

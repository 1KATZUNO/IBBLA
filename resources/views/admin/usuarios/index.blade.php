@extends('layouts.admin')

@section('title', 'IBBSC - Gestión de Usuarios')
@section('page-title', 'Gestión de Usuarios')

@section('content')
<div class="space-y-6">
    <div class="flex justify-end">
        <a href="{{ route('usuarios.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shadow transition-colors">
            + Nuevo Usuario
        </a>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Nombre
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Email
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Rol
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fecha Registro
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($usuarios as $usuario)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $usuario->name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ $usuario->email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        @if($usuario->rol === 'admin') bg-purple-100 text-purple-800
                                        @elseif($usuario->rol === 'tesorero') bg-blue-100 text-blue-800
                                        @elseif($usuario->rol === 'asistente') bg-green-100 text-green-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($usuario->rol) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $usuario->created_at->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('usuarios.edit', $usuario) }}" class="text-blue-600 hover:text-blue-900 mr-3">Editar</a>
                                    @if($usuario->id !== auth()->id())
                                    <button type="button" onclick="mostrarModalEliminar({{ $usuario->id }}, '{{ $usuario->name }}')" class="text-red-600 hover:text-red-900">
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
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    No hay usuarios registrados
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
        </div>

        <div class="px-6 py-4 bg-gray-50">
            {{ $usuarios->links() }}
        </div>
    </div>
</div>

    <!-- Modal: Confirmar Eliminación -->
    <div id="modalEliminar" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-red-900">⚠️ Eliminar Usuario</h3>
                    <button onclick="cerrarModalEliminar()" class="text-gray-400 hover:text-gray-600">
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
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" 
                            onclick="confirmarEliminacion()"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
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

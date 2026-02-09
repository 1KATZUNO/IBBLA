@extends('layouts.admin')

@section('title', 'IBBSC - Cultos')
@section('page-title', 'Gestión de Cultos')

@section('content')
<div class="space-y-6">
    <div class="flex justify-end">
        <a href="{{ route('cultos.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
            + Nuevo Culto
        </a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hora</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Ingresos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asistencia</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($cultos as $culto)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ $culto->fecha->format('d/m/Y') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ \Carbon\Carbon::parse($culto->hora)->format('h:i A') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            {{ ucfirst($culto->tipo_culto) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                        @php
                            $t = $culto->totales;
                            $totalGeneral = $t ? $t->total_general : ($culto->sobres->flatMap->detalles->sum('monto') + $culto->ofrendasSueltas->sum('monto'));
                        @endphp
                        ₡{{ number_format($totalGeneral, 2) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $culto->asistencia ? $culto->asistencia->total_asistencia : '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('cultos.show', $culto) }}" class="text-green-600 hover:text-green-900 mr-3">Ver</a>
                        <a href="{{ route('cultos.edit', $culto) }}" class="text-blue-600 hover:text-blue-900 mr-3">Editar</a>
                        <button type="button" onclick="mostrarModalEliminar({{ $culto->id }}, '{{ $culto->fecha->format('d/m/Y') }} - {{ ucfirst($culto->tipo_culto) }}')" class="text-red-600 hover:text-red-900">
                            Eliminar
                        </button>
                        <form id="form-eliminar-{{ $culto->id }}" action="{{ route('cultos.destroy', $culto) }}" method="POST" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        No hay cultos registrados
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $cultos->links() }}
    </div>
</div>

<!-- Modal: Confirmar Eliminación -->
<div id="modalEliminar" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-full max-w-sm sm:max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-red-900">⚠️ Eliminar Culto</h3>
                <button onclick="cerrarModalEliminar()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-yellow-800 mb-2">
                    ¿Estás seguro de que deseas eliminar el culto <strong id="nombreCulto"></strong>?
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
let cultoIdEliminar = null;

function mostrarModalEliminar(id, nombre) {
    cultoIdEliminar = id;
    document.getElementById('nombreCulto').textContent = nombre;
    document.getElementById('modalEliminar').classList.remove('hidden');
}

function cerrarModalEliminar() {
    document.getElementById('modalEliminar').classList.add('hidden');
    cultoIdEliminar = null;
}

function confirmarEliminacion() {
    if (cultoIdEliminar) {
        document.getElementById('form-eliminar-' + cultoIdEliminar).submit();
    }
}
</script>
@endsection

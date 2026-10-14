@extends('layouts.admin')

@section('title', tenant_title('Cultos'))
@section('page-title', 'Gestión de Cultos')

@section('page-subtitle', 'Crea el culto antes de registrar sus sobres y su asistencia.')

@section('page-actions')
    <a href="{{ route('cultos.create') }}" class="btn-primary">+ Nuevo Culto</a>
@endsection

@section('content')
<div class="space-y-6">
    <div class="card overflow-hidden">
        <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th class="hidden xl:table-cell">Hora</th>
                    <th>Tipo</th>
                    <th class="col-num">Total ingresos</th>
                    <th class="hidden xl:table-cell col-num">Asistencia</th>
                    <th class="col-actions">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cultos as $culto)
                <tr>
                    <td class="whitespace-nowrap font-medium text-gray-900">
                        {{ $culto->fecha->format('d/m/Y') }}
                    </td>
                    <td class="hidden whitespace-nowrap xl:table-cell">
                        {{ \Carbon\Carbon::parse($culto->hora)->format('h:i A') }}
                    </td>
                    <td class="whitespace-nowrap">
                        <x-ui.badge tipo="brand">{{ $culto->tipo_nombre }}</x-ui.badge>
                        @if($culto->cerrado)
                            <x-ui.badge tipo="neutral" class="ml-1">Cerrado</x-ui.badge>
                        @endif
                    </td>
                    <td class="col-num whitespace-nowrap font-semibold text-gray-900">
                        @php
                            $t = $culto->totales;
                            $totalGeneral = $t ? $t->total_general : ($culto->sobres->flatMap->detalles->sum('monto') + $culto->ofrendasSueltas->sum('monto'));
                        @endphp
                        ₡{{ number_format($totalGeneral, 2) }}
                    </td>
                    <td class="hidden xl:table-cell col-num whitespace-nowrap">
                        {{ $culto->asistencia ? $culto->asistencia->total_asistencia : '-' }}
                    </td>
                    <td class="col-actions">
                        <a href="{{ route('cultos.show', $culto) }}" class="btn-row text-green-700 hover:bg-green-50">Ver</a>
                        <a href="{{ route('cultos.edit', $culto) }}" class="btn-row text-brand-700 hover:bg-brand-50">Editar</a>
                        <button type="button" onclick="mostrarModalEliminar({{ $culto->id }}, @js($culto->fecha->format('d/m/Y') . ' - ' . $culto->tipo_nombre))" class="btn-row text-red-700 hover:bg-red-50">
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
                    <td colspan="6" class="!p-0">
                        <x-ui.vacio icono="calendario" titulo="Todavía no hay cultos" texto="Crea el primer culto para empezar a registrar sus sobres y su asistencia.">
                            <x-slot name="acciones"><a href="{{ route('cultos.create') }}" class="btn-primary">+ Nuevo Culto</a></x-slot>
                        </x-ui.vacio>
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
                <button type="button" onclick="cerrarModalEliminar()" class="btn-icon" aria-label="Cerrar">
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

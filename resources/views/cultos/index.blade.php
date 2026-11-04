@extends('layouts.admin')

@section('title', tenant_title('Cultos'))
@section('page-title', 'Gestión de Cultos')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600">Crea el culto antes de registrar sus sobres y su asistencia.</p>
        <a href="{{ route('cultos.create') }}" class="btn-primary">
            + Nuevo Culto
        </a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="table-scroll">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden xl:table-cell">Hora</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Ingresos</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden xl:table-cell">Asistencia</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($cultos as $culto)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ $culto->fecha->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 hidden xl:table-cell">
                        {{ \Carbon\Carbon::parse($culto->hora)->format('h:i A') }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            {{ $culto->tipo_nombre }}
                        </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">
                        @php
                            $t = $culto->totales;
                            $totalGeneral = $t ? $t->total_general : ($culto->sobres->flatMap->detalles->sum('monto') + $culto->ofrendasSueltas->sum('monto'));
                        @endphp
                        ₡{{ number_format($totalGeneral, 2) }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 hidden xl:table-cell">
                        {{ $culto->asistencia ? $culto->asistencia->total_asistencia : '-' }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('cultos.show', $culto) }}" class="btn-row text-green-700 hover:bg-green-50">Ver</a>
                        <a href="{{ route('cultos.edit', $culto) }}" class="btn-row text-blue-700 hover:bg-blue-50">Editar</a>
                        @php
                            $conDatos = $culto->cerrado || $culto->sobres->isNotEmpty() || $culto->ofrendasSueltas->isNotEmpty();
                        @endphp
                        <x-permiso :permiso="Gate::inspect('delete', $culto)">
                            <button type="button" class="btn-row text-red-700 hover:bg-red-50"
                                    data-eliminar-culto="{{ route('cultos.destroy', $culto) }}"
                                    data-nombre="{{ $culto->fecha->format('d/m/Y') }} - {{ $culto->tipo_nombre }}"
                                    data-con-datos="{{ $conDatos ? '1' : '0' }}"
                                    data-resumen="{{ $culto->cerrado ? 'Culto CERRADO' : 'Culto abierto' }} · {{ $culto->sobres->count() }} sobres · ₡{{ number_format($totalGeneral, 2) }}">
                                Eliminar
                            </button>
                        </x-permiso>
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

{{-- Eliminar culto (QA-003): solo admin; si esta cerrado o tiene recuento pide confirmacion y motivo, y se audita --}}
<div id="modalEliminar" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" role="dialog" aria-modal="true" aria-labelledby="tituloEliminarCulto">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-full max-w-sm sm:max-w-md shadow-lg rounded-md bg-white">
        <form method="POST" id="formEliminarCulto" class="space-y-4">
            @csrf
            @method('DELETE')
            <div class="flex items-center justify-between">
                <h3 id="tituloEliminarCulto" class="text-lg font-semibold text-red-900">Eliminar culto</h3>
                <button type="button" data-cerrar-eliminar class="btn-icon" aria-label="Cerrar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-900">
                <p>¿Eliminar el culto <strong id="nombreCulto"></strong>?</p>
                <p class="mt-1 font-medium" id="resumenCulto"></p>
            </div>
            <div id="camposMotivo" class="hidden space-y-3">
                <p class="text-sm text-red-800">Se borran también todos sus sobres, el dinero suelto y los egresos. No se puede deshacer; queda registrado en la auditoría.</p>
                <div>
                    <label for="motivoEliminar" class="block text-sm font-medium text-gray-800 mb-1">Motivo (obligatorio)</label>
                    <textarea id="motivoEliminar" name="motivo" rows="3" minlength="10" maxlength="500" class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500" placeholder="Ej.: culto creado dos veces por error; los sobres están en el culto del 12/10"></textarea>
                </div>
                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input type="checkbox" name="confirmo" value="1" id="confirmoEliminar" class="mt-0.5 h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                    Entiendo que se borran el culto y todo su recuento.
                </label>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" data-cerrar-eliminar class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-danger">Sí, eliminar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('modalEliminar');
    const form = document.getElementById('formEliminarCulto');
    const campos = document.getElementById('camposMotivo');
    const motivo = document.getElementById('motivoEliminar');
    const confirmo = document.getElementById('confirmoEliminar');

    document.addEventListener('click', function (e) {
        const boton = e.target.closest('[data-eliminar-culto]');
        if (boton) {
            const conDatos = boton.dataset.conDatos === '1';
            form.action = boton.dataset.eliminarCulto;
            document.getElementById('nombreCulto').textContent = boton.dataset.nombre;
            document.getElementById('resumenCulto').textContent = boton.dataset.resumen;
            campos.classList.toggle('hidden', !conDatos);
            motivo.required = conDatos;
            confirmo.required = conDatos;
            motivo.value = '';
            confirmo.checked = false;
            modal.classList.remove('hidden');
            (conDatos ? motivo : form.querySelector('[type=submit]')).focus();
            return;
        }
        if (e.target.closest('[data-cerrar-eliminar]') || e.target === modal) {
            modal.classList.add('hidden');
        }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.classList.add('hidden'); });
})();
</script>
@endsection

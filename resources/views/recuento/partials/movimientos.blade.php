{{-- Dinero suelto y egresos del culto: aparte de los sobres y con sus propias acciones --}}
@if($ofrendasSueltas->isNotEmpty() || $egresos->isNotEmpty())
<section class="bg-white rounded-lg shadow overflow-hidden" aria-labelledby="titulo-movimientos">
    <div class="px-4 sm:px-6 py-3 border-b border-gray-200">
        <h3 id="titulo-movimientos" class="text-lg font-semibold text-gray-900">Dinero suelto y egresos</h3>
    </div>
    <ul class="divide-y divide-gray-100">
        @foreach($ofrendasSueltas as $ofrenda)
            <li class="flex flex-wrap items-center gap-3 px-4 sm:px-6 py-2">
                <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Dinero suelto</span>
                <span class="flex-1 min-w-[8rem] text-sm text-gray-600">{{ $ofrenda->descripcion ?: 'Sin descripción' }}</span>
                <span class="text-sm font-semibold tabular-nums text-green-700">
                    {{ $ofrenda->simbolo_moneda }}{{ number_format($ofrenda->monto, 2) }}
                    @if($ofrenda->moneda === 'USD')
                        <span class="block text-xs font-normal text-gray-400">≈ ₡{{ number_format($ofrenda->monto_crc, 2) }}</span>
                    @endif
                </span>
                @if($puedeEditar)
                    <span class="flex items-center gap-1">
                        <button type="button" class="btn-icon text-blue-600 hover:bg-blue-50 hover:text-blue-800" aria-label="Editar dinero suelto" title="Editar"
                                data-abrir-movimiento="suelto"
                                data-accion="{{ route('recuento.update-suelto', $ofrenda) }}"
                                data-monto="{{ $ofrenda->monto }}"
                                data-descripcion="{{ $ofrenda->descripcion }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </button>
                        <button type="button" class="btn-icon text-red-600 hover:bg-red-50 hover:text-red-700" aria-label="Eliminar dinero suelto" title="Eliminar"
                                data-confirmar="#form-eliminar-suelto-{{ $ofrenda->id }}"
                                data-titulo="Eliminar dinero suelto"
                                data-mensaje="¿Eliminar este dinero suelto de {{ $ofrenda->simbolo_moneda }}{{ number_format($ofrenda->monto, 2) }}?"
                                data-boton="Sí, eliminar">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </span>
                    <form id="form-eliminar-suelto-{{ $ofrenda->id }}" action="{{ route('recuento.destroy-suelto', $ofrenda) }}" method="POST" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            </li>
        @endforeach
        @foreach($egresos as $egreso)
            <li class="flex flex-wrap items-center gap-3 px-4 sm:px-6 py-2">
                <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">Egreso</span>
                <span class="flex-1 min-w-[8rem] text-sm text-gray-600">{{ $egreso->descripcion ?: 'Sin descripción' }}</span>
                <span class="text-sm font-semibold tabular-nums text-red-700">
                    −{{ $egreso->simbolo_moneda }}{{ number_format($egreso->monto, 2) }}
                    @if($egreso->moneda === 'USD')
                        <span class="block text-xs font-normal text-gray-400">≈ −₡{{ number_format($egreso->monto_crc, 2) }}</span>
                    @endif
                </span>
                @if($puedeEditar)
                    <span class="flex items-center gap-1">
                        <button type="button" class="btn-icon text-blue-600 hover:bg-blue-50 hover:text-blue-800" aria-label="Editar egreso" title="Editar"
                                data-abrir-movimiento="egreso"
                                data-accion="{{ route('recuento.update-egreso', $egreso) }}"
                                data-monto="{{ $egreso->monto }}"
                                data-descripcion="{{ $egreso->descripcion }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </button>
                        <button type="button" class="btn-icon text-red-600 hover:bg-red-50 hover:text-red-700" aria-label="Eliminar egreso" title="Eliminar"
                                data-confirmar="#form-eliminar-egreso-{{ $egreso->id }}"
                                data-titulo="Eliminar egreso"
                                data-mensaje="¿Eliminar el egreso de {{ $egreso->simbolo_moneda }}{{ number_format($egreso->monto, 2) }}{{ $egreso->descripcion ? ' ('.$egreso->descripcion.')' : '' }}?"
                                data-boton="Sí, eliminar">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </span>
                    <form id="form-eliminar-egreso-{{ $egreso->id }}" action="{{ route('recuento.destroy-egreso', $egreso) }}" method="POST" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
</section>
@endif

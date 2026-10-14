{{-- Encabezado del culto: que culto es, su estado y su resumen a la izquierda; las acciones a la derecha --}}
@php
    $diaCulto = ucfirst($culto->fecha->locale('es')->isoFormat('dddd'));
@endphp
<section class="bg-white rounded-lg shadow" aria-labelledby="titulo-culto">
    <div class="p-4 sm:p-6 flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('recuento.index') }}" class="inline-flex items-center gap-1 min-h-[44px] -mt-3 text-sm font-medium text-blue-700 hover:text-blue-900 hover:underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Todos los cultos
            </a>
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="titulo-culto" class="text-xl sm:text-2xl font-bold text-gray-900">{{ $diaCulto }} {{ $culto->fecha->format('d/m/Y') }}</h2>
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-sm font-medium text-blue-800">{{ $culto->tipo_nombre }}</span>
                @if($culto->cerrado)
                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-semibold text-gray-700">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        Cerrado{{ $culto->cerrado_at ? ' el '.$culto->cerrado_at->format('d/m') : '' }}{{ $culto->cerradoPor ? ' por '.$culto->cerradoPor->name : '' }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-0.5 text-sm font-semibold text-green-800">
                        <span class="h-2 w-2 rounded-full bg-green-600" aria-hidden="true"></span>
                        Abierto
                    </span>
                @endif
            </div>

            {{-- Resumen en una linea: lo que antes eran ocho tarjetas --}}
            <dl class="mt-4 grid grid-cols-2 sm:flex sm:flex-wrap gap-x-8 gap-y-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Sobres</dt>
                    <dd class="text-lg font-semibold tabular-nums text-gray-900">{{ $resumen->cantidadSobres }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Efectivo</dt>
                    <dd class="text-lg font-semibold tabular-nums text-gray-900">₡{{ number_format($resumen->efectivo(), 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 whitespace-nowrap">Transferencias ({{ $resumen->cantidadTransferencias }})</dt>
                    <dd class="text-lg font-semibold tabular-nums text-gray-900">₡{{ number_format($resumen->transferencias(), 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Total</dt>
                    <dd class="text-lg font-bold tabular-nums text-blue-700">₡{{ number_format($resumen->total(), 2) }}</dd>
                </div>
            </dl>
            <p class="mt-2 text-xs text-gray-500">
                Efectivo = sobres en efectivo + dinero suelto − egresos.
                @if($resumen->tieneUsd)
                    Incluye montos en dólares convertidos a colones con el tipo de cambio de su registro.
                @endif
            </p>
        </div>

        {{-- Acciones a la derecha (en tablet vertical bajan debajo del resumen) --}}
        <div class="toolbar xl:justify-end xl:max-w-xl">
            @if($puedeEditar)
                <a href="{{ route('recuento.create', ['culto_id' => $culto->id]) }}" class="btn-primary">+ Agregar sobre</a>
                <button type="button" class="btn-success" data-abrir-movimiento="suelto">+ Dinero suelto</button>
                <button type="button" class="btn-danger-soft" data-abrir-movimiento="egreso">− Egreso</button>
            @endif
            <details class="relative" data-menu>
                <summary class="btn-secondary list-none cursor-pointer [&::-webkit-details-marker]:hidden">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    PDF
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="absolute right-0 z-30 mt-1 w-60 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                    <a href="{{ route('ingresos-asistencia.pdf-recuento-individual', $culto->id) }}" class="flex items-center min-h-[44px] px-4 text-sm text-gray-700 hover:bg-gray-50">Recuento completo</a>
                    <a href="{{ route('ingresos-asistencia.pdf-recuento-transferencias', $culto->id) }}" class="flex items-center min-h-[44px] px-4 text-sm text-gray-700 hover:bg-gray-50">Solo transferencias</a>
                </div>
            </details>
            @if(! $culto->cerrado && $puedeEditar)
                <button type="button" class="btn-warning"
                        data-confirmar="#form-cerrar-culto"
                        data-titulo="Cerrar culto"
                        data-mensaje="¿Cerrar el culto del {{ $culto->fecha->format('d/m/Y') }}? Después ya no se podrán agregar ni editar sobres (solo el administrador podrá corregirlo). El tipo de cambio queda congelado."
                        data-boton="Sí, cerrar"
                        data-variante="btn-warning">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Cerrar culto
                </button>
                <form id="form-cerrar-culto" action="{{ route('recuento.cerrar-culto', $culto) }}" method="POST" class="hidden">
                    @csrf
                </form>
            @endif
        </div>
    </div>
</section>

{{-- Sobres del culto, uno por linea, con buscador por numero o persona. Los anonimos se marcan en rojo,
     se pueden ver solos (?anonimos=1) y se les asigna persona desde la fila (pedido del cliente 04/11). --}}
@php
    $sobresVisibles = $soloAnonimos ? $sobres->whereNull('persona_id') : $sobres;
@endphp
<section class="bg-white rounded-lg shadow overflow-hidden" aria-labelledby="titulo-sobres">
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 sm:px-6 py-3 border-b border-gray-200">
        <h3 id="titulo-sobres" class="text-lg font-semibold text-gray-900">
            Sobres <span class="text-sm font-normal text-gray-500">({{ $sobres->count() }})</span>
        </h3>
        @if($cantidadAnonimos > 0 || $soloAnonimos)
            <a href="{{ route('recuento.culto', ['culto' => $culto->id, 'anonimos' => $soloAnonimos ? null : 1]) }}"
               class="inline-flex items-center gap-2 min-h-[44px] px-3 rounded-full border text-sm font-medium {{ $soloAnonimos ? 'border-red-600 bg-red-50 text-red-800' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}"
               aria-pressed="{{ $soloAnonimos ? 'true' : 'false' }}">
                Solo anónimos
                <span class="inline-flex min-w-[1.5rem] justify-center rounded-full bg-red-600 px-1.5 text-xs font-bold text-white">{{ $cantidadAnonimos }}</span>
            </a>
        @endif
        @if($sobresVisibles->count() > 5)
            <label class="w-full sm:w-72">
                <span class="sr-only">Buscar sobre por número o persona</span>
                <input type="search" placeholder="Buscar por número o persona" autocomplete="off" data-filtrar-sobres
                       class="w-full min-h-[44px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            </label>
        @endif
    </div>
    <div class="table-scroll">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N°</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Persona</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Método</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden xl:table-cell">Detalle</th>
                    @if($accionesVisibles)
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100" id="tabla-sobres">
                @forelse($sobresVisibles as $sobre)
                    @php $nombrePersona = $sobre->persona ? $sobre->persona->nombre : 'Anónimo'; @endphp
                    <tr class="hover:bg-gray-50" data-buscar="{{ \Illuminate\Support\Str::lower('#'.$sobre->numero_sobre.' '.$sobre->numero_sobre.' '.$nombrePersona) }}">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">#{{ $sobre->numero_sobre }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            @if($sobre->persona)
                                {{ $nombrePersona }}
                                @unless($sobre->persona->activo)
                                    <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">inactiva</span>
                                @endunless
                            @else
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-red-800" data-sobre-anonimo>Anónimo</span>
                                @if($puedeEditar)
                                    <details class="mt-1">
                                        <summary class="inline-flex items-center min-h-[44px] cursor-pointer text-sm font-medium text-blue-700 hover:text-blue-900">Asignar persona</summary>
                                        <form action="{{ route('recuento.asignar-persona', $sobre) }}" method="POST" class="mt-1 flex flex-wrap items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            @if($soloAnonimos)<input type="hidden" name="anonimos" value="1">@endif
                                            <label for="asignar-{{ $sobre->id }}" class="sr-only">Persona del sobre #{{ $sobre->numero_sobre }}</label>
                                            <input type="text" id="asignar-{{ $sobre->id }}" name="persona_nombre" list="lista-personas-asignar" required autocomplete="off"
                                                   placeholder="Nombre o PIN" class="min-h-[44px] w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <button type="submit" class="btn-primary">Asignar</button>
                                        </form>
                                    </details>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm hidden sm:table-cell">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $sobre->metodo_pago == 'transferencia' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                {{ ucfirst($sobre->metodo_pago) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold tabular-nums text-gray-900">
                            {{ $sobre->simbolo_moneda }}{{ number_format($sobre->total_declarado, 2) }}
                            @if($sobre->moneda === 'USD')
                                <span class="ml-1 text-xs bg-yellow-100 text-yellow-800 px-1.5 py-0.5 rounded-full">USD</span>
                                @if($sobre->tipo_cambio_venta)
                                    <span class="block text-xs text-gray-400 font-normal">≈ ₡{{ number_format($sobre->total_declarado_crc, 2) }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 hidden xl:table-cell">
                            @foreach($sobre->detalles as $detalle)
                                <span class="inline-block bg-gray-100 rounded px-2 py-1 text-xs mr-1 mb-1">
                                    {{ $categorias->firstWhere('slug', $detalle->categoria)->nombre ?? ucfirst($detalle->categoria) }}: {{ $sobre->simbolo_moneda }}{{ number_format($detalle->monto, 2) }}
                                </span>
                            @endforeach
                            @if($sobre->metodo_pago === 'transferencia' && $sobre->comprobante_numero)
                                <span class="inline-block bg-blue-100 text-blue-800 rounded px-2 py-1 text-xs mr-1 mb-1">Comprobante: {{ $sobre->comprobante_numero }}</span>
                            @endif
                            @if($sobre->notas)
                                <span class="block text-xs text-gray-500 mt-1">Notas: {{ $sobre->notas }}</span>
                            @endif
                        </td>
                        @if($accionesVisibles)
                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-1">
                                    <x-permiso :permiso="$permisoEditar" etiqueta="Editar sobre #{{ $sobre->numero_sobre }}">
                                        <a href="{{ route('recuento.edit', $sobre) }}" class="btn-row text-blue-700 hover:bg-blue-50" aria-label="Editar sobre #{{ $sobre->numero_sobre }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            <span class="hidden sm:inline">Editar</span>
                                        </a>
                                    </x-permiso>
                                    <x-permiso :permiso="$permisoEditar" etiqueta="Eliminar sobre #{{ $sobre->numero_sobre }}">
                                        <button type="button" class="btn-icon text-red-600 hover:bg-red-50 hover:text-red-700" aria-label="Eliminar sobre #{{ $sobre->numero_sobre }}" title="Eliminar"
                                                data-confirmar="#form-eliminar-sobre-{{ $sobre->id }}"
                                                data-titulo="Eliminar sobre"
                                                data-mensaje="¿Eliminar el sobre #{{ $sobre->numero_sobre }} ({{ $nombrePersona }}, {{ $sobre->simbolo_moneda }}{{ number_format($sobre->total_declarado, 2) }})?"
                                                data-boton="Sí, eliminar">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </x-permiso>
                                </div>
                                @if($puedeEditar)
                                    <form id="form-eliminar-sobre-{{ $sobre->id }}" action="{{ route('recuento.destroy', $sobre) }}" method="POST" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $accionesVisibles ? 6 : 5 }}" class="px-6 py-10 text-center">
                            @if($soloAnonimos)
                                <p class="text-base font-semibold text-gray-800">No quedan sobres anónimos en este culto</p>
                                <a href="{{ route('recuento.culto', $culto) }}" class="btn-secondary mt-4">Ver todos los sobres</a>
                            @else
                            <p class="text-base font-semibold text-gray-800">Este culto no tiene sobres</p>
                            @if($puedeEditar)
                                <p class="mt-1 text-sm text-gray-500">Registra el primero; después sigue con "Guardar y siguiente" sin salir del formulario.</p>
                                <a href="{{ route('recuento.create', ['culto_id' => $culto->id]) }}" class="btn-primary mt-4">+ Agregar primer sobre</a>
                            @endif
                            @endif
                        </td>
                    </tr>
                @endforelse
                <tr class="hidden" data-sin-resultados>
                    <td colspan="{{ $accionesVisibles ? 6 : 5 }}" class="px-6 py-8 text-center text-sm text-gray-500">Ningún sobre coincide con la búsqueda.</td>
                </tr>
            </tbody>
        </table>
    </div>
    @if($personasParaAsignar->isNotEmpty())
        <datalist id="lista-personas-asignar">
            @foreach($personasParaAsignar as $p)
                <option value="{{ $p->nombre }}">{{ $p->pin ? 'PIN '.$p->pin : '' }}</option>
            @endforeach
        </datalist>
    @endif
</section>

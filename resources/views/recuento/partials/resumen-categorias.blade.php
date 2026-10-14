{{-- Resumen por categorias (solo lectura): cada sobre en colones y los totales al pie --}}
@if($resumen->cantidadSobres > 0 || $ofrendasSueltas->isNotEmpty() || $egresos->isNotEmpty())
<section class="bg-white rounded-lg shadow overflow-hidden" aria-labelledby="titulo-resumen">
    <div class="px-4 sm:px-6 py-3 border-b border-gray-200">
        <h3 id="titulo-resumen" class="text-lg font-semibold text-gray-900">Resumen por categorías</h3>
        <p class="text-xs text-gray-500">Montos en colones. Los sobres en dólares muestran debajo el monto original.</p>
    </div>
    <div class="table-scroll">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">N° sobre</th>
                    @foreach($categorias as $cat)
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-600 uppercase">{{ $cat->nombre }}</th>
                    @endforeach
                    <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Subtotal</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                @foreach($resumen->filas as $fila)
                    @php $esUsd = $fila['sobre']->moneda === 'USD'; @endphp
                    <tr>
                        <td class="px-4 py-2 text-sm font-medium text-gray-900 whitespace-nowrap">
                            #{{ $fila['sobre']->numero_sobre }}
                            @if($esUsd)
                                <span class="ml-1 text-xs bg-yellow-100 text-yellow-800 px-1.5 py-0.5 rounded-full">USD</span>
                            @endif
                        </td>
                        @foreach($categorias as $cat)
                            <td class="px-4 py-2 text-sm text-right tabular-nums {{ $fila['crc'][$cat->slug] > 0 ? 'text-gray-800' : 'text-gray-300' }}">
                                ₡{{ number_format($fila['crc'][$cat->slug], 2) }}
                                @if($esUsd && $fila['usd'][$cat->slug] > 0)
                                    <span class="block text-xs text-yellow-700">${{ number_format($fila['usd'][$cat->slug], 2) }}</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-2 text-sm text-right font-semibold tabular-nums text-blue-700">₡{{ number_format($fila['subtotal_crc'], 2) }}</td>
                    </tr>
                @endforeach
                @foreach($ofrendasSueltas as $ofrenda)
                    <tr class="bg-green-50/40">
                        <td class="px-4 py-2 text-sm font-medium text-green-800 whitespace-nowrap">Dinero suelto</td>
                        @foreach($categorias as $cat)
                            <td class="px-4 py-2 text-sm text-right text-gray-300">—</td>
                        @endforeach
                        <td class="px-4 py-2 text-sm text-right font-semibold tabular-nums text-green-700">₡{{ number_format($ofrenda->monto_crc, 2) }}</td>
                    </tr>
                @endforeach
                @foreach($egresos as $egreso)
                    <tr class="bg-red-50/40">
                        <td class="px-4 py-2 text-sm font-medium text-red-800 whitespace-nowrap">Egreso</td>
                        @foreach($categorias as $cat)
                            <td class="px-4 py-2 text-sm text-right text-gray-300">—</td>
                        @endforeach
                        <td class="px-4 py-2 text-sm text-right font-semibold tabular-nums text-red-700">−₡{{ number_format($egreso->monto_crc, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="bg-blue-50 border-t-2 border-blue-200">
                    <th scope="row" class="px-4 py-3 text-left text-sm font-bold text-gray-900">TOTALES</th>
                    @foreach($categorias as $cat)
                        <td class="px-4 py-3 text-sm text-right font-bold tabular-nums text-blue-800">₡{{ number_format($resumen->porCategoria[$cat->slug] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="px-4 py-3 text-right font-bold tabular-nums text-green-800 text-base">₡{{ number_format($resumen->total(), 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
@endif

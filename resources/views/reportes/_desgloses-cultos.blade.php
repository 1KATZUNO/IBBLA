{{-- Desglose del rango por categoria y por metodo de pago (Reporte de cultos). Variable: $reporte (ReporteCultos) --}}
@php
    $formato = fn (float $monto) => \App\Services\Reportes\Dinero::formato($monto);
    $t = $reporte->totales();
@endphp
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <h2 class="px-4 py-3 border-b border-gray-200 text-base font-semibold text-gray-900">Ingresos por categoría</h2>
        <table class="min-w-full divide-y divide-gray-200 text-sm" data-testid="por-categoria">
            <tbody class="divide-y divide-gray-100">
                @foreach($reporte->porCategoria() as $linea)
                <tr>
                    <td class="px-4 py-2">{{ $linea['nombre'] }}</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">{{ $formato($linea['monto']) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-100 font-semibold">
                <tr>
                    <td class="px-4 py-3">Total de ingresos</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ $formato($t['ingresos']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <h2 class="px-4 py-3 border-b border-gray-200 text-base font-semibold text-gray-900">Ingresos por método de pago</h2>
        <table class="min-w-full divide-y divide-gray-200 text-sm" data-testid="por-metodo">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Método</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sobres</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($reporte->porMetodo() as $linea)
                <tr>
                    <td class="px-4 py-2">{{ $linea['nombre'] }}</td>
                    <td class="px-4 py-2 text-right">{{ $linea['cantidad'] === null ? '' : number_format($linea['cantidad']) }}</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">{{ $formato($linea['monto']) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-100 font-semibold">
                <tr>
                    <td class="px-4 py-3">Total de ingresos</td>
                    <td class="px-4 py-3 text-right">{{ number_format($t['sobres']) }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ $formato($t['ingresos']) }}</td>
                </tr>
            </tfoot>
        </table>
        @if($t['egresos'] > 0)
            <p class="px-4 py-3 text-sm text-gray-600 border-t border-gray-200">Egresos del rango: {{ $formato($t['egresos']) }} · Neto: {{ $formato($t['total']) }}</p>
        @endif
    </div>
</div>

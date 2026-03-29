@extends('layouts.admin')

@section('title', 'IBBSC - Editar Sobre')
@section('page-title', 'Editar Sobre #' . $sobre->numero_sobre)

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('recuento.update', $sobre) }}" method="POST" id="sobreForm">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Culto</label>
                <p class="text-lg font-semibold text-gray-900">
                    {{ $sobre->culto->fecha->format('d/m/Y') }} - {{ ucfirst($sobre->culto->tipo_culto) }}
                </p>
            </div>

            <div class="mb-6">
                <label for="persona_id" class="block text-sm font-medium text-gray-700 mb-2">Persona (opcional)</label>
                <select name="persona_id" id="persona_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">-- Anónimo --</option>
                    @foreach($personas as $persona)
                        <option value="{{ $persona->id }}" {{ old('persona_id', $sobre->persona_id) == $persona->id ? 'selected' : '' }}>
                            {{ $persona->nombre }}{{ $persona->pin ? ' (PIN: '.$persona->pin.')' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('persona_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="metodo_pago" class="block text-sm font-medium text-gray-700 mb-2">Método de Pago *</label>
                <select name="metodo_pago" id="metodo_pago" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    <option value="efectivo" {{ old('metodo_pago', $sobre->metodo_pago) == 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                    <option value="transferencia" {{ old('metodo_pago', $sobre->metodo_pago) == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                </select>
                @error('metodo_pago')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <div id="comprobanteWrapper" class="mt-3 hidden">
                    <label for="comprobante_numero" class="block text-sm font-medium text-gray-700 mb-2">N° Comprobante (Transferencia)</label>
                    <input type="text" name="comprobante_numero" id="comprobante_numero" value="{{ old('comprobante_numero', $sobre->comprobante_numero) }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Ej: 1234567890">
                    @error('comprobante_numero')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="space-y-4 mb-6">
                <div class="flex items-center gap-4 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Detalles del Sobre</h3>
                    <div class="flex items-center gap-2 ml-auto">
                        <label class="text-sm font-medium text-gray-700">Moneda:</label>
                        <select name="moneda" id="moneda" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="CRC" {{ ($sobre->moneda ?? 'CRC') === 'CRC' ? 'selected' : '' }}>₡ Colones</option>
                            <option value="USD" {{ ($sobre->moneda ?? 'CRC') === 'USD' ? 'selected' : '' }}>$ Dólares</option>
                        </select>
                        <span id="tipoCambioBadge" class="{{ ($sobre->moneda ?? 'CRC') === 'USD' ? '' : 'hidden' }} text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full font-medium">
                            T/C: <span id="tipoCambioValor">{{ $sobre->tipo_cambio_venta ? '₡'.number_format((float)$sobre->tipo_cambio_venta, 2) : '--' }}</span>
                        </span>
                    </div>
                </div>

                @php
                    $detallesPorCategoria = $sobre->detalles->keyBy('categoria');
                @endphp

                @foreach($categorias as $cat)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                    <label for="detalle_{{ $cat->slug }}" class="block text-sm font-medium text-gray-700">
                        {{ $cat->nombre }}
                    </label>
                    <div class="relative">
                        <input type="hidden" name="detalles[{{ $loop->index }}][categoria]" value="{{ $cat->slug }}">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 simbolo-moneda">{{ ($sobre->moneda ?? 'CRC') === 'USD' ? '$' : '₡' }}</span>
                        <input type="number"
                               name="detalles[{{ $loop->index }}][monto]"
                               id="detalle_{{ $cat->slug }}"
                               min="0"
                               step="0.01"
                               value="{{ old('detalles.' . $loop->index . '.monto', $detallesPorCategoria->get($cat->slug)->monto ?? 0) }}"
                               class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 detalle-monto">
                    </div>
                </div>
                @endforeach
            </div>

            <div class="bg-blue-50 rounded-lg p-4 mb-6">
                <div class="flex justify-between items-center">
                    <span class="text-lg font-semibold text-gray-700">Total Declarado:</span>
                    <span id="totalDeclarado" class="text-2xl font-bold text-blue-600">₡0.00</span>
                </div>
                <div id="totalConvertido" class="{{ ($sobre->moneda ?? 'CRC') === 'USD' ? '' : 'hidden' }} mt-2 flex justify-between items-center text-sm text-gray-500">
                    <span>Equivalente en colones:</span>
                    <span class="font-semibold text-green-700" id="totalConvertidoValor">₡0.00</span>
                </div>
            </div>

            <div class="mb-6">
                <label for="notas" class="block text-sm font-medium text-gray-700 mb-2">Notas</label>
                <textarea name="notas" id="notas" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notas', $sobre->notas) }}</textarea>
                @error('notas')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('recuento.index', ['culto_id' => $sobre->culto_id]) }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Actualizar Sobre
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.detalle-monto');
        const totalDisplay = document.getElementById('totalDeclarado');
        const metodoPagoSelect = document.getElementById('metodo_pago');
        const comprobanteWrapper = document.getElementById('comprobanteWrapper');
        const comprobanteInput = document.getElementById('comprobante_numero');
        const monedaSelect = document.getElementById('moneda');
        const simbolos = document.querySelectorAll('.simbolo-moneda');
        const tipoCambioBadge = document.getElementById('tipoCambioBadge');
        const tipoCambioValor = document.getElementById('tipoCambioValor');
        const totalConvertido = document.getElementById('totalConvertido');
        const totalConvertidoValor = document.getElementById('totalConvertidoValor');

        let tipoCambioVenta = {{ $sobre->tipo_cambio_venta ?? 0 }};

        // Obtener tipo de cambio actual
        fetch('{{ route("api.tipo-cambio") }}')
            .then(r => r.json())
            .then(data => {
                if (data.disponible) {
                    tipoCambioVenta = data.venta;
                    tipoCambioValor.textContent = '₡' + data.venta.toFixed(2);
                }
            })
            .catch(() => {});

        function getSimboloMoneda() {
            return monedaSelect.value === 'USD' ? '$' : '₡';
        }

        function calcularTotal() {
            let total = 0;
            inputs.forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            const simbolo = getSimboloMoneda();
            totalDisplay.textContent = simbolo + total.toFixed(2);

            if (monedaSelect.value === 'USD' && tipoCambioVenta > 0) {
                totalConvertido.classList.remove('hidden');
                totalConvertidoValor.textContent = '₡' + (total * tipoCambioVenta).toFixed(2);
            } else {
                totalConvertido.classList.add('hidden');
            }
        }

        monedaSelect.addEventListener('change', function() {
            const simbolo = getSimboloMoneda();
            simbolos.forEach(s => s.textContent = simbolo);
            if (this.value === 'USD') {
                tipoCambioBadge.classList.remove('hidden');
            } else {
                tipoCambioBadge.classList.add('hidden');
            }
            calcularTotal();
        });

        inputs.forEach(input => {
            input.addEventListener('input', calcularTotal);
            input.addEventListener('focus', function() { this.select(); });
        });

        calcularTotal();

        function toggleComprobante() {
            if (metodoPagoSelect.value === 'transferencia') {
                comprobanteWrapper.classList.remove('hidden');
                comprobanteInput.required = true;
            } else {
                comprobanteWrapper.classList.add('hidden');
                comprobanteInput.required = false;
            }
        }
        metodoPagoSelect.addEventListener('change', toggleComprobante);
        toggleComprobante();
    });
</script>
@endpush
@endsection

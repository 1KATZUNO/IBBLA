@extends('layouts.admin')

@section('title', 'IBBSC - Agregar Sobre')
@section('page-title', 'Registrar Nuevo Sobre')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('recuento.store') }}" method="POST" id="sobreForm">
            @csrf

            <input type="hidden" name="culto_id" value="{{ $culto ? $culto->id : request('culto_id') }}">

            @if($culto)
            <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                <p class="text-sm text-gray-600">Culto seleccionado:</p>
                <p class="text-lg font-semibold text-blue-900">
                    {{ $culto->fecha->format('d/m/Y') }} - {{ ucfirst($culto->tipo_culto) }}
                </p>
            </div>
            @endif

            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="persona_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Persona (Opcional)
                        </label>
                        <div class="space-y-2">
                            <div class="relative">
                                <input type="text" id="buscarPersona" placeholder="Buscar persona..." 
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <div id="resultadosBusqueda" class="hidden absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                </div>
                            </div>
                            <select name="persona_id" id="persona_id" 
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Anónimo</option>
                                @foreach($personas as $persona)
                                    <option value="{{ $persona->id }}" {{ old('persona_id') == $persona->id ? 'selected' : '' }}>
                                        {{ $persona->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" id="btnAgregarRapida" class="w-full px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm">
                                + Agregar Persona Rápida
                            </button>
                        </div>
                        @error('persona_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="metodo_pago" class="block text-sm font-medium text-gray-700 mb-2">
                            Método de Pago *
                        </label>
                        <select name="metodo_pago" id="metodo_pago" 
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <option value="efectivo" {{ old('metodo_pago') == 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                            <option value="transferencia" {{ old('metodo_pago') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                        </select>
                        @error('metodo_pago')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <div id="comprobanteWrapper" class="mt-3 hidden">
                            <label for="comprobante_numero" class="block text-sm font-medium text-gray-700 mb-2">N° Comprobante (Transferencia)</label>
                            <input type="text" name="comprobante_numero" id="comprobante_numero" value="{{ old('comprobante_numero') }}" 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Ej: 1234567890">
                            @error('comprobante_numero')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="border-t pt-6">
                    <h3 class="text-lg font-semibold mb-4">Detalles del Sobre</h3>
                    
                    <div class="space-y-3" id="detallesContainer">
                        <!-- Categorías fijas -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="diezmo" class="block text-sm font-medium text-gray-700 mb-2">Diezmo</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">₡</span>
                                    <input type="number" name="detalles[0][monto]" id="diezmo" step="0.01" min="0" value="0"
                                           class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 detalle-monto">
                                    <input type="hidden" name="detalles[0][categoria]" value="diezmo">
                                </div>
                            </div>

                            <div>
                                <label for="ofrenda_especial" class="block text-sm font-medium text-gray-700 mb-2">Ofrenda Especial</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">₡</span>
                                    <input type="number" name="detalles[1][monto]" id="ofrenda_especial" step="0.01" min="0" value="0"
                                           class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 detalle-monto">
                                    <input type="hidden" name="detalles[1][categoria]" value="ofrenda_especial">
                                </div>
                            </div>

                            <div>
                                <label for="misiones" class="block text-sm font-medium text-gray-700 mb-2">Misiones</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">₡</span>
                                    <input type="number" name="detalles[2][monto]" id="misiones" step="0.01" min="0" value="0"
                                           class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 detalle-monto">
                                    <input type="hidden" name="detalles[2][categoria]" value="misiones">
                                </div>
                            </div>

                            <div>
                                <label for="seminario" class="block text-sm font-medium text-gray-700 mb-2">Seminario</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">₡</span>
                                    <input type="number" name="detalles[3][monto]" id="seminario" step="0.01" min="0" value="0"
                                           class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 detalle-monto">
                                    <input type="hidden" name="detalles[3][categoria]" value="seminario">
                                </div>
                            </div>

                            <div>
                                <label for="campa" class="block text-sm font-medium text-gray-700 mb-2">Campamento</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">₡</span>
                                    <input type="number" name="detalles[4][monto]" id="campa" step="0.01" min="0" value="0"
                                           class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 detalle-monto">
                                    <input type="hidden" name="detalles[4][categoria]" value="campa">
                                </div>
                            </div>

                            <div>
                                <label for="prestamo" class="block text-sm font-medium text-gray-700 mb-2">Préstamo</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">₡</span>
                                    <input type="number" name="detalles[5][monto]" id="prestamo" step="0.01" min="0" value="0"
                                           class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 detalle-monto">
                                    <input type="hidden" name="detalles[5][categoria]" value="prestamo">
                                </div>
                            </div>

                            <div>
                                <label for="construccion" class="block text-sm font-medium text-gray-700 mb-2">Construcción</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">₡</span>
                                    <input type="number" name="detalles[6][monto]" id="construccion" step="0.01" min="0" value="0"
                                           class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 detalle-monto">
                                    <input type="hidden" name="detalles[6][categoria]" value="construccion">
                                </div>
                            </div>

                            <div>
                                <label for="micro" class="block text-sm font-medium text-gray-700 mb-2">Micro</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">₡</span>
                                    <input type="number" name="detalles[7][monto]" id="micro" step="0.01" min="0" value="0"
                                           class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 detalle-monto">
                                    <input type="hidden" name="detalles[7][categoria]" value="micro">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-medium text-gray-700">Total Declarado:</span>
                            <span class="text-2xl font-bold text-blue-600" id="totalDeclarado">₡0.00</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="notas" class="block text-sm font-medium text-gray-700 mb-2">Notas (Opcional)</label>
                    <textarea name="notas" id="notas" rows="3" 
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notas') }}</textarea>
                    @error('notas')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('recuento.index', ['culto_id' => $culto ? $culto->id : '']) }}" 
                   class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Guardar Sobre
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.detalle-monto');
        const totalElement = document.getElementById('totalDeclarado');
        const buscarInput = document.getElementById('buscarPersona');
        const resultadosDiv = document.getElementById('resultadosBusqueda');
        const personaSelect = document.getElementById('persona_id');
        const btnAgregarRapida = document.getElementById('btnAgregarRapida');
        const metodoPagoSelect = document.getElementById('metodo_pago');
        const comprobanteWrapper = document.getElementById('comprobanteWrapper');
        const comprobanteInput = document.getElementById('comprobante_numero');

        function calcularTotal() {
            let total = 0;
            inputs.forEach(input => {
                const valor = parseFloat(input.value) || 0;
                total += valor;
            });
            totalElement.textContent = '₡' + total.toFixed(2);
        }

        inputs.forEach(input => {
            input.addEventListener('input', calcularTotal);
            
            // Seleccionar todo al hacer focus (para borrar el 0 fácilmente)
            input.addEventListener('focus', function() {
                this.select();
            });
        });

        calcularTotal();

        function toggleComprobante() {
            if (metodoPagoSelect.value === 'transferencia') {
                comprobanteWrapper.classList.remove('hidden');
                comprobanteInput.required = true;
            } else {
                comprobanteWrapper.classList.add('hidden');
                comprobanteInput.required = false;
                comprobanteInput.value = '';
            }
        }
        metodoPagoSelect.addEventListener('change', toggleComprobante);
        // Initialize on load
        toggleComprobante();

        // Búsqueda de personas
        buscarInput.addEventListener('input', function() {
            const busqueda = this.value.toLowerCase();
            if (busqueda.length < 2) {
                resultadosDiv.classList.add('hidden');
                return;
            }

            const opciones = Array.from(personaSelect.options).slice(1);
            const resultados = opciones.filter(option => 
                option.text.toLowerCase().includes(busqueda)
            );

            if (resultados.length > 0) {
                resultadosDiv.innerHTML = resultados.map(option => `
                    <div class="px-4 py-2 hover:bg-blue-50 cursor-pointer resultado-item" data-id="${option.value}">
                        ${option.text}
                    </div>
                `).join('');
                resultadosDiv.classList.remove('hidden');

                document.querySelectorAll('.resultado-item').forEach(item => {
                    item.addEventListener('click', function() {
                        personaSelect.value = this.dataset.id;
                        buscarInput.value = this.textContent.trim();
                        resultadosDiv.classList.add('hidden');
                    });
                });
            } else {
                resultadosDiv.innerHTML = '<div class="px-4 py-2 text-gray-500">No se encontraron resultados</div>';
                resultadosDiv.classList.remove('hidden');
            }
        });

        document.addEventListener('click', function(e) {
            if (!buscarInput.contains(e.target) && !resultadosDiv.contains(e.target)) {
                resultadosDiv.classList.add('hidden');
            }
        });

        // Agregar persona rápida
        btnAgregarRapida.addEventListener('click', function() {
            // Crear modal personalizado
            const modalHTML = `
                <div id="quickAddModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 animate-fadeIn">
                    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all animate-slideIn">
                        <div class="bg-gradient-to-r from-green-600 to-green-700 rounded-t-2xl px-6 py-4">
                            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                </svg>
                                Agregar Persona Rápida
                            </h3>
                        </div>
                        <div class="p-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Nombre completo de la persona
                            </label>
                            <input type="text" id="quickAddInput" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all"
                                   placeholder="Ej: Juan Pérez Gómez"
                                   autofocus>
                            <p class="mt-2 text-xs text-gray-500">
                                Se creará como persona activa sin usuario asociado
                            </p>
                        </div>
                        <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex gap-3 justify-end">
                            <button onclick="document.getElementById('quickAddModal').remove()" 
                                    class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium">
                                Cancelar
                            </button>
                            <button id="confirmAddBtn" 
                                    class="px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Agregar
                            </button>
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes slideIn {
                        from { transform: translateY(-20px) scale(0.95); opacity: 0; }
                        to { transform: translateY(0) scale(1); opacity: 1; }
                    }
                    .animate-fadeIn { animation: fadeIn 0.2s ease-out; }
                    .animate-slideIn { animation: slideIn 0.3s ease-out; }
                </style>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            const input = document.getElementById('quickAddInput');
            const confirmBtn = document.getElementById('confirmAddBtn');
            const modal = document.getElementById('quickAddModal');
            
            // Focus en el input
            setTimeout(() => input.focus(), 100);
            
            // Cerrar con ESC
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    modal.remove();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
            
            // Confirmar con Enter
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && input.value.trim()) {
                    confirmBtn.click();
                }
            });
            
            // Acción de confirmar
            confirmBtn.addEventListener('click', function() {
                const nombre = input.value.trim();
                if (!nombre) {
                    input.classList.add('border-red-500');
                    input.focus();
                    return;
                }
                
                // Deshabilitar botón mientras procesa
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = `
                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Agregando...
                `;
                
                fetch('{{ route("personas.quick-store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ nombre: nombre })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const option = new Option(data.persona.nombre, data.persona.id, true, true);
                        personaSelect.add(option);
                        personaSelect.value = data.persona.id;
                        buscarInput.value = data.persona.nombre;
                        
                        // Mostrar mensaje de éxito
                        modal.remove();
                        const successMsg = document.createElement('div');
                        successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2 animate-slideIn';
                        successMsg.innerHTML = `
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>¡Persona agregada exitosamente!</span>
                        `;
                        document.body.appendChild(successMsg);
                        setTimeout(() => successMsg.remove(), 3000);
                    } else {
                        alert('Error al agregar persona');
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = 'Agregar';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al agregar persona');
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Agregar';
                });
            });
        });
    });
</script>
@endpush
@endsection

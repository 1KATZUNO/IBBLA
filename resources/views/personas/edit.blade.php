@extends('layouts.admin')

@section('title', 'IBBSC - Editar Persona')
@section('page-title', 'Editar Persona')

@section('content')
<div class="max-w-2xl mx-auto">
    <!-- Mensaje de error global -->
    @if ($errors->any())
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 rounded-lg p-4 shadow-md animate-shake">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-semibold text-red-800 mb-2">
                        Por favor, corrige los siguientes errores:
                    </h3>
                    <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('personas.update', $persona) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre Completo *</label>
                    <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $persona->nombre) }}" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    @error('nombre')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="telefono" class="block text-sm font-medium text-gray-700 mb-2">Teléfono</label>
                        <input type="text" name="telefono" id="telefono" value="{{ old('telefono', $persona->telefono) }}" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('telefono')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="correo" class="block text-sm font-medium text-gray-700 mb-2">Correo Electrónico</label>
                        <input type="email" name="correo" id="correo" value="{{ old('correo', $persona->correo) }}" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('correo')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Contraseña (solo si hay correo o se agrega uno nuevo) -->
                <div id="password-section" class="{{ $persona->correo ? '' : 'hidden' }}">
                    @if($persona->user_id)
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                        <p class="text-sm text-green-800 flex items-center">
                            <svg class="inline w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Esta persona ya tiene acceso como <strong class="ml-1">miembro</strong>
                        </p>
                    </div>
                    @else
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <p class="text-sm text-blue-800">
                            <svg class="inline w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Al agregar un correo, esta persona podrá acceder al sistema como <strong>miembro</strong> para ver su progreso.
                        </p>
                    </div>
                    @endif
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ $persona->user_id ? 'Nueva Contraseña (dejar vacío para mantener actual)' : 'Contraseña *' }}
                        </label>
                        <input type="password" name="password" id="password" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="mt-1 text-xs text-gray-500">
                            @if($persona->user_id)
                                Solo completa este campo si deseas cambiar la contraseña
                            @else
                                Mínimo 8 caracteres. La persona usará esta contraseña para acceder.
                            @endif
                        </p>
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="activo" value="1" {{ old('activo', $persona->activo) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">Activo</span>
                    </label>
                </div>

                <div>
                    <label for="notas" class="block text-sm font-medium text-gray-700 mb-2">Notas</label>
                    <textarea name="notas" id="notas" rows="3" 
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notas', $persona->notas) }}</textarea>
                    @error('notas')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Sección de Promesas -->
                <div class="border-t pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Promesas de Ofrenda</h3>
                    <p class="text-sm text-gray-600 mb-4">Configure las promesas mensuales de esta persona (excepto diezmo)</p>
                    
                    <div id="promesas-container" class="space-y-4">
                        @php
                            $categorias = ['misiones', 'micro', 'construccion', 'seminario', 'campa', 'prestamo'];
                            $promesasActuales = $persona->promesas->keyBy('categoria');
                        @endphp
                        
                        @foreach($categorias as $index => $categoria)
                        @php
                            $promesaExistente = $promesasActuales->get($categoria);
                        @endphp
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-700 mb-3 capitalize">{{ ucfirst($categoria) }}</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Monto</label>
                                    <input type="number" 
                                           name="promesas[{{$index}}][monto]" 
                                           step="0.01" 
                                           min="0" 
                                           value="{{ old('promesas.'.$index.'.monto', $promesaExistente->monto ?? 0) }}"
                                           class="w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Frecuencia</label>
                                    <select name="promesas[{{$index}}][frecuencia]" 
                                            class="w-full rounded-md border-gray-300 text-sm">
                                        <option value="semanal" {{ old('promesas.'.$index.'.frecuencia', $promesaExistente->frecuencia ?? 'mensual') == 'semanal' ? 'selected' : '' }}>Semanal</option>
                                        <option value="quincenal" {{ old('promesas.'.$index.'.frecuencia', $promesaExistente->frecuencia ?? 'mensual') == 'quincenal' ? 'selected' : '' }}>Quincenal</option>
                                        <option value="mensual" {{ old('promesas.'.$index.'.frecuencia', $promesaExistente->frecuencia ?? 'mensual') == 'mensual' ? 'selected' : '' }}>Mensual</option>
                                    </select>
                                </div>
                                <input type="hidden" name="promesas[{{$index}}][categoria]" value="{{$categoria}}">
                                <div class="flex items-end">
                                    <p class="text-xs text-gray-500">
                                        <span class="font-medium">Ejemplo:</span><br>
                                        ₡100 semanal = ~₡400-500/mes
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-between items-center">
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('modalReiniciar').classList.remove('hidden')" 
                            class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Reiniciar Compromisos
                    </button>
                    <button type="button" onclick="document.getElementById('modalLimpiar').classList.remove('hidden')" 
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Limpiar Todo
                    </button>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('personas.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Actualizar Persona
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reiniciar Compromisos -->
<div id="modalReiniciar" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Reiniciar Compromisos</h3>
                <button onclick="document.getElementById('modalReiniciar').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <p class="text-sm text-gray-600 mb-4">
                Esta acción eliminará todo el historial de compromisos <strong>anterior</strong> a la fecha seleccionada y comenzará a calcular desde cero a partir de ese mes.
            </p>
            
            <form action="{{ route('personas.reiniciar-compromisos', $persona) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="fecha_inicio" class="block text-sm font-medium text-gray-700 mb-2">
                        Fecha de inicio (mes/año) *
                    </label>
                    <input type="month" 
                           name="fecha_inicio" 
                           id="fecha_inicio" 
                           value="{{ date('Y-m') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-yellow-500 focus:ring-yellow-500" 
                           required>
                    <p class="mt-1 text-xs text-gray-500">
                        Se borrará todo el historial anterior a esta fecha
                    </p>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" 
                            onclick="document.getElementById('modalReiniciar').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700">
                        Reiniciar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Limpiar Todo -->
<div id="modalLimpiar" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-red-900">⚠️ Limpiar Todo</h3>
                <button onclick="document.getElementById('modalLimpiar').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-red-800 mb-2">
                    <strong>Esta acción NO se puede deshacer.</strong>
                </p>
                <p class="text-sm text-red-700">
                    Se eliminarán permanentemente:
                </p>
                <ul class="text-sm text-red-700 list-disc list-inside mt-2">
                    <li>Todas las promesas configuradas</li>
                    <li>Todo el historial de compromisos</li>
                    <li>Todas las deudas y saldos</li>
                </ul>
            </div>
            
            <p class="text-sm text-gray-600 mb-4">
                La persona quedará en estado "limpio" como si nunca hubiera tenido promesas.
            </p>
            
            <form action="{{ route('personas.limpiar-todo', $persona) }}" method="POST">
                @csrf
                
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               id="confirmar_limpieza" 
                               class="rounded border-gray-300 text-red-600 shadow-sm focus:border-red-500 focus:ring-red-500"
                               required>
                        <span class="ml-2 text-sm text-gray-700">
                            Confirmo que quiero eliminar todo
                        </span>
                    </label>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" 
                            onclick="document.getElementById('modalLimpiar').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Sí, Limpiar Todo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Mostrar/ocultar campo de contraseña según si hay correo
    const correoInput = document.getElementById('correo');
    const passwordSection = document.getElementById('password-section');
    const passwordInput = document.getElementById('password');
    const hasUser = {{ $persona->user_id ? 'true' : 'false' }};

    function togglePasswordField() {
        if (correoInput.value.trim() !== '') {
            passwordSection.classList.remove('hidden');
            // Solo requerido si no tiene usuario y hay correo
            if (!hasUser) {
                passwordInput.required = true;
            }
        } else {
            passwordSection.classList.add('hidden');
            passwordInput.required = false;
            passwordInput.value = '';
        }
    }

    correoInput.addEventListener('input', togglePasswordField);
    togglePasswordField(); // Verificar al cargar
</script>
@endsection

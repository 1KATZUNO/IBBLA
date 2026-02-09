@extends('layouts.admin')

@section('title', 'IBBSC - Nueva Persona')
@section('page-title', 'Registrar Nueva Persona')

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
        <form action="{{ route('personas.store') }}" method="POST">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre Completo *</label>
                    <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    @error('nombre')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="telefono" class="block text-sm font-medium text-gray-700 mb-2">Teléfono</label>
                        <input type="text" name="telefono" id="telefono" value="{{ old('telefono') }}" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('telefono')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="correo" class="block text-sm font-medium text-gray-700 mb-2">Correo Electrónico</label>
                        <input type="email" name="correo" id="correo" value="{{ old('correo') }}" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('correo')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Contraseña (solo si hay correo) -->
                <div id="password-section" class="hidden">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <p class="text-sm text-blue-800">
                            <svg class="inline w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Al agregar un correo, esta persona podrá acceder al sistema como <strong>miembro</strong> para ver su progreso y compromisos.
                        </p>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Contraseña *</label>
                        <input type="password" name="password" id="password" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="mt-1 text-xs text-gray-500">Mínimo 8 caracteres. La persona usará esta contraseña para acceder.</p>
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">Activo</span>
                    </label>
                </div>

                <div>
                    <label for="notas" class="block text-sm font-medium text-gray-700 mb-2">Notas</label>
                    <textarea name="notas" id="notas" rows="3" 
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notas') }}</textarea>
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
                        @endphp
                        
                        @foreach($categorias as $index => $categoria)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-700 mb-3 capitalize">{{ ucfirst($categoria) }}</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Monto</label>
                                    <input type="number" 
                                           name="promesas[{{$index}}][monto]" 
                                           step="0.01" 
                                           min="0" 
                                           value="{{ old('promesas.'.$index.'.monto', 0) }}"
                                           class="w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Frecuencia</label>
                                    <select name="promesas[{{$index}}][frecuencia]" 
                                            class="w-full rounded-md border-gray-300 text-sm">
                                        <option value="semanal" {{ old('promesas.'.$index.'.frecuencia') == 'semanal' ? 'selected' : '' }}>Semanal</option>
                                        <option value="quincenal" {{ old('promesas.'.$index.'.frecuencia') == 'quincenal' ? 'selected' : '' }}>Quincenal</option>
                                        <option value="mensual" {{ old('promesas.'.$index.'.frecuencia', 'mensual') == 'mensual' ? 'selected' : '' }}>Mensual</option>
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

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('personas.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Guardar Persona
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Mostrar/ocultar campo de contraseña según si hay correo
    const correoInput = document.getElementById('correo');
    const passwordSection = document.getElementById('password-section');
    const passwordInput = document.getElementById('password');

    function togglePasswordField() {
        if (correoInput.value.trim() !== '') {
            passwordSection.classList.remove('hidden');
            passwordInput.required = true;
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

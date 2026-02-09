@extends('layouts.admin')

@section('title', 'IBBSC - Nueva Clase')
@section('page-title', 'Nueva Clase de Asistencia')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('admin.clases.store') }}" method="POST">
            @csrf

            <div class="space-y-6">
                <div>
                    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre de la Clase *</label>
                    <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                           placeholder="Ej: Clase 12-15 Años" required>
                    @error('nombre')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">Este nombre aparecerá en el formulario de asistencia</p>
                </div>

                <div>
                    <label for="color" class="block text-sm font-medium text-gray-700 mb-2">Color *</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="color" id="color" value="{{ old('color', '#3b82f6') }}" 
                               class="h-10 w-20 rounded border-gray-300 shadow-sm" required>
                        <span id="colorHex" class="text-sm text-gray-600">{{ old('color', '#3b82f6') }}</span>
                    </div>
                    @error('color')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">Color de fondo del acordeón en la interfaz</p>
                </div>

                <div>
                    <label for="orden" class="block text-sm font-medium text-gray-700 mb-2">Orden de Visualización *</label>
                    <input type="number" name="orden" id="orden" min="0" value="{{ old('orden', 0) }}" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    @error('orden')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">Número menor aparece primero (después de Capilla)</p>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="tiene_maestros" id="tiene_maestros" value="1" {{ old('tiene_maestros') ? 'checked' : '' }}
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <label for="tiene_maestros" class="ml-2 text-sm text-gray-700">
                        Incluir campos para Maestros (H) y Maestras (M)
                    </label>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin.clases.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Crear Clase
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const colorInput = document.getElementById('color');
    const colorHex = document.getElementById('colorHex');
    
    colorInput.addEventListener('input', function() {
        colorHex.textContent = this.value;
    });
</script>
@endpush
@endsection

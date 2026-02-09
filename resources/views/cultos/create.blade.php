@extends('layouts.admin')

@section('title', 'IBBSC - Nuevo Culto')
@section('page-title', 'Registrar Nuevo Culto')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('cultos.store') }}" method="POST">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="fecha" class="block text-sm font-medium text-gray-700 mb-2">Fecha</label>
                    <input type="date" name="fecha" id="fecha" value="{{ old('fecha', date('Y-m-d')) }}" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    @error('fecha')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="hora" class="block text-sm font-medium text-gray-700 mb-2">Hora</label>
                    <input type="time" name="hora" id="hora" value="{{ old('hora', '10:00') }}" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    @error('hora')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="tipo_culto" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Culto</label>
                    <select name="tipo_culto" id="tipo_culto" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="domingo" {{ old('tipo_culto') == 'domingo' ? 'selected' : '' }}>Domingo AM</option>
                        <option value="domingo_pm" {{ old('tipo_culto') == 'domingo_pm' ? 'selected' : '' }}>Domingo PM</option>
                        <option value="miércoles" {{ old('tipo_culto') == 'miércoles' ? 'selected' : '' }}>Miércoles</option>
                        <option value="sábado" {{ old('tipo_culto') == 'sábado' ? 'selected' : '' }}>Sábado</option>
                        <option value="especial" {{ old('tipo_culto') == 'especial' ? 'selected' : '' }}>Especial</option>
                    </select>
                    @error('tipo_culto')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
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
                <a href="{{ route('cultos.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Guardar Culto
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

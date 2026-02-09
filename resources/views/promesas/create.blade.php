@extends('layouts.admin')

@section('title', 'IBBSC - Nueva Promesa')
@section('page-title', 'Registrar Nueva Promesa')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('promesas.store') }}" method="POST">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="persona_id" class="block text-sm font-medium text-gray-700 mb-2">Persona *</label>
                    <select name="persona_id" id="persona_id" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">-- Seleccione una persona --</option>
                        @foreach($personas as $persona)
                            <option value="{{ $persona->id }}" {{ old('persona_id') == $persona->id ? 'selected' : '' }}>
                                {{ $persona->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('persona_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="fecha_promesa" class="block text-sm font-medium text-gray-700 mb-2">Fecha de Promesa *</label>
                        <input type="date" name="fecha_promesa" id="fecha_promesa" value="{{ old('fecha_promesa', date('Y-m-d')) }}" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        @error('fecha_promesa')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="monto_total" class="block text-sm font-medium text-gray-700 mb-2">Monto Total (₡) *</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">₡</span>
                            <input type="number" name="monto_total" id="monto_total" min="0.01" step="0.01" value="{{ old('monto_total') }}" 
                                   class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        </div>
                        @error('monto_total')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción *</label>
                    <textarea name="descripcion" id="descripcion" rows="3" 
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>{{ old('descripcion') }}</textarea>
                    @error('descripcion')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="notas" class="block text-sm font-medium text-gray-700 mb-2">Notas</label>
                    <textarea name="notas" id="notas" rows="2" 
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notas') }}</textarea>
                    @error('notas')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('promesas.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Guardar Promesa
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

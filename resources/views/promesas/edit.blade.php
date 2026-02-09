@extends('layouts.admin')

@section('title', 'IBBSC - Editar Promesa')
@section('page-title', 'Editar Promesa')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('promesas.update', $promesa) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label for="persona_id" class="block text-sm font-medium text-gray-700 mb-2">Persona *</label>
                    <select name="persona_id" id="persona_id" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">-- Seleccione una persona --</option>
                        @foreach($personas as $persona)
                            <option value="{{ $persona->id }}" {{ old('persona_id', $promesa->persona_id) == $persona->id ? 'selected' : '' }}>
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
                        <input type="date" name="fecha_promesa" id="fecha_promesa" value="{{ old('fecha_promesa', $promesa->fecha_promesa->format('Y-m-d')) }}" 
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        @error('fecha_promesa')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="monto_total" class="block text-sm font-medium text-gray-700 mb-2">Monto Total (₡) *</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">₡</span>
                            <input type="number" name="monto_total" id="monto_total" min="0.01" step="0.01" value="{{ old('monto_total', $promesa->monto_total) }}" 
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
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>{{ old('descripcion', $promesa->descripcion) }}</textarea>
                    @error('descripcion')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="notas" class="block text-sm font-medium text-gray-700 mb-2">Notas</label>
                    <textarea name="notas" id="notas" rows="2" 
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notas', $promesa->notas) }}</textarea>
                    @error('notas')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @php
                    $montoRecibido = $promesa->sobreDetalles->sum('monto');
                    $porcentaje = $promesa->monto_total > 0 ? ($montoRecibido / $promesa->monto_total) * 100 : 0;
                @endphp

                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="font-medium text-gray-700">Monto Recibido:</span>
                            <span class="font-semibold text-green-600">₡{{ number_format($montoRecibido, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="font-medium text-gray-700">Monto Pendiente:</span>
                            <span class="font-semibold text-orange-600">₡{{ number_format($promesa->monto_total - $montoRecibido, 2) }}</span>
                        </div>
                        <div class="mt-2">
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-blue-600 h-2.5 rounded-full" style="width: {{ min($porcentaje, 100) }}%"></div>
                            </div>
                            <p class="text-center text-sm font-medium text-gray-700 mt-1">{{ number_format($porcentaje, 1) }}% completado</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('promesas.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Actualizar Promesa
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

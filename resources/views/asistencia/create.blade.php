@extends('layouts.admin')

@section('title', 'IBBSC - Nueva Asistencia')
@section('page-title', 'Nueva Asistencia')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('asistencia.store') }}" method="POST" id="asistenciaForm">
            @csrf

            <div class="mb-6">
                <label for="culto_id" class="block text-sm font-medium text-gray-700 mb-2">Seleccionar Culto *</label>
                <select name="culto_id" id="culto_id" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    <option value="">-- Seleccione un culto --</option>
                    @foreach($cultos as $culto)
                        <option value="{{ $culto->id }}" {{ old('culto_id', request('culto_id')) == $culto->id ? 'selected' : '' }}>
                            {{ $culto->fecha->format('d/m/Y') }} - {{ ucfirst($culto->tipo_culto) }}
                        </option>
                    @endforeach
                </select>
                @error('culto_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-6">
                <!-- Capilla -->
                <div class="border rounded-lg overflow-hidden">
                    <button type="button" onclick="toggleSection('capilla')" class="w-full px-4 py-3 bg-blue-50 hover:bg-blue-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-blue-900">Capilla</h3>
                        <svg id="capilla-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="capilla-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adultos Hombres</label>
                            <select name="chapel_adultos_hombres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('chapel_adultos_hombres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adultos Mujeres</label>
                            <select name="chapel_adultos_mujeres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('chapel_adultos_mujeres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adultos Mayores Hombres</label>
                            <select name="chapel_adultos_mayores_hombres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('chapel_adultos_mayores_hombres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adultos Mayores Mujeres</label>
                            <select name="chapel_adultos_mayores_mujeres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('chapel_adultos_mayores_mujeres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jóvenes Masculinos</label>
                            <select name="chapel_jovenes_masculinos" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('chapel_jovenes_masculinos', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jóvenes Femeninas</label>
                            <select name="chapel_jovenes_femeninas" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('chapel_jovenes_femeninas', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maestros Hombres</label>
                            <select name="chapel_maestros_hombres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('chapel_maestros_hombres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maestras Mujeres</label>
                            <select name="chapel_maestros_mujeres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('chapel_maestros_mujeres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Clase 0-1 -->
                <div class="border rounded-lg overflow-hidden">
                    <button type="button" onclick="toggleSection('clase01')" class="w-full px-4 py-3 bg-green-50 hover:bg-green-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-green-900">Clase 0-1 Años</h3>
                        <svg id="clase01-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="clase01-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niños</label>
                            <select name="clase_0_1_hombres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_0_1_hombres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niñas</label>
                            <select name="clase_0_1_mujeres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_0_1_mujeres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maestros (H)</label>
                            <select name="clase_0_1_maestros_hombres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_0_1_maestros_hombres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maestras (M)</label>
                            <select name="clase_0_1_maestros_mujeres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_0_1_maestros_mujeres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Clase 2-6 -->
                <div class="border rounded-lg overflow-hidden">
                    <button type="button" onclick="toggleSection('clase26')" class="w-full px-4 py-3 bg-yellow-50 hover:bg-yellow-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-yellow-900">Clase 2-6 Años</h3>
                        <svg id="clase26-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="clase26-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niños</label>
                            <select name="clase_2_6_hombres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_2_6_hombres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niñas</label>
                            <select name="clase_2_6_mujeres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_2_6_mujeres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maestros (H)</label>
                            <select name="clase_2_6_maestros_hombres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_2_6_maestros_hombres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maestras (M)</label>
                            <select name="clase_2_6_maestros_mujeres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_2_6_maestros_mujeres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Clase 7-8 -->
                <div class="border rounded-lg overflow-hidden">
                    <button type="button" onclick="toggleSection('clase78')" class="w-full px-4 py-3 bg-purple-50 hover:bg-purple-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-purple-900">Clase 7-8 Años</h3>
                        <svg id="clase78-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="clase78-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niños</label>
                            <select name="clase_7_8_hombres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_7_8_hombres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niñas</label>
                            <select name="clase_7_8_mujeres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_7_8_mujeres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maestros (H)</label>
                            <select name="clase_7_8_maestros_hombres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_7_8_maestros_hombres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maestras (M)</label>
                            <select name="clase_7_8_maestros_mujeres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_7_8_maestros_mujeres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Clase 9-11 -->
                <div class="border rounded-lg overflow-hidden">
                    <button type="button" onclick="toggleSection('clase911')" class="w-full px-4 py-3 bg-red-50 hover:bg-red-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-red-900">Clase 9-11 Años</h3>
                        <svg id="clase911-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="clase911-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niños</label>
                            <select name="clase_9_11_hombres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_9_11_hombres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niñas</label>
                            <select name="clase_9_11_mujeres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_9_11_mujeres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maestros (H)</label>
                            <select name="clase_9_11_maestros_hombres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_9_11_maestros_hombres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maestras (M)</label>
                            <select name="clase_9_11_maestros_mujeres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('clase_9_11_maestros_mujeres', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Salvos -->
                <div class="border rounded-lg overflow-hidden border-green-300">
                    <button type="button" onclick="toggleSection('salvos')" class="w-full px-4 py-3 bg-green-50 hover:bg-green-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-green-900">✝️ Salvos</h3>
                        <svg id="salvos-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="salvos-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Hombre</label>
                            <select name="salvos_adulto_hombre" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('salvos_adulto_hombre', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Mujer</label>
                            <select name="salvos_adulto_mujer" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('salvos_adulto_mujer', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Hombre</label>
                            <select name="salvos_joven_hombre" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('salvos_joven_hombre', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Mujer</label>
                            <select name="salvos_joven_mujer" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('salvos_joven_mujer', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niño</label>
                            <select name="salvos_nino" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('salvos_nino', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niña</label>
                            <select name="salvos_nina" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('salvos_nina', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Bautismos -->
                <div class="border rounded-lg overflow-hidden border-blue-300">
                    <button type="button" onclick="toggleSection('bautismos')" class="w-full px-4 py-3 bg-blue-50 hover:bg-blue-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-blue-900">💧 Bautismos</h3>
                        <svg id="bautismos-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="bautismos-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Hombre</label>
                            <select name="bautismos_adulto_hombre" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('bautismos_adulto_hombre', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Mujer</label>
                            <select name="bautismos_adulto_mujer" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('bautismos_adulto_mujer', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Hombre</label>
                            <select name="bautismos_joven_hombre" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('bautismos_joven_hombre', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Mujer</label>
                            <select name="bautismos_joven_mujer" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('bautismos_joven_mujer', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niño</label>
                            <select name="bautismos_nino" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('bautismos_nino', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niña</label>
                            <select name="bautismos_nina" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('bautismos_nina', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Visitas -->
                <div class="border rounded-lg overflow-hidden border-purple-300">
                    <button type="button" onclick="toggleSection('visitas')" class="w-full px-4 py-3 bg-purple-50 hover:bg-purple-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-purple-900">👥 Visitas</h3>
                        <svg id="visitas-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="visitas-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Hombre</label>
                            <select name="visitas_adulto_hombre" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('visitas_adulto_hombre', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Mujer</label>
                            <select name="visitas_adulto_mujer" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('visitas_adulto_mujer', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Hombre</label>
                            <select name="visitas_joven_hombre" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('visitas_joven_hombre', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Mujer</label>
                            <select name="visitas_joven_mujer" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('visitas_joven_mujer', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niño</label>
                            <select name="visitas_nino" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('visitas_nino', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niña</label>
                            <select name="visitas_nina" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('visitas_nina', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Total Asistencia -->
                <div class="bg-blue-50 rounded-lg p-6">
                    <div class="flex justify-between items-center">
                        <label for="total_asistencia" class="text-lg font-semibold text-gray-700">Total Asistencia *</label>
                        <input type="number" name="total_asistencia" id="total_asistencia" step="1" min="0" value="{{ old('total_asistencia', 0) }}" 
                               class="w-32 text-2xl font-bold text-center rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required readonly>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('asistencia.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Guardar Asistencia
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function toggleSection(sectionId) {
        const content = document.getElementById(sectionId + '-content');
        const icon = document.getElementById(sectionId + '-icon');
        
        content.classList.toggle('hidden');
        icon.classList.toggle('rotate-180');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.asistencia-input');
        const totalInput = document.getElementById('total_asistencia');
        const form = document.querySelector('form');

        function calcularTotal() {
            let total = 0;
            inputs.forEach(input => {
                total += parseInt(input.value) || 0;
            });
            totalInput.value = total;
            validarCongruencia();
        }

        function validarCongruencia() {
            const categorias = [
                { nombre: 'Adultos Hombres', asistencia: ['chapel_adultos_hombres', 'clase_adultos_hombres'], salvos: 'salvos_adulto_hombre', bautismos: 'bautismos_adulto_hombre', visitas: 'visitas_adulto_hombre' },
                { nombre: 'Adultos Mujeres', asistencia: ['chapel_adultos_mujeres', 'clase_adultos_mujeres'], salvos: 'salvos_adulto_mujer', bautismos: 'bautismos_adulto_mujer', visitas: 'visitas_adulto_mujer' },
                { nombre: 'Jóvenes Hombres', asistencia: ['chapel_jovenes_masculinos', 'clase_jovenes_masculinos'], salvos: 'salvos_joven_hombre', bautismos: 'bautismos_joven_hombre', visitas: 'visitas_joven_hombre' },
                { nombre: 'Jóvenes Mujeres', asistencia: ['chapel_jovenes_femeninas', 'clase_jovenes_femeninas'], salvos: 'salvos_joven_mujer', bautismos: 'bautismos_joven_mujer', visitas: 'visitas_joven_mujer' },
                { nombre: 'Niños', asistencia: ['chapel_ninos', 'clase_ninos_7a8', 'clase_ninos_9a12'], salvos: 'salvos_nino', bautismos: 'bautismos_nino', visitas: 'visitas_nino' },
                { nombre: 'Niñas', asistencia: ['chapel_ninas', 'clase_ninas_7a8', 'clase_ninas_9a12', 'clase_ninas_2a6'], salvos: 'salvos_nina', bautismos: 'bautismos_nina', visitas: 'visitas_nina' }
            ];

            let hayErrores = false;
            let mensajes = [];

            categorias.forEach(cat => {
                // Calcular total de personas en esta categoría
                let totalPersonas = 0;
                cat.asistencia.forEach(campo => {
                    const select = document.querySelector(`[name="${campo}"]`);
                    if (select) {
                        totalPersonas += parseInt(select.value) || 0;
                    }
                });

                // Obtener salvos, bautismos y visitas
                const salvos = parseInt(document.querySelector(`[name="${cat.salvos}"]`)?.value) || 0;
                const bautismos = parseInt(document.querySelector(`[name="${cat.bautismos}"]`)?.value) || 0;
                const visitas = parseInt(document.querySelector(`[name="${cat.visitas}"]`)?.value) || 0;

                // Validar que no excedan el total
                if (salvos > totalPersonas) {
                    hayErrores = true;
                    mensajes.push(`❌ ${cat.nombre}: ${salvos} salvos pero solo hay ${totalPersonas} personas`);
                    document.querySelector(`[name="${cat.salvos}"]`)?.classList.add('border-red-500', 'bg-red-50');
                } else {
                    document.querySelector(`[name="${cat.salvos}"]`)?.classList.remove('border-red-500', 'bg-red-50');
                }

                if (bautismos > totalPersonas) {
                    hayErrores = true;
                    mensajes.push(`❌ ${cat.nombre}: ${bautismos} bautismos pero solo hay ${totalPersonas} personas`);
                    document.querySelector(`[name="${cat.bautismos}"]`)?.classList.add('border-red-500', 'bg-red-50');
                } else {
                    document.querySelector(`[name="${cat.bautismos}"]`)?.classList.remove('border-red-500', 'bg-red-50');
                }

                if (visitas > totalPersonas) {
                    hayErrores = true;
                    mensajes.push(`❌ ${cat.nombre}: ${visitas} visitas pero solo hay ${totalPersonas} personas`);
                    document.querySelector(`[name="${cat.visitas}"]`)?.classList.add('border-red-500', 'bg-red-50');
                } else {
                    document.querySelector(`[name="${cat.visitas}"]`)?.classList.remove('border-red-500', 'bg-red-50');
                }
            });

            // Mostrar/ocultar mensaje de error
            let alertDiv = document.getElementById('alerta-congruencia');
            if (!alertDiv) {
                alertDiv = document.createElement('div');
                alertDiv.id = 'alerta-congruencia';
                alertDiv.className = 'fixed bottom-4 right-4 max-w-md z-50 transition-all duration-300';
                document.body.appendChild(alertDiv);
            }

            if (hayErrores) {
                alertDiv.innerHTML = `
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-lg">
                        <div class="flex items-start">
                            <svg class="w-6 h-6 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <p class="font-bold mb-2">⚠️ Datos Incongruentes</p>
                                <ul class="text-sm space-y-1">
                                    ${mensajes.map(m => `<li>${m}</li>`).join('')}
                                </ul>
                            </div>
                        </div>
                    </div>
                `;
                alertDiv.classList.remove('hidden');
            } else {
                alertDiv.classList.add('hidden');
            }

            return !hayErrores;
        }

        // Validar antes de enviar el formulario
        form.addEventListener('submit', function(e) {
            if (!validarCongruencia()) {
                e.preventDefault();
                alert('⚠️ Por favor corrige las incongruencias antes de guardar.\n\nNo puede haber más salvos, bautismos o visitas que personas registradas en cada categoría.');
                // Scroll al primer error
                const primerError = document.querySelector('.border-red-500');
                if (primerError) {
                    primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    primerError.focus();
                }
            }
        });

        inputs.forEach(input => {
            input.addEventListener('change', calcularTotal);
        });

        calcularTotal();
    });
</script>
@endpush
@endsection

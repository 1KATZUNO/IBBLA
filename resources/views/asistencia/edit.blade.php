@extends('layouts.admin')

@section('title', 'IBBSC - Editar Asistencia')
@section('page-title', 'Editar Registro de Asistencia')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('asistencia.update', $asistencia) }}" method="POST" id="asistenciaForm">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Culto</label>
                <p class="text-lg font-semibold text-gray-900">
                    {{ $asistencia->culto->fecha->format('d/m/Y') }} - {{ ucfirst($asistencia->culto->tipo_culto) }}
                </p>
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
                                    <option value="{{ $i }}" {{ old('chapel_adultos_hombres', $asistencia->chapel_adultos_hombres) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adultos Mujeres</label>
                            <select name="chapel_adultos_mujeres" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('chapel_adultos_mujeres', $asistencia->chapel_adultos_mujeres) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jovenes Masculinos</label>
                            <select name="chapel_jovenes_masculinos" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('chapel_jovenes_masculinos', $asistencia->chapel_jovenes_masculinos) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jovenes Femeninas</label>
                            <select name="chapel_jovenes_femeninas" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('chapel_jovenes_femeninas', $asistencia->chapel_jovenes_femeninas) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Clases Dinamicas -->
                @foreach($clases as $clase)
                @php $detalle = $asistencia->detallesClases->firstWhere('clase_asistencia_id', $clase->id); @endphp
                <div class="border rounded-lg overflow-hidden">
                    <button type="button" onclick="toggleSection('clase-{{ $clase->id }}')" class="w-full px-4 py-3 hover:opacity-80 flex justify-between items-center" style="background-color: {{ $clase->color }}15;">
                        <h3 class="text-lg font-semibold text-gray-900">{{ $clase->nombre }}</h3>
                        <svg id="clase-{{ $clase->id }}-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="clase-{{ $clase->id }}-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Hombres</label>
                            <select name="clase[{{ $clase->id }}][hombres]" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old("clase.{$clase->id}.hombres", $detalle->hombres ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Mujeres</label>
                            <select name="clase[{{ $clase->id }}][mujeres]" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 asistencia-input" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old("clase.{$clase->id}.mujeres", $detalle->mujeres ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                        @if($clase->tiene_maestros)
                        <div class="border-t pt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maestros que dieron clase <span class="text-gray-400 font-normal" id="maestros-count-{{ $clase->id }}">(0 seleccionados)</span></label>
                            @php
                                $maestrosDeClase = $maestrosPorClase[$clase->id] ?? collect();
                                $maestrosSeleccionados = old("clase.{$clase->id}.maestros_ids", $detalle->maestros_ids ?? []) ?? [];
                            @endphp
                            @if($maestrosDeClase->count() > 0)
                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                @foreach($maestrosDeClase as $maestro)
                                <label class="flex items-center p-2 rounded hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" name="clase[{{ $clase->id }}][maestros_ids][]" value="{{ $maestro->id }}"
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 maestro-check maestro-check-{{ $clase->id }}"
                                           {{ in_array($maestro->id, $maestrosSeleccionados) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-700">{{ $maestro->nombre }}</span>
                                </label>
                                @endforeach
                            </div>
                            @else
                            <p class="text-sm text-gray-400 italic">No hay maestros asignados a esta clase. Asignalos en Gestionar Personas.</p>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach

                <!-- Salvos -->
                <div class="border rounded-lg overflow-hidden border-green-300">
                    <button type="button" onclick="toggleSection('salvos')" class="w-full px-4 py-3 bg-green-50 hover:bg-green-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-green-900">Salvos</h3>
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
                                    <option value="{{ $i }}" {{ old('salvos_adulto_hombre', $asistencia->salvos_adulto_hombre ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Mujer</label>
                            <select name="salvos_adulto_mujer" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('salvos_adulto_mujer', $asistencia->salvos_adulto_mujer ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Hombre</label>
                            <select name="salvos_joven_hombre" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('salvos_joven_hombre', $asistencia->salvos_joven_hombre ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Mujer</label>
                            <select name="salvos_joven_mujer" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('salvos_joven_mujer', $asistencia->salvos_joven_mujer ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nino</label>
                            <select name="salvos_nino" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('salvos_nino', $asistencia->salvos_nino ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nina</label>
                            <select name="salvos_nina" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('salvos_nina', $asistencia->salvos_nina ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Bautismos -->
                <div class="border rounded-lg overflow-hidden border-blue-300">
                    <button type="button" onclick="toggleSection('bautismos')" class="w-full px-4 py-3 bg-blue-50 hover:bg-blue-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-blue-900">Bautismos</h3>
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
                                    <option value="{{ $i }}" {{ old('bautismos_adulto_hombre', $asistencia->bautismos_adulto_hombre ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Mujer</label>
                            <select name="bautismos_adulto_mujer" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('bautismos_adulto_mujer', $asistencia->bautismos_adulto_mujer ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Hombre</label>
                            <select name="bautismos_joven_hombre" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('bautismos_joven_hombre', $asistencia->bautismos_joven_hombre ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Mujer</label>
                            <select name="bautismos_joven_mujer" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('bautismos_joven_mujer', $asistencia->bautismos_joven_mujer ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nino</label>
                            <select name="bautismos_nino" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('bautismos_nino', $asistencia->bautismos_nino ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nina</label>
                            <select name="bautismos_nina" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('bautismos_nina', $asistencia->bautismos_nina ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Visitas -->
                <div class="border rounded-lg overflow-hidden border-purple-300">
                    <button type="button" onclick="toggleSection('visitas')" class="w-full px-4 py-3 bg-purple-50 hover:bg-purple-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-purple-900">Visitas</h3>
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
                                    <option value="{{ $i }}" {{ old('visitas_adulto_hombre', $asistencia->visitas_adulto_hombre ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Mujer</label>
                            <select name="visitas_adulto_mujer" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('visitas_adulto_mujer', $asistencia->visitas_adulto_mujer ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Hombre</label>
                            <select name="visitas_joven_hombre" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('visitas_joven_hombre', $asistencia->visitas_joven_hombre ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Mujer</label>
                            <select name="visitas_joven_mujer" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('visitas_joven_mujer', $asistencia->visitas_joven_mujer ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nino</label>
                            <select name="visitas_nino" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('visitas_nino', $asistencia->visitas_nino ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nina</label>
                            <select name="visitas_nina" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                @for($i = 0; $i <= 100; $i++)
                                    <option value="{{ $i }}" {{ old('visitas_nina', $asistencia->visitas_nina ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Registros Extra (no suman a personas) -->
                @foreach($registroExtraTipos as $tipo)
                @php
                    $registroExistente = $asistencia->registrosExtra->firstWhere('registro_extra_tipo_id', $tipo->id);
                    $valoresExistentes = $registroExistente ? $registroExistente->valores : [];
                @endphp
                <div class="border rounded-lg overflow-hidden" style="border-color: {{ $tipo->color }}40;">
                    <button type="button" onclick="toggleSection('extra-{{ $tipo->id }}')" class="w-full px-4 py-3 hover:opacity-80 flex justify-between items-center" style="background-color: {{ $tipo->color }}15;">
                        <h3 class="text-lg font-semibold" style="color: {{ $tipo->color }};">
                            {{ $tipo->nombre }}
                            <span class="text-xs font-normal text-gray-500 ml-2">(no suma a personas)</span>
                        </h3>
                        <svg id="extra-{{ $tipo->id }}-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="extra-{{ $tipo->id }}-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            @foreach($tipo->subcampos as $subcampo)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ ucfirst($subcampo) }}</label>
                                <select name="registro_extra[{{ $tipo->id }}][{{ $subcampo }}]" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @for($i = 0; $i <= 500; $i++)
                                        <option value="{{ $i }}" {{ old("registro_extra.{$tipo->id}.{$subcampo}", $valoresExistentes[$subcampo] ?? 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endforeach

                <!-- Total Asistencia -->
                <div class="bg-blue-50 rounded-lg p-6">
                    <div class="flex justify-between items-center">
                        <label for="total_asistencia" class="text-lg font-semibold text-gray-700">Total Asistencia *</label>
                        <input type="number" name="total_asistencia" id="total_asistencia" step="1" min="0" value="{{ old('total_asistencia', $asistencia->total_asistencia) }}"
                               class="w-32 text-2xl font-bold text-center rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required readonly>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('asistencia.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Actualizar Asistencia
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
        const maestroChecks = document.querySelectorAll('.maestro-check');
        const totalInput = document.getElementById('total_asistencia');

        function calcularTotal() {
            let total = 0;
            inputs.forEach(input => {
                total += parseInt(input.value) || 0;
            });
            // Sumar maestros seleccionados
            maestroChecks.forEach(cb => {
                if (cb.checked) total++;
            });
            totalInput.value = total;
        }

        function updateMaestroCount(claseId) {
            const checks = document.querySelectorAll('.maestro-check-' + claseId);
            const count = Array.from(checks).filter(c => c.checked).length;
            const label = document.getElementById('maestros-count-' + claseId);
            if (label) label.textContent = '(' + count + ' seleccionados)';
        }

        inputs.forEach(input => {
            input.addEventListener('change', calcularTotal);
        });

        maestroChecks.forEach(cb => {
            cb.addEventListener('change', function() {
                const claseId = this.className.match(/maestro-check-(\d+)/)?.[1];
                if (claseId) updateMaestroCount(claseId);
                calcularTotal();
            });
        });

        // Inicializar conteos de maestros pre-seleccionados
        const claseIds = new Set();
        maestroChecks.forEach(cb => {
            const match = cb.className.match(/maestro-check-(\d+)/);
            if (match) claseIds.add(match[1]);
        });
        claseIds.forEach(id => updateMaestroCount(id));

        calcularTotal();
    });
</script>
@endpush
@endsection

@extends('layouts.admin')

@section('title', tenant_title('Nueva Asistencia'))
@section('page-title', 'Nueva Asistencia')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('asistencia.store') }}" method="POST" id="asistenciaForm">
            @csrf

            <div class="mb-6">
                <label for="culto_id" class="block text-sm font-medium text-gray-700 mb-2">Seleccionar Culto *</label>
                <select name="culto_id" id="culto_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
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
                    <button type="button" onclick="toggleSection('capilla')" class="w-full px-4 py-3 bg-brand-50 hover:bg-brand-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-brand-900">Capilla</h3>
                        <svg id="capilla-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="capilla-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adultos Hombres</label>
                            <x-asistencia-numero name="chapel_adultos_hombres" :value="old('chapel_adultos_hombres', 0)" suma />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adultos Mujeres</label>
                            <x-asistencia-numero name="chapel_adultos_mujeres" :value="old('chapel_adultos_mujeres', 0)" suma />
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Clases Dinamicas -->
                @foreach($clases as $clase)
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
                            <x-asistencia-numero name="clase[{{ $clase->id }}][hombres]" :value='old("clase.{$clase->id}.hombres", 0)' suma />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Mujeres</label>
                            <x-asistencia-numero name="clase[{{ $clase->id }}][mujeres]" :value='old("clase.{$clase->id}.mujeres", 0)' suma />
                        </div>
                        </div>
                        @if($clase->tiene_maestros)
                        <div class="border-t pt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maestros que dieron clase <span class="text-gray-400 font-normal" id="maestros-count-{{ $clase->id }}">(0 seleccionados)</span></label>
                            @php $maestrosDeClase = $maestrosPorClase[$clase->id] ?? collect(); @endphp
                            @if($maestrosDeClase->count() > 0)
                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                @foreach($maestrosDeClase as $maestro)
                                <label class="flex items-center p-2 rounded hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" name="clase[{{ $clase->id }}][maestros_ids][]" value="{{ $maestro->id }}"
                                           class="rounded border-gray-300 text-brand-600 shadow-sm focus:border-brand-500 focus:ring-brand-500 maestro-check maestro-check-{{ $clase->id }}"
                                           {{ in_array($maestro->id, old("clase.{$clase->id}.maestros_ids", [])) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-700">{{ $maestro->nombre }}</span>
                                </label>
                                @endforeach
                            </div>
                            @else
                            <p class="text-sm text-gray-400 italic">No hay maestros asignados a esta clase. Asígnalos en Gestionar Personas.</p>
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
                            <x-asistencia-numero name="salvos_adulto_hombre" :value="old('salvos_adulto_hombre', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Mujer</label>
                            <x-asistencia-numero name="salvos_adulto_mujer" :value="old('salvos_adulto_mujer', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Hombre</label>
                            <x-asistencia-numero name="salvos_joven_hombre" :value="old('salvos_joven_hombre', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Mujer</label>
                            <x-asistencia-numero name="salvos_joven_mujer" :value="old('salvos_joven_mujer', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niño</label>
                            <x-asistencia-numero name="salvos_nino" :value="old('salvos_nino', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niña</label>
                            <x-asistencia-numero name="salvos_nina" :value="old('salvos_nina', 0)" />
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Bautismos -->
                <div class="border rounded-lg overflow-hidden border-brand-300">
                    <button type="button" onclick="toggleSection('bautismos')" class="w-full px-4 py-3 bg-brand-50 hover:bg-brand-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-brand-900">Bautismos</h3>
                        <svg id="bautismos-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="bautismos-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Hombre</label>
                            <x-asistencia-numero name="bautismos_adulto_hombre" :value="old('bautismos_adulto_hombre', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Mujer</label>
                            <x-asistencia-numero name="bautismos_adulto_mujer" :value="old('bautismos_adulto_mujer', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Hombre</label>
                            <x-asistencia-numero name="bautismos_joven_hombre" :value="old('bautismos_joven_hombre', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Mujer</label>
                            <x-asistencia-numero name="bautismos_joven_mujer" :value="old('bautismos_joven_mujer', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niño</label>
                            <x-asistencia-numero name="bautismos_nino" :value="old('bautismos_nino', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niña</label>
                            <x-asistencia-numero name="bautismos_nina" :value="old('bautismos_nina', 0)" />
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Visitas -->
                <div class="border rounded-lg overflow-hidden border-gray-300">
                    <button type="button" onclick="toggleSection('visitas')" class="w-full px-4 py-3 bg-gray-50 hover:bg-gray-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Visitas</h3>
                        <svg id="visitas-icon" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="visitas-content" class="p-4 hidden">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Hombre</label>
                            <x-asistencia-numero name="visitas_adulto_hombre" :value="old('visitas_adulto_hombre', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adulto Mujer</label>
                            <x-asistencia-numero name="visitas_adulto_mujer" :value="old('visitas_adulto_mujer', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Hombre</label>
                            <x-asistencia-numero name="visitas_joven_hombre" :value="old('visitas_joven_hombre', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Joven Mujer</label>
                            <x-asistencia-numero name="visitas_joven_mujer" :value="old('visitas_joven_mujer', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niño</label>
                            <x-asistencia-numero name="visitas_nino" :value="old('visitas_nino', 0)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Niña</label>
                            <x-asistencia-numero name="visitas_nina" :value="old('visitas_nina', 0)" />
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Total Asistencia -->
                <div class="bg-brand-50 rounded-lg p-6">
                    <div class="flex justify-between items-center">
                        <label for="total_asistencia" class="text-lg font-semibold text-gray-700">Total Asistencia *</label>
                        <input type="number" name="total_asistencia" id="total_asistencia" step="1" min="0" value="{{ old('total_asistencia', 0) }}"
                               class="w-32 text-2xl font-bold text-center rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" required readonly>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('asistencia.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md hover:bg-brand-700">
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
        // Una casilla inválida dentro de una sección plegada no se puede enfocar y el navegador
        // bloquea el envío sin avisar: se abre la sección para que se vea el mensaje.
        document.getElementById('asistenciaForm').addEventListener('invalid', function(e) {
            const seccion = e.target.closest('[id$="-content"]');
            if (seccion && seccion.classList.contains('hidden')) {
                seccion.classList.remove('hidden');
                const icono = document.getElementById(seccion.id.replace(/-content$/, '-icon'));
                if (icono) icono.classList.add('rotate-180');
            }
        }, true);

        const inputs = document.querySelectorAll('.asistencia-input');
        const maestroChecks = document.querySelectorAll('.maestro-check');
        const totalInput = document.getElementById('total_asistencia');

        function calcularTotal() {
            let total = 0;
            inputs.forEach(input => {
                total += parseInt(input.value) || 0;
            });
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
            input.addEventListener('input', calcularTotal);
            input.addEventListener('change', calcularTotal);
        });

        maestroChecks.forEach(cb => {
            cb.addEventListener('change', function() {
                const claseId = this.className.match(/maestro-check-(\d+)/)?.[1];
                if (claseId) updateMaestroCount(claseId);
                calcularTotal();
            });
        });

        calcularTotal();
    });
</script>
@endpush
@endsection

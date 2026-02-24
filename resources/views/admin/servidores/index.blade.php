@extends('layouts.admin')

@section('title', 'Servidores')
@section('page-title', 'Servidores')

@section('content')
<div class="space-y-6">
    <!-- Cards de acceso rapido -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Card Reporte -->
        <a href="{{ route('admin.servidores.reporte') }}"
           class="block bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl shadow-lg p-6 text-white hover:from-blue-600 hover:to-blue-800 transition-all transform hover:scale-[1.02]">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold mb-1">Reporte de Servidores</h3>
                    <p class="text-blue-100 text-sm">Compromisos y asistencia mensual</p>
                </div>
                <svg class="w-12 h-12 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </a>

        <!-- Card QR -->
        <div x-data="{ open: false }">
            <div class="block bg-gradient-to-br from-green-500 to-green-700 rounded-xl shadow-lg p-6 text-white cursor-pointer hover:from-green-600 hover:to-green-800 transition-all transform hover:scale-[1.02]"
                 @click="open = true">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold mb-1">Generar QR</h3>
                        <p class="text-green-100 text-sm">Codigo QR para marcar asistencia</p>
                    </div>
                    <svg class="w-12 h-12 text-green-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                </div>
            </div>

            <!-- Modal QR -->
            <div x-show="open" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                 @click.self="open = false"
                 x-transition>
                <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full mx-4 text-gray-800" @click.stop>
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-900">Generar QR de Culto</h3>
                        <button @click="open = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="mb-6">
                        <label for="qr_culto_id" class="block text-sm font-medium text-gray-700 mb-2">Seleccionar Culto</label>
                        <select id="qr_culto_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            @foreach($cultos as $culto)
                                <option value="{{ $culto->id }}" {{ $cultoSeleccionado && $culto->id == $cultoSeleccionado->id ? 'selected' : '' }}>
                                    {{ $culto->fecha->format('d/m/Y') }} - {{ $culto->tipo_nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button onclick="abrirQR()"
                            class="w-full px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        Mostrar QR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Selector de culto para la tabla -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" action="{{ route('admin.servidores.index') }}" class="flex items-end gap-4">
            <div class="flex-1">
                <label for="culto_id" class="block text-sm font-medium text-gray-700 mb-2">Culto</label>
                <select name="culto_id" id="culto_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($cultos as $culto)
                        <option value="{{ $culto->id }}" {{ $cultoSeleccionado && $culto->id == $cultoSeleccionado->id ? 'selected' : '' }}>
                            {{ $culto->fecha->format('d/m/Y') }} - {{ $culto->tipo_nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                Ver Asistencia
            </button>
        </form>
    </div>

    <!-- Tabla de Servidores y Asistencia -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold">
                Asistencia de Servidores
                @if($cultoSeleccionado)
                    - {{ $cultoSeleccionado->fecha->format('d/m/Y') }} ({{ $cultoSeleccionado->tipo_nombre }})
                @endif
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Servidor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Asistencia</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hora de Marca</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($servidores as $servidor)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <span class="text-blue-600 font-semibold text-sm">{{ strtoupper(substr($servidor->name, 0, 1)) }}</span>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $servidor->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $servidor->email }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($asistencias->has($servidor->id))
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    Presente
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                    Ausente
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($asistencias->has($servidor->id))
                                {{ $asistencias[$servidor->id]->created_at->format('d/m/Y H:i') }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                            No hay servidores registrados
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($servidores->count() > 0 && $cultoSeleccionado)
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600">
                    <strong>{{ $asistencias->count() }}</strong> de <strong>{{ $servidores->count() }}</strong> servidores presentes
                </span>
                <span class="text-gray-500">
                    {{ $servidores->count() > 0 ? round(($asistencias->count() / $servidores->count()) * 100) : 0 }}% de asistencia
                </span>
            </div>
        </div>
        @endif
    </div>
</div>

<script>
function abrirQR() {
    const cultoId = document.getElementById('qr_culto_id').value;
    if (cultoId) {
        window.open(`{{ url('/admin/servidores/qr') }}/${cultoId}`, '_blank');
    }
}
</script>
@endsection

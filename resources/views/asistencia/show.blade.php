@extends('layouts.admin')

@section('title', 'IBBSC - Detalle Asistencia')
@section('page-title', 'Detalle de Asistencia')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">{{ $asistencia->culto->fecha->format('d/m/Y') }}</h2>
                <p class="text-gray-600">{{ ucfirst($asistencia->culto->tipo_culto) }}</p>
                @if($asistencia->cerrado)
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs font-medium mt-2">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                        </svg>
                        Cerrado el {{ $asistencia->cerrado_at->format('d/m/Y H:i') }}
                    </span>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('asistencia.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Volver
                </a>
                @if(!$asistencia->cerrado)
                <a href="{{ route('asistencia.edit', $asistencia) }}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Editar
                </a>
                @endif
            </div>
        </div>

        @php
            $totalCapilla = ($asistencia->chapel_adultos_hombres ?? 0) + 
                           ($asistencia->chapel_adultos_mujeres ?? 0) +
                           ($asistencia->chapel_adultos_mayores_hombres ?? 0) + 
                           ($asistencia->chapel_adultos_mayores_mujeres ?? 0) +
                           ($asistencia->chapel_jovenes_masculinos ?? 0) + 
                           ($asistencia->chapel_jovenes_femeninas ?? 0) +
                           ($asistencia->chapel_maestros_hombres ?? 0) +
                           ($asistencia->chapel_maestros_mujeres ?? 0);
            
            $totalNinos = ($asistencia->clase_0_1_hombres ?? 0) + ($asistencia->clase_0_1_mujeres ?? 0) +
                         ($asistencia->clase_2_6_hombres ?? 0) + ($asistencia->clase_2_6_mujeres ?? 0) +
                         ($asistencia->clase_7_8_hombres ?? 0) + ($asistencia->clase_7_8_mujeres ?? 0) +
                         ($asistencia->clase_9_11_hombres ?? 0) + ($asistencia->clase_9_11_mujeres ?? 0);
            
            $totalMaestros = ($asistencia->clase_0_1_maestros_hombres ?? 0) + ($asistencia->clase_0_1_maestros_mujeres ?? 0) +
                            ($asistencia->clase_2_6_maestros_hombres ?? 0) + ($asistencia->clase_2_6_maestros_mujeres ?? 0) +
                            ($asistencia->clase_7_8_maestros_hombres ?? 0) + ($asistencia->clase_7_8_maestros_mujeres ?? 0) +
                            ($asistencia->clase_9_11_maestros_hombres ?? 0) + ($asistencia->clase_9_11_maestros_mujeres ?? 0);
            
            $totalSalvos = ($asistencia->salvos_adulto_hombre ?? 0) + ($asistencia->salvos_adulto_mujer ?? 0) +
                          ($asistencia->salvos_joven_hombre ?? 0) + ($asistencia->salvos_joven_mujer ?? 0) +
                          ($asistencia->salvos_nino ?? 0) + ($asistencia->salvos_nina ?? 0);
            
            $totalBautismos = ($asistencia->bautismos_adulto_hombre ?? 0) + ($asistencia->bautismos_adulto_mujer ?? 0) +
                             ($asistencia->bautismos_joven_hombre ?? 0) + ($asistencia->bautismos_joven_mujer ?? 0) +
                             ($asistencia->bautismos_nino ?? 0) + ($asistencia->bautismos_nina ?? 0);
            
            $totalVisitas = ($asistencia->visitas_adulto_hombre ?? 0) + ($asistencia->visitas_adulto_mujer ?? 0) +
                           ($asistencia->visitas_joven_hombre ?? 0) + ($asistencia->visitas_joven_mujer ?? 0) +
                           ($asistencia->visitas_nino ?? 0) + ($asistencia->visitas_nina ?? 0);
        @endphp

        <!-- Tabla de Detalles -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 border">
                <thead class="bg-blue-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Categoría</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase">Hombres</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase">Mujeres</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <!-- CAPILLA -->
                    <tr class="bg-blue-50 font-semibold">
                        <td class="px-4 py-2 text-sm" colspan="4">CAPILLA</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Adultos</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->chapel_adultos_hombres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->chapel_adultos_mujeres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->chapel_adultos_hombres ?? 0) + ($asistencia->chapel_adultos_mujeres ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Adultos Mayores</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->chapel_adultos_mayores_hombres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->chapel_adultos_mayores_mujeres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->chapel_adultos_mayores_hombres ?? 0) + ($asistencia->chapel_adultos_mayores_mujeres ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Jóvenes</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->chapel_jovenes_masculinos ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->chapel_jovenes_femeninas ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->chapel_jovenes_masculinos ?? 0) + ($asistencia->chapel_jovenes_femeninas ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Maestros</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->chapel_maestros_hombres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->chapel_maestros_mujeres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->chapel_maestros_hombres ?? 0) + ($asistencia->chapel_maestros_mujeres ?? 0) }}</td>
                    </tr>

                    <!-- CLASES -->
                    <tr class="bg-green-50 font-semibold">
                        <td class="px-4 py-2 text-sm" colspan="4">CLASES</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Clase 0-1 Años</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_0_1_hombres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_0_1_mujeres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->clase_0_1_hombres ?? 0) + ($asistencia->clase_0_1_mujeres ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Maestros 0-1</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_0_1_maestros_hombres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_0_1_maestros_mujeres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->clase_0_1_maestros_hombres ?? 0) + ($asistencia->clase_0_1_maestros_mujeres ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Clase 2-6 Años</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_2_6_hombres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_2_6_mujeres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->clase_2_6_hombres ?? 0) + ($asistencia->clase_2_6_mujeres ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Maestros 2-6</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_2_6_maestros_hombres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_2_6_maestros_mujeres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->clase_2_6_maestros_hombres ?? 0) + ($asistencia->clase_2_6_maestros_mujeres ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Clase 7-8 Años</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_7_8_hombres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_7_8_mujeres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->clase_7_8_hombres ?? 0) + ($asistencia->clase_7_8_mujeres ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Maestros 7-8</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_7_8_maestros_hombres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_7_8_maestros_mujeres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->clase_7_8_maestros_hombres ?? 0) + ($asistencia->clase_7_8_maestros_mujeres ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Clase 9-11 Años</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_9_11_hombres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_9_11_mujeres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->clase_9_11_hombres ?? 0) + ($asistencia->clase_9_11_mujeres ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Maestros 9-11</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_9_11_maestros_hombres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->clase_9_11_maestros_mujeres ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->clase_9_11_maestros_hombres ?? 0) + ($asistencia->clase_9_11_maestros_mujeres ?? 0) }}</td>
                    </tr>

                    <!-- SALVOS -->
                    <tr class="bg-green-100 font-semibold">
                        <td class="px-4 py-2 text-sm" colspan="4">✝️ SALVOS</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Adultos</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->salvos_adulto_hombre ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->salvos_adulto_mujer ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->salvos_adulto_hombre ?? 0) + ($asistencia->salvos_adulto_mujer ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Jóvenes</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->salvos_joven_hombre ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->salvos_joven_mujer ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->salvos_joven_hombre ?? 0) + ($asistencia->salvos_joven_mujer ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Niños</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->salvos_nino ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->salvos_nina ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->salvos_nino ?? 0) + ($asistencia->salvos_nina ?? 0) }}</td>
                    </tr>

                    <!-- BAUTISMOS -->
                    <tr class="bg-blue-100 font-semibold">
                        <td class="px-4 py-2 text-sm" colspan="4">💧 BAUTISMOS</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Adultos</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->bautismos_adulto_hombre ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->bautismos_adulto_mujer ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->bautismos_adulto_hombre ?? 0) + ($asistencia->bautismos_adulto_mujer ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Jóvenes</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->bautismos_joven_hombre ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->bautismos_joven_mujer ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->bautismos_joven_hombre ?? 0) + ($asistencia->bautismos_joven_mujer ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Niños</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->bautismos_nino ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->bautismos_nina ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->bautismos_nino ?? 0) + ($asistencia->bautismos_nina ?? 0) }}</td>
                    </tr>

                    <!-- VISITAS -->
                    <tr class="bg-purple-100 font-semibold">
                        <td class="px-4 py-2 text-sm" colspan="4">👥 VISITAS</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Adultos</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->visitas_adulto_hombre ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->visitas_adulto_mujer ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->visitas_adulto_hombre ?? 0) + ($asistencia->visitas_adulto_mujer ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Jóvenes</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->visitas_joven_hombre ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->visitas_joven_mujer ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->visitas_joven_hombre ?? 0) + ($asistencia->visitas_joven_mujer ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-700 pl-8">Niños</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->visitas_nino ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center">{{ $asistencia->visitas_nina ?? 0 }}</td>
                        <td class="px-4 py-2 text-sm text-center font-semibold">{{ ($asistencia->visitas_nino ?? 0) + ($asistencia->visitas_nina ?? 0) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Totales Finales -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mt-6">
            <div class="bg-blue-50 rounded-lg p-4 border-2 border-blue-200">
                <div class="text-sm text-gray-600 mb-1">Total Capilla</div>
                <div class="text-3xl font-bold text-blue-600">{{ $totalCapilla + $totalMaestros - $totalVisitas }}</div>
                <div class="text-xs text-gray-500 mt-1">Con maestros, sin visitas</div>
            </div>
            
            <div class="bg-green-50 rounded-lg p-4 border-2 border-green-200">
                <div class="text-sm text-gray-600 mb-1">Total Niños</div>
                <div class="text-3xl font-bold text-green-600">{{ $totalNinos }}</div>
                <div class="text-xs text-gray-500 mt-1">Sin contar maestros</div>
            </div>
            
            <div class="bg-yellow-50 rounded-lg p-4 border-2 border-yellow-200">
                <div class="text-sm text-gray-600 mb-1">Total Salvos</div>
                <div class="text-3xl font-bold text-yellow-600">{{ $totalSalvos }}</div>
                <div class="text-xs text-gray-500 mt-1">Decisiones registradas</div>
            </div>
            
            <div class="bg-purple-50 rounded-lg p-4 border-2 border-purple-200">
                <div class="text-sm text-gray-600 mb-1">Total Bautismos</div>
                <div class="text-3xl font-bold text-purple-600">{{ $totalBautismos }}</div>
                <div class="text-xs text-gray-500 mt-1">Bautizados en el culto</div>
            </div>

            <div class="bg-orange-50 rounded-lg p-4 border-2 border-orange-200">
                <div class="text-sm text-gray-600 mb-1">Total Visitas</div>
                <div class="text-3xl font-bold text-orange-600">{{ $totalVisitas }}</div>
                <div class="text-xs text-gray-500 mt-1">Personas visitantes</div>
            </div>
        </div>

        <!-- Total General -->
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 mt-6 text-white">
            <div class="flex flex-col sm:flex-row justify-between items-center">
                <div>
                    <div class="text-sm opacity-90 mb-1">TOTAL ASISTENCIA GENERAL</div>
                    <div class="text-5xl font-bold">{{ $asistencia->total_asistencia }}</div>
                </div>
                <div class="mt-4 sm:mt-0 text-right">
                    <div class="text-xs opacity-75">Maestros de Clases</div>
                    <div class="text-2xl font-semibold">{{ $totalMaestros }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

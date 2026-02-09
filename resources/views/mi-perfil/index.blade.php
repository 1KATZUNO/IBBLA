@extends('layouts.admin')

@section('title', 'IBBSC - Mi Perfil')
@section('page-title', 'Mi Perfil')

@section('content')
<div class="space-y-6">
    <!-- Información Personal -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 rounded-full bg-blue-600 flex items-center justify-center text-white text-2xl font-bold">
                {{ substr($persona->nombre, 0, 2) }}
            </div>
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $persona->nombre }}</h2>
                <p class="text-gray-600">{{ $persona->correo }}</p>
                @if($persona->telefono)
                <p class="text-gray-600">{{ $persona->telefono }}</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Resumen General -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">Promesas Activas</p>
                    <p class="text-3xl font-bold mt-2">{{ $promesasConEstatus->count() }}</p>
                </div>
                <svg class="w-12 h-12 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm">Promesas Cumplidas</p>
                    <p class="text-3xl font-bold mt-2">{{ $promesasConEstatus->where('cumplido', true)->count() }}</p>
                </div>
                <svg class="w-12 h-12 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </div>

        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-100 text-sm">Compromisos Pendientes</p>
                    <p class="text-3xl font-bold mt-2">{{ $compromisos->count() }}</p>
                </div>
                <svg class="w-12 h-12 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Promesas del Mes -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Mis Promesas - {{ now()->locale('es')->isoFormat('MMMM YYYY') }}</h3>
        </div>
        <div class="p-6">
            @if($promesasConEstatus->count() > 0)
                <div class="space-y-4">
                    @foreach($promesasConEstatus as $item)
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="font-semibold text-gray-900 capitalize">{{ $item['promesa']->categoria }}</h4>
                                <p class="text-sm text-gray-600">{{ ucfirst($item['promesa']->frecuencia) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-gray-900">₡{{ number_format($item['pagado'], 2) }}</p>
                                <p class="text-sm text-gray-600">de ₡{{ number_format($item['promesa']->monto, 2) }}</p>
                            </div>
                        </div>
                        
                        <!-- Barra de progreso -->
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 {{ $item['cumplido'] ? 'bg-green-500' : 'bg-blue-500' }}" 
                                 style="width: {{ $item['porcentaje'] }}%"></div>
                        </div>
                        
                        <div class="mt-2 flex items-center justify-between text-sm">
                            @if($item['cumplido'])
                                <span class="text-green-600 font-semibold flex items-center">
                                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    ¡Cumplido!
                                </span>
                            @else
                                <span class="text-gray-600">Falta: ₡{{ number_format($item['faltante'], 2) }}</span>
                            @endif
                            <span class="text-gray-500">{{ number_format($item['porcentaje'], 1) }}%</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <p class="mt-4 text-gray-600">No tienes promesas registradas</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Compromisos Pendientes -->
    @if($compromisos->count() > 0)
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Compromisos Pendientes</h3>
        </div>
        <div class="p-6">
            <div class="space-y-3">
                @foreach($compromisos as $compromiso)
                <div class="flex items-center justify-between p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div>
                        <p class="font-semibold text-gray-900 capitalize">{{ $compromiso->categoria }}</p>
                        <p class="text-sm text-gray-600">{{ $compromiso->descripcion }}</p>
                        <p class="text-xs text-gray-500 mt-1">Creado: {{ $compromiso->created_at->format('d/m/Y') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-bold text-red-600">₡{{ number_format($compromiso->deuda, 2) }}</p>
                        <p class="text-sm text-gray-600">Deuda pendiente</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Información Adicional -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <div class="flex items-start">
            <svg class="w-6 h-6 text-blue-600 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <h4 class="font-semibold text-blue-900 mb-2">Información Importante</h4>
                <ul class="text-sm text-blue-800 space-y-1">
                    <li>• Esta información se actualiza automáticamente cuando se registran tus ofrendas.</li>
                    <li>• Las promesas se calculan mensualmente según la frecuencia que estableciste.</li>
                    <li>• Si tienes dudas sobre tus compromisos, contacta al administrador de la iglesia.</li>
                    <li>• Solo puedes visualizar tu información, no puedes editarla desde aquí.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title', tenant_title('Personas'))
@section('page-title', 'Gestión de Personas')

@section('content')
<div class="space-y-6">
    <!-- Buscar y agregar: lo que mas se usa, arriba y en una sola fila -->
    <div class="bg-white rounded-lg shadow-md p-4">
        <div class="flex flex-col md:flex-row md:items-center gap-3">
            <form method="GET" action="{{ route('personas.index') }}" class="flex flex-1 flex-col sm:flex-row gap-2">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="search"
                           name="buscar"
                           value="{{ request('buscar') }}"
                           placeholder="Buscar por nombre, PIN o teléfono..."
                           aria-label="Buscar por nombre, PIN o teléfono"
                           class="block w-full min-h-[44px] pl-10 pr-3 border border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary flex-1 sm:flex-none">
                        Buscar
                    </button>
                    @if(request('buscar'))
                    <a href="{{ route('personas.index') }}" class="btn-secondary flex-1 sm:flex-none">
                        Limpiar
                    </a>
                    @endif
                </div>
            </form>
            <a href="{{ route('personas.create') }}" class="btn-primary">
                + Nueva Persona
            </a>
        </div>

        <div class="toolbar mt-3 pt-3 border-t border-gray-100">
            <span class="text-sm text-gray-500 mr-1">Reportes:</span>
            <button type="button" onclick="mostrarModalReporte()" class="btn-secondary">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Reporte PDF
            </button>
            <button type="button" onclick="mostrarModalReporteGeneral()" class="btn-secondary">
                <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
                </svg>
                Reporte General
            </button>
            <a href="{{ route('personas.tarjetas.index') }}" class="btn-secondary">
                <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                Tarjetas con QR
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="table-scroll">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden xl:table-cell">PIN</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden xl:table-cell">Teléfono</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden 2xl:table-cell">Correo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden 2xl:table-cell">Sobres</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden 2xl:table-cell">Promesas</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($personas as $persona)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm">
                        <a href="{{ route('personas.show', $persona) }}" class="font-medium text-gray-900 hover:text-brand-700 hover:underline">{{ $persona->nombre }}</a>
                        @if($persona->pin || $persona->telefono)
                        <div class="xl:hidden mt-0.5 text-xs text-gray-500">
                            {{ collect([$persona->pin ? 'PIN ' . $persona->pin : null, $persona->telefono])->filter()->implode(' · ') }}
                        </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 hidden xl:table-cell">
                        {{ $persona->pin ?? '-' }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 hidden xl:table-cell">
                        {{ $persona->telefono ?? '-' }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 hidden 2xl:table-cell">
                        {{ $persona->correo ?? '-' }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $persona->activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $persona->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 hidden 2xl:table-cell">
                        {{ $persona->sobres_count }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 hidden 2xl:table-cell">
                        {{ $persona->promesas_count }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('personas.show', $persona) }}" class="btn-icon text-green-700 hover:bg-green-50" aria-label="Ver {{ $persona->nombre }}" title="Ver detalle">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <a href="{{ route('compromisos.show', $persona) }}" class="btn-icon text-gray-700 hover:bg-gray-50" aria-label="Compromisos de {{ $persona->nombre }}" title="Compromisos">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            </a>
                            <x-whatsapp-enviar :persona="$persona" :compacto="true" />
                            <a href="{{ route('personas.informe', ['persona' => $persona, 'accion' => 'ver']) }}" target="_blank" rel="noopener" class="btn-icon text-green-700 hover:bg-green-50" aria-label="Estado de cuenta en PDF de {{ $persona->nombre }}" title="Estado de cuenta (PDF)">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                            </a>
                            <a href="{{ route('personas.tarjeta.show', $persona) }}" data-abrir-tarjeta="{{ route('personas.tarjeta.show', $persona) }}" class="btn-row text-gray-700 hover:bg-gray-100" aria-label="Tarjeta con QR de {{ $persona->nombre }}" title="Tarjeta con QR de acceso">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                Tarjeta
                            </a>
                            <a href="{{ route('personas.edit', $persona) }}" class="btn-row text-brand-700 hover:bg-brand-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                Editar
                            </a>
                            <button type="button" onclick="mostrarModalEliminar({{ $persona->id }}, @js($persona->nombre))" class="btn-icon text-red-600 hover:bg-red-50 hover:text-red-700" aria-label="Eliminar {{ $persona->nombre }}" title="Eliminar">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                        <form id="form-eliminar-{{ $persona->id }}" action="{{ route('personas.destroy', $persona) }}" method="POST" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                        @if(request('buscar'))
                            <p>No se encontro a nadie con "{{ request('buscar') }}".</p>
                            <a href="{{ route('personas.index') }}" class="btn-secondary mt-3">Ver todas las personas</a>
                        @else
                            <p>Todavia no hay personas registradas.</p>
                            <a href="{{ route('personas.create') }}" class="btn-primary mt-3">+ Nueva Persona</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-4">
        {{-- withQueryString: al cambiar de pagina se mantiene la busqueda --}}
        {{ $personas->withQueryString()->links() }}
    </div>

    <!-- Mantenimiento: acciones masivas e irreversibles, separadas de los botones del dia a dia -->
    <details class="bg-white rounded-lg shadow border border-red-200">
        <summary class="cursor-pointer select-none min-h-[44px] px-4 py-3 text-sm font-semibold text-red-800 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Mantenimiento (acciones que no se pueden deshacer)
        </summary>
        <div class="px-4 pb-4 pt-1 border-t border-red-100">
            <p class="text-sm text-gray-600 mb-3">Afectan a todas las personas a la vez. Cada una abre una pantalla que explica qué se borra y pide confirmación.</p>
            <div class="flex flex-col sm:flex-row flex-wrap gap-2">
                @if($personasInactivas > 0)
                <a href="{{ route('personas.mantenimiento', 'inactivas') }}" class="btn-danger w-full sm:w-auto">
                    Eliminar personas inactivas ({{ $personasInactivas }})
                </a>
                @endif

                <a href="{{ route('personas.mantenimiento', 'promesas') }}" class="btn-danger-soft w-full sm:w-auto">
                    Borrar todas las promesas
                </a>

            </div>
        </div>
    </details>
</div>

<!-- Tarjeta flotante con QR de acceso (el contenido lo trae personas.tarjeta.show) -->
<div id="modalTarjeta" class="hidden fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="modalTarjetaTitulo">
    <div class="relative mx-auto my-4 w-full max-w-lg px-4 sm:my-12">
        <div class="rounded-2xl bg-white p-4 shadow-popover sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 id="modalTarjetaTitulo" class="text-lg font-semibold text-gray-900">Tarjeta con QR de acceso</h3>
                <button type="button" data-cerrar-tarjeta class="btn-icon" aria-label="Cerrar">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="modalTarjetaContenido"></div>
        </div>
    </div>
</div>

<!-- Modal: Confirmar Eliminación -->
<div id="modalEliminar" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-full max-w-sm sm:max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-red-900">⚠️ Eliminar Persona</h3>
                <button type="button" onclick="cerrarModalEliminar()" class="btn-icon" aria-label="Cerrar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-yellow-800 mb-2">
                    ¿Estás seguro de que deseas eliminar a <strong id="nombrePersona"></strong>?
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button"
                        onclick="cerrarModalEliminar()"
                        class="btn-secondary">
                    Cancelar
                </button>
                <button type="button"
                        onclick="confirmarEliminacion()"
                        class="btn-danger">
                    Sí, Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Reporte PDF -->
@php
    $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $mesActual = (int)date('n');
    $añoActual = date('Y');
@endphp
<div id="modalReporte" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-full max-w-md shadow-lg rounded-lg bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-brand-900 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Reporte de Contribuciones
                </h3>
                <button type="button" onclick="cerrarModalReporte()" class="btn-icon" aria-label="Cerrar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="space-y-4">
                <div class="bg-brand-50 rounded-lg p-4 border border-brand-200 text-center">
                    <p class="text-sm text-gray-600 mb-1">Período del reporte:</p>
                    <p class="text-lg font-bold text-brand-700">Enero - {{ $meses[$mesActual] }} {{ $añoActual }}</p>
                    <p class="text-xs text-gray-500 mt-2">Contribuciones acumuladas de todas las personas</p>
                </div>

                <form action="{{ route('personas.reporte-pdf') }}" method="GET" target="_blank">
                    <div class="flex gap-3">
                        <button type="submit" name="accion" value="ver" class="flex-1 px-4 py-3 bg-brand-600 text-white rounded-md hover:bg-brand-700 transition-colors flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            Ver PDF
                        </button>
                        <button type="submit" name="accion" value="descargar" class="flex-1 px-4 py-3 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Descargar
                        </button>
                    </div>
                </form>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <p class="text-xs text-gray-600 text-center">
                        Incluye: Diezmo, Misiones, Seminario, Campamento, Construcción y Micro
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Reporte General -->
<div id="modalReporteGeneral" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-full max-w-md shadow-lg rounded-lg bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-brand-900 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
                    </svg>
                    Reporte General
                </h3>
                <button type="button" onclick="cerrarModalReporteGeneral()" class="btn-icon" aria-label="Cerrar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="space-y-4">
                <div class="bg-brand-50 rounded-lg p-4 border border-brand-200 text-center">
                    <p class="text-sm text-gray-600 mb-1">Periodo del reporte:</p>
                    <p class="text-lg font-bold text-brand-700">Enero - {{ $meses[$mesActual] }} {{ $añoActual }}</p>
                    <p class="text-xs text-gray-500 mt-2">Dado vs Esperado por mes y categoria</p>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <p class="text-xs text-gray-600 mb-2"><strong>Incluye:</strong></p>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li>• Todas las personas con promesas o contribuciones</li>
                        <li>• Categorías: Misiones, Seminario, Campa, Construcción, Micro</li>
                        <li>• Filas verdes (Dado) y azules (Esperado) por mes</li>
                        <li>• Totales y diferencia por persona</li>
                        <li>• Totales generales al final</li>
                    </ul>
                </div>

                <form action="{{ route('personas.reporte-general') }}" method="GET" target="_blank">
                    <div class="flex gap-3">
                        <button type="submit" name="accion" value="ver" class="flex-1 px-4 py-3 bg-brand-600 text-white rounded-md hover:bg-brand-700 transition-colors flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            Ver PDF
                        </button>
                        <button type="submit" name="accion" value="descargar" class="flex-1 px-4 py-3 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Descargar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Tarjeta flotante con QR: abrir, ver contraseña, restablecer acceso y generar QR nuevo sin salir de la lista
(function () {
    const modal = document.getElementById('modalTarjeta');
    const contenido = document.getElementById('modalTarjetaContenido');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const cargando = '<p class="py-10 text-center text-sm text-gray-500">Cargando la tarjeta…</p>';
    let ultimoFoco = null;

    function avisar(texto, tipo) {
        const aviso = contenido.querySelector('[data-tarjeta-aviso]');
        if (!aviso || !texto) return;
        aviso.className = 'mt-3 ' + (tipo === 'error' ? 'alert-error' : 'alert-success');
        aviso.textContent = texto;
    }

    async function abrir(url) {
        ultimoFoco = document.activeElement;
        contenido.innerHTML = cargando;
        modal.classList.remove('hidden');
        modal.querySelector('[data-cerrar-tarjeta]').focus();
        try {
            const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            contenido.innerHTML = await r.text();
        } catch (e) {
            contenido.innerHTML = '<p class="alert-error">No se pudo abrir la tarjeta. Recarga la página e inténtalo de nuevo.</p>';
        }
    }

    function cerrar() {
        modal.classList.add('hidden');
        contenido.innerHTML = '';
        if (ultimoFoco) ultimoFoco.focus();
    }

    document.querySelectorAll('[data-abrir-tarjeta]').forEach(function (enlace) {
        enlace.addEventListener('click', function (e) {
            e.preventDefault();
            abrir(enlace.dataset.abrirTarjeta);
        });
    });
    modal.querySelector('[data-cerrar-tarjeta]').addEventListener('click', cerrar);
    modal.addEventListener('click', function (e) {
        if (e.target === modal || e.target === modal.firstElementChild) cerrar();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) cerrar();
    });

    contenido.addEventListener('click', function (e) {
        if (e.target.closest('[data-tarjeta-ocultar]')) {
            const caja = contenido.querySelector('[data-tarjeta-contrasena]');
            caja.classList.add('hidden');
            caja.querySelector('[data-tarjeta-valor]').textContent = '';
        }
    });

    contenido.addEventListener('submit', async function (e) {
        const form = e.target.closest('form[data-tarjeta-accion]');
        if (!form) return;
        e.preventDefault();
        const boton = form.querySelector('[type="submit"]');
        if (boton) boton.disabled = true;
        try {
            const r = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            });
            const datos = await r.json().catch(function () { return {}; });
            if (form.dataset.tarjetaAccion === 'ver-contrasena') {
                if (r.ok && datos.contrasena) {
                    const caja = contenido.querySelector('[data-tarjeta-contrasena]');
                    caja.querySelector('[data-tarjeta-valor]').textContent = datos.contrasena;
                    caja.classList.remove('hidden');
                } else {
                    avisar(datos.mensaje || 'No se pudo ver la contraseña.', 'error');
                }
            } else {
                if (datos.html) contenido.innerHTML = datos.html;
                avisar(datos.mensaje || (r.ok ? 'Listo.' : 'No se pudo completar la acción.'), r.ok ? 'ok' : 'error');
            }
        } catch (err) {
            avisar('No se pudo completar la acción. Revisa la conexión e inténtalo de nuevo.', 'error');
        } finally {
            if (boton && boton.isConnected) boton.disabled = false;
        }
    });
})();

let personaIdEliminar = null;

function mostrarModalEliminar(id, nombre) {
    personaIdEliminar = id;
    document.getElementById('nombrePersona').textContent = nombre;
    document.getElementById('modalEliminar').classList.remove('hidden');
}

function cerrarModalEliminar() {
    document.getElementById('modalEliminar').classList.add('hidden');
    personaIdEliminar = null;
}

function confirmarEliminacion() {
    if (personaIdEliminar) {
        document.getElementById('form-eliminar-' + personaIdEliminar).submit();
    }
}

function mostrarModalReporte() {
    document.getElementById('modalReporte').classList.remove('hidden');
}

function cerrarModalReporte() {
    document.getElementById('modalReporte').classList.add('hidden');
}

function mostrarModalReporteGeneral() {
    document.getElementById('modalReporteGeneral').classList.remove('hidden');
}

function cerrarModalReporteGeneral() {
    document.getElementById('modalReporteGeneral').classList.add('hidden');
}

// Cerrar modal al hacer clic fuera
document.getElementById('modalReporte')?.addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalReporte();
    }
});

document.getElementById('modalEliminar')?.addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalEliminar();
    }
});

document.getElementById('modalReporteGeneral')?.addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalReporteGeneral();
    }
});
</script>
@endsection

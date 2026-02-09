@extends('layouts.admin')

@section('title', 'IBBSC - Recuento de Sobres')
@section('page-title', 'Recuento de Sobres')

@section('content')
<div class="space-y-6">
    <!-- Mensajes de éxito/error -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">{{ session('success') }}</span>
    </div>
    @endif
    
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">{{ session('error') }}</span>
    </div>
    @endif

    <!-- Filtro por Culto -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" action="{{ route('recuento.index') }}" class="flex items-end gap-4">
            <div class="flex-1">
                <label for="culto_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Seleccionar Culto
                </label>
                <select name="culto_id" id="culto_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="this.form.submit()">
                    <option value="">-- Seleccione un culto --</option>
                    @foreach($cultos as $culto)
                        <option value="{{ $culto->id }}" {{ request('culto_id') == $culto->id ? 'selected' : '' }}>
                            {{ $culto->fecha->format('d/m/Y') }} - {{ ucfirst($culto->tipo_culto) }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if($cultoSeleccionado)
            <div class="flex flex-col sm:flex-row gap-2">
                @if(!$cultoSeleccionado->cerrado)
                <a href="{{ route('recuento.create', ['culto_id' => $cultoSeleccionado->id]) }}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-center">
                    + Agregar Sobre
                </a>
                <button type="button" onclick="document.getElementById('modalSuelto').classList.remove('hidden')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                    + Dinero Suelto
                </button>
                <button type="button" onclick="document.getElementById('modalEgreso').classList.remove('hidden')" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                    - Egresos
                </button>
                <a href="{{ route('ingresos-asistencia.pdf-recuento-individual', $cultoSeleccionado->id) }}" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span class="hidden sm:inline">Descargar </span>PDF
                </a>
                <a href="{{ route('ingresos-asistencia.pdf-recuento-transferencias', $cultoSeleccionado->id) }}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="hidden sm:inline">Transferencias</span>
                </a>
                <button type="button" onclick="mostrarModalCerrarCulto({{ $cultoSeleccionado->id }})" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    Cerrar Culto
                </button>
                @else
                <a href="{{ route('ingresos-asistencia.pdf-recuento-individual', $cultoSeleccionado->id) }}" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span class="hidden sm:inline">Descargar </span>PDF
                </a>
                <a href="{{ route('ingresos-asistencia.pdf-recuento-transferencias', $cultoSeleccionado->id) }}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="hidden sm:inline">Transferencias</span>
                </a>
                <div class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md border border-gray-300 flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Culto Cerrado</span>
                    @if($cultoSeleccionado->cerrado_at)
                    <span class="text-xs text-gray-500">({{ $cultoSeleccionado->cerrado_at->format('d/m/Y H:i') }})</span>
                    @endif
                </div>
                @endif
            </div>
            @endif
        </form>
    </div>

    @if($cultoSeleccionado && !$cultoSeleccionado->cerrado)
    <!-- MODO EDICIÓN: Culto Activo Seleccionado -->
    <!-- Firmas de Recuento -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Firmas de Recuento</h3>
        <form method="POST" action="{{ route('recuento.firmas.update', $cultoSeleccionado->id) }}" class="space-y-4" id="firmasForm">
            @csrf

            <!-- Pastor -->
            <div class="border rounded-lg p-4 bg-gray-50">
                <label class="block text-sm font-medium text-gray-700 mb-2">Pastor</label>
                <div class="flex flex-wrap gap-3 items-center">
                    <input type="text" name="firma_pastor" id="firma_pastor_nombre"
                           value="{{ old('firma_pastor', $cultoSeleccionado->firma_pastor) }}"
                           class="flex-1 min-w-[200px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="Nombre del pastor">
                    <input type="hidden" name="firma_pastor_imagen" id="firma_pastor_imagen"
                           value="{{ old('firma_pastor_imagen', $cultoSeleccionado->firma_pastor_imagen) }}">
                    <button type="button" onclick="abrirModalFirma('pastor', document.getElementById('firma_pastor_nombre').value || 'Pastor')"
                            class="px-4 py-2 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                        Firmar
                    </button>
                    <div id="preview_pastor" class="w-24 h-12 border rounded bg-white flex items-center justify-center overflow-hidden">
                        @if($cultoSeleccionado->firma_pastor_imagen)
                            <img src="{{ $cultoSeleccionado->firma_pastor_imagen }}" class="max-w-full max-h-full object-contain">
                        @else
                            <span class="text-xs text-gray-400">Sin firma</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Tesoreros -->
            <div class="border rounded-lg p-4 bg-gray-50">
                <div class="flex justify-between items-center mb-3">
                    <label class="block text-sm font-medium text-gray-700">Tesoreros</label>
                    <button type="button" onclick="agregarTesorero()"
                            class="px-3 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200 text-sm flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Agregar Tesorero
                    </button>
                </div>
                <div id="tesorerosContainer" class="space-y-3">
                    @php
                        $tesorerosImagenes = old('firmas_tesoreros', $cultoSeleccionado->firmas_tesoreros_imagenes ?? []);
                        if (empty($tesorerosImagenes)) {
                            // Si no hay imágenes, usar los nombres viejos
                            $tesoreros = $cultoSeleccionado->firmas_tesoreros ?? [];
                            $tesorerosImagenes = array_map(fn($n) => ['nombre' => $n, 'imagen' => ''], $tesoreros);
                        }
                    @endphp
                    @if(empty($tesorerosImagenes))
                        <div class="tesorero-row flex flex-wrap gap-3 items-center p-3 bg-white rounded-lg border" data-index="0">
                            <input type="text" name="firmas_tesoreros[0][nombre]"
                                   class="flex-1 min-w-[200px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 tesorero-nombre"
                                   placeholder="Nombre del tesorero">
                            <input type="hidden" name="firmas_tesoreros[0][imagen]" class="tesorero-imagen">
                            <button type="button" onclick="abrirModalFirmaTesorero(this)"
                                    class="px-4 py-2 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                                Firmar
                            </button>
                            <div class="tesorero-preview w-24 h-12 border rounded bg-white flex items-center justify-center overflow-hidden">
                                <span class="text-xs text-gray-400">Sin firma</span>
                            </div>
                            <button type="button" onclick="quitarTesorero(this)"
                                    class="px-3 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    @else
                        @foreach($tesorerosImagenes as $index => $t)
                        <div class="tesorero-row flex flex-wrap gap-3 items-center p-3 bg-white rounded-lg border" data-index="{{ $index }}">
                            <input type="text" name="firmas_tesoreros[{{ $index }}][nombre]"
                                   value="{{ is_array($t) ? ($t['nombre'] ?? '') : $t }}"
                                   class="flex-1 min-w-[200px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 tesorero-nombre"
                                   placeholder="Nombre del tesorero">
                            <input type="hidden" name="firmas_tesoreros[{{ $index }}][imagen]"
                                   class="tesorero-imagen"
                                   value="{{ is_array($t) ? ($t['imagen'] ?? '') : '' }}">
                            <button type="button" onclick="abrirModalFirmaTesorero(this)"
                                    class="px-4 py-2 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                                Firmar
                            </button>
                            <div class="tesorero-preview w-24 h-12 border rounded bg-white flex items-center justify-center overflow-hidden">
                                @if(is_array($t) && !empty($t['imagen']))
                                    <img src="{{ $t['imagen'] }}" class="max-w-full max-h-full object-contain">
                                @else
                                    <span class="text-xs text-gray-400">Sin firma</span>
                                @endif
                            </div>
                            <button type="button" onclick="quitarTesorero(this)"
                                    class="px-3 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Guardar Firmas
                </button>
            </div>
        </form>
    </div>

    <!-- Modal de Firma -->
    <div id="modalFirma" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full">
            <div class="flex justify-between items-center p-4 border-b">
                <h4 class="text-lg font-semibold text-gray-800">
                    Firma de: <span id="modalFirmaNombre" class="text-blue-600"></span>
                </h4>
                <button type="button" onclick="cerrarModalFirma()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-4">
                <div class="border-2 border-dashed border-gray-300 rounded-lg bg-white mb-4">
                    <canvas id="signatureCanvas" class="w-full cursor-crosshair" style="touch-action: none;"></canvas>
                </div>
                <p class="text-sm text-gray-500 text-center mb-4">Dibuje su firma con el mouse o dedo</p>
            </div>
            <div class="flex justify-between p-4 border-t bg-gray-50 rounded-b-xl">
                <button type="button" onclick="limpiarCanvas()"
                        class="px-4 py-2 bg-yellow-100 text-yellow-700 rounded-md hover:bg-yellow-200 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Limpiar
                </button>
                <div class="flex gap-2">
                    <button type="button" onclick="cerrarModalFirma()"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-100">
                        Cancelar
                    </button>
                    <button type="button" onclick="guardarFirma()"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Guardar Firma
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales para firma
        let canvas, ctx;
        let isDrawing = false;
        let lastX = 0, lastY = 0;
        let currentSignerType = null; // 'pastor' o 'tesorero'
        let currentTesoreroRow = null;
        let tesoreroIndex = {{ count($tesorerosImagenes) > 0 ? count($tesorerosImagenes) : 1 }};

        document.addEventListener('DOMContentLoaded', function() {
            initCanvas();
        });

        function initCanvas() {
            canvas = document.getElementById('signatureCanvas');
            if (!canvas) return;

            // Ajustar tamaño del canvas
            const container = canvas.parentElement;
            canvas.width = container.offsetWidth - 4;
            canvas.height = 200;

            ctx = canvas.getContext('2d');
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            // Mouse events
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);

            // Touch events
            canvas.addEventListener('touchstart', handleTouchStart, { passive: false });
            canvas.addEventListener('touchmove', handleTouchMove, { passive: false });
            canvas.addEventListener('touchend', stopDrawing);
        }

        function getMousePos(e) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top
            };
        }

        function getTouchPos(e) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: e.touches[0].clientX - rect.left,
                y: e.touches[0].clientY - rect.top
            };
        }

        function startDrawing(e) {
            isDrawing = true;
            const pos = getMousePos(e);
            lastX = pos.x;
            lastY = pos.y;
        }

        function handleTouchStart(e) {
            e.preventDefault();
            isDrawing = true;
            const pos = getTouchPos(e);
            lastX = pos.x;
            lastY = pos.y;
        }

        function draw(e) {
            if (!isDrawing) return;
            const pos = getMousePos(e);

            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();

            lastX = pos.x;
            lastY = pos.y;
        }

        function handleTouchMove(e) {
            e.preventDefault();
            if (!isDrawing) return;
            const pos = getTouchPos(e);

            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();

            lastX = pos.x;
            lastY = pos.y;
        }

        function stopDrawing() {
            isDrawing = false;
        }

        function limpiarCanvas() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        function abrirModalFirma(type, nombre) {
            currentSignerType = type;
            currentTesoreroRow = null;
            document.getElementById('modalFirmaNombre').textContent = nombre || 'Firmante';
            document.getElementById('modalFirma').classList.remove('hidden');

            // Reinicializar canvas
            setTimeout(() => {
                initCanvas();
                limpiarCanvas();

                // Cargar firma existente si hay
                const existingSignature = document.getElementById('firma_pastor_imagen').value;
                if (existingSignature) {
                    loadSignatureToCanvas(existingSignature);
                }
            }, 100);
        }

        function abrirModalFirmaTesorero(btn) {
            const row = btn.closest('.tesorero-row');
            currentSignerType = 'tesorero';
            currentTesoreroRow = row;

            const nombre = row.querySelector('.tesorero-nombre').value || 'Tesorero';
            document.getElementById('modalFirmaNombre').textContent = nombre;
            document.getElementById('modalFirma').classList.remove('hidden');

            setTimeout(() => {
                initCanvas();
                limpiarCanvas();

                // Cargar firma existente si hay
                const existingSignature = row.querySelector('.tesorero-imagen').value;
                if (existingSignature) {
                    loadSignatureToCanvas(existingSignature);
                }
            }, 100);
        }

        function loadSignatureToCanvas(dataUrl) {
            const img = new Image();
            img.onload = function() {
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            };
            img.src = dataUrl;
        }

        function cerrarModalFirma() {
            document.getElementById('modalFirma').classList.add('hidden');
            currentSignerType = null;
            currentTesoreroRow = null;
        }

        function guardarFirma() {
            const dataUrl = canvas.toDataURL('image/png');

            if (currentSignerType === 'pastor') {
                document.getElementById('firma_pastor_imagen').value = dataUrl;
                document.getElementById('preview_pastor').innerHTML =
                    `<img src="${dataUrl}" class="max-w-full max-h-full object-contain">`;
            } else if (currentSignerType === 'tesorero' && currentTesoreroRow) {
                currentTesoreroRow.querySelector('.tesorero-imagen').value = dataUrl;
                currentTesoreroRow.querySelector('.tesorero-preview').innerHTML =
                    `<img src="${dataUrl}" class="max-w-full max-h-full object-contain">`;
            }

            cerrarModalFirma();
        }

        function agregarTesorero() {
            const container = document.getElementById('tesorerosContainer');
            const div = document.createElement('div');
            div.className = 'tesorero-row flex flex-wrap gap-3 items-center p-3 bg-white rounded-lg border';
            div.setAttribute('data-index', tesoreroIndex);
            div.innerHTML = `
                <input type="text" name="firmas_tesoreros[${tesoreroIndex}][nombre]"
                       class="flex-1 min-w-[200px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 tesorero-nombre"
                       placeholder="Nombre del tesorero">
                <input type="hidden" name="firmas_tesoreros[${tesoreroIndex}][imagen]" class="tesorero-imagen">
                <button type="button" onclick="abrirModalFirmaTesorero(this)"
                        class="px-4 py-2 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                    Firmar
                </button>
                <div class="tesorero-preview w-24 h-12 border rounded bg-white flex items-center justify-center overflow-hidden">
                    <span class="text-xs text-gray-400">Sin firma</span>
                </div>
                <button type="button" onclick="quitarTesorero(this)"
                        class="px-3 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            `;
            container.appendChild(div);
            tesoreroIndex++;
        }

        function quitarTesorero(btn) {
            const row = btn.closest('.tesorero-row');
            row.remove();
        }
    </script>
    
    <!-- Resumen del Culto -->
    @if($cultoSeleccionado->totales)
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600">Total General</p>
            <p class="text-2xl font-bold text-blue-600">₡{{ number_format($cultoSeleccionado->totales->total_general, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600">Cantidad de Sobres</p>
            <p class="text-2xl font-bold text-green-600">{{ $cultoSeleccionado->totales->cantidad_sobres }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600">Diezmos</p>
            <p class="text-2xl font-bold text-purple-600">₡{{ number_format($cultoSeleccionado->totales->total_diezmo, 2) }}</p>
        </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-600">Ofrenda Especial</p>
                <p class="text-2xl font-bold text-pink-600">₡{{ number_format($cultoSeleccionado->totales->total_ofrenda_especial, 2) }}</p>
            </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600">Transferencias</p>
            <p class="text-2xl font-bold text-orange-600">{{ $cultoSeleccionado->totales->cantidad_transferencias }}</p>
        </div>
    </div>
    @endif

    <!-- Tabla de Sobres -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° Sobre</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Persona</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Método Pago</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Detalles</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($sobres as $sobre)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            #{{ $sobre->numero_sobre }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $sobre->persona ? $sobre->persona->nombre : 'Anónimo' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm hidden sm:table-cell">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $sobre->metodo_pago == 'transferencia' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                {{ ucfirst($sobre->metodo_pago) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                            ₡{{ number_format($sobre->total_declarado, 2) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 hidden lg:table-cell">
                            @foreach($sobre->detalles as $detalle)
                                <span class="inline-block bg-gray-100 rounded px-2 py-1 text-xs mr-1 mb-1">
                                    {{ ucfirst($detalle->categoria) }}: ₡{{ number_format($detalle->monto, 2) }}
                                </span>
                            @endforeach
                            @if($sobre->metodo_pago === 'transferencia' && $sobre->comprobante_numero)
                                <span class="inline-block bg-blue-100 text-blue-800 rounded px-2 py-1 text-xs mr-1 mb-1">
                                    Comprobante: {{ $sobre->comprobante_numero }}
                                </span>
                            @endif
                            @if($sobre->notas)
                                <span class="block text-xs text-gray-500 mt-1">Notas: {{ $sobre->notas }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <!-- Desktop actions -->
                            <div class="hidden sm:flex sm:items-center sm:justify-end sm:gap-3">
                                <a href="{{ route('recuento.edit', $sobre) }}" class="text-blue-600 hover:text-blue-900">Editar</a>
                                @if(in_array(auth()->user()->rol, ['admin', 'tesorero']))
                                <button type="button" onclick="mostrarModalEliminarSobre({{ $sobre->id }}, {{ $sobre->numero_sobre }})" class="text-red-600 hover:text-red-900">
                                    Eliminar
                                </button>
                                @endif
                            </div>
                            
                            <!-- Mobile dropdown -->
                            <div class="relative sm:hidden">
                                <button type="button" onclick="toggleSobreDropdown({{ $sobre->id }})" class="p-2 hover:bg-gray-100 rounded-full">
                                    <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                    </svg>
                                </button>
                                <div id="sobre-dropdown-{{ $sobre->id }}" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200">
                                    <div class="py-1">
                                        <a href="{{ route('recuento.edit', $sobre) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50">
                                            <span class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"></path>
                                                    <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"></path>
                                                </svg>
                                                Editar
                                            </span>
                                        </a>
                                        @if(in_array(auth()->user()->rol, ['admin', 'tesorero']))
                                        <button type="button" onclick="mostrarModalEliminarSobre({{ $sobre->id }}, {{ $sobre->numero_sobre }}); toggleSobreDropdown({{ $sobre->id }})" class="w-full text-left block px-4 py-2 text-sm text-gray-700 hover:bg-red-50">
                                            <span class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                </svg>
                                                Eliminar
                                            </span>
                                        </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            @if(in_array(auth()->user()->rol, ['admin', 'tesorero']))
                            <form id="form-eliminar-sobre-{{ $sobre->id }}" action="{{ route('recuento.destroy', $sobre) }}" method="POST" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="mt-2">No hay sobres registrados para este culto</p>
                            <a href="{{ route('recuento.create', ['culto_id' => $cultoSeleccionado->id]) }}" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Agregar primer sobre
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabla Resumen por Culto -->
    @if($sobres->count() > 0)
    @php
        // Sobres por método de pago
        $sobresEfectivo = $sobres->where('metodo_pago', 'efectivo')->sum('total_declarado');
        $sobresTransferencias = $sobres->where('metodo_pago', 'transferencia')->sum('total_declarado');

        // Dinero suelto siempre es efectivo
        $totalSuelto = $ofrendasSueltas->sum('monto');

        // Total de egresos (solo se resta del efectivo)
        $totalEgresos = $egresos->sum('monto');

        // Totales finales
        $totalEfectivo = $sobresEfectivo + $totalSuelto - $totalEgresos;
        $totalTransferencias = $sobresTransferencias;
        $totalGeneral = $totalEfectivo + $totalTransferencias;
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <p class="text-sm text-gray-600">Total Efectivo</p>
            <p class="text-2xl font-bold text-green-600">₡{{ number_format($totalEfectivo, 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Sobres + Suelto - Egresos</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <p class="text-sm text-gray-600">Total Transferencias</p>
            <p class="text-2xl font-bold text-blue-600">₡{{ number_format($totalTransferencias, 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Sobres + Suelto</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
            <p class="text-sm text-gray-600">Total General</p>
            <p class="text-2xl font-bold text-purple-600">₡{{ number_format($totalGeneral, 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">Efectivo + Transferencias</p>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-900">Resumen Detallado por Categorías</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">N° Sobre</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Diezmo</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Misiones</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Seminario</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Campamento</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Préstamo</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Construcción</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Micro</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase font-bold">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @php
                        $totales = [
                            'diezmo' => 0,
                            'misiones' => 0,
                            'seminario' => 0,
                            'campa' => 0,
                            'prestamo' => 0,
                            'construccion' => 0,
                            'micro' => 0,
                            'subtotal' => 0
                        ];
                    @endphp
                    @foreach($sobres as $sobre)
                    @php
                        $detallesPorCategoria = $sobre->detalles->keyBy('categoria');
                        $diezmo = $detallesPorCategoria->get('diezmo')->monto ?? 0;
                        $misiones = $detallesPorCategoria->get('misiones')->monto ?? 0;
                        $seminario = $detallesPorCategoria->get('seminario')->monto ?? 0;
                        $campa = $detallesPorCategoria->get('campa')->monto ?? 0;
                        $prestamo = $detallesPorCategoria->get('prestamo')->monto ?? 0;
                        $construccion = $detallesPorCategoria->get('construccion')->monto ?? 0;
                        $micro = $detallesPorCategoria->get('micro')->monto ?? 0;
                        $subtotal = $sobre->total_declarado;

                        $totales['diezmo'] += $diezmo;
                        $totales['misiones'] += $misiones;
                        $totales['seminario'] += $seminario;
                        $totales['campa'] += $campa;
                        $totales['prestamo'] += $prestamo;
                        $totales['construccion'] += $construccion;
                        $totales['micro'] += $micro;
                        $totales['subtotal'] += $subtotal;
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">#{{ $sobre->numero_sobre }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700">₡{{ number_format($diezmo, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700">₡{{ number_format($misiones, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700">₡{{ number_format($seminario, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700">₡{{ number_format($campa, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700">₡{{ number_format($prestamo, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700">₡{{ number_format($construccion, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700">₡{{ number_format($micro, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-blue-600">₡{{ number_format($subtotal, 2) }}</td>
                    </tr>
                    @endforeach
                    
                    <!-- Filas de Dinero Suelto -->
                    @foreach($ofrendasSueltas as $ofrenda)
                    @php
                        $totales['subtotal'] += $ofrenda->monto;
                    @endphp
                    <tr class="hover:bg-green-50 bg-green-50/30">
                        <td class="px-4 py-3 text-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-medium text-green-700">Dinero Suelto</span>
                                    @if($ofrenda->descripcion)
                                    <span class="text-xs text-gray-500 block">{{ $ofrenda->descripcion }}</span>
                                    @endif
                                </div>
                                <div class="flex gap-2 ml-2">
                                    <button onclick="editarSuelto({{ $ofrenda->id }}, {{ $ofrenda->monto }}, '{{ $ofrenda->descripcion }}')" 
                                            class="text-blue-600 hover:text-blue-900 text-xs">
                                        Editar
                                    </button>
                                    @if(in_array(auth()->user()->rol, ['admin', 'tesorero']))
                                    <button type="button" onclick="mostrarModalEliminarSuelto({{ $ofrenda->id }}, '{{ $ofrenda->descripcion }}')" class="text-red-600 hover:text-red-900 text-xs">
                                        Eliminar
                                    </button>
                                    <form id="form-eliminar-suelto-{{ $ofrenda->id }}" action="{{ route('recuento.destroy-suelto', $ofrenda) }}" method="POST" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-green-600">₡{{ number_format($ofrenda->monto, 2) }}</td>
                    </tr>
                    @endforeach

                    <!-- Filas de Egresos -->
                    @foreach($egresos as $egreso)
                    @php
                        $totales['subtotal'] -= $egreso->monto;
                    @endphp
                    <tr class="hover:bg-red-50 bg-red-50/30">
                        <td class="px-4 py-3 text-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-medium text-red-700">Egreso</span>
                                    @if($egreso->descripcion)
                                    <span class="text-xs text-gray-500 block">{{ $egreso->descripcion }}</span>
                                    @endif
                                </div>
                                <div class="flex gap-2 ml-2">
                                    <button onclick="editarEgreso({{ $egreso->id }}, {{ $egreso->monto }}, '{{ $egreso->descripcion }}')" 
                                            class="text-blue-600 hover:text-blue-900 text-xs">
                                        Editar
                                    </button>
                                    @if(in_array(auth()->user()->rol, ['admin', 'tesorero']))
                                    <button type="button" onclick="mostrarModalEliminarEgreso({{ $egreso->id }}, '{{ $egreso->descripcion }}')" class="text-red-600 hover:text-red-900 text-xs">
                                        Eliminar
                                    </button>
                                    <form id="form-eliminar-egreso-{{ $egreso->id }}" action="{{ route('recuento.destroy-egreso', $egreso) }}" method="POST" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-400">-</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-red-600">₡{{ number_format($egreso->monto, 2) }}</td>
                    </tr>
                    @endforeach
                    
                    <!-- Fila de Totales -->
                    <tr class="bg-blue-50 border-t-2 border-blue-200">
                        <td class="px-4 py-3 text-sm font-bold text-gray-900">TOTALES</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-blue-700">₡{{ number_format($totales['diezmo'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-blue-700">₡{{ number_format($totales['misiones'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-blue-700">₡{{ number_format($totales['seminario'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-blue-700">₡{{ number_format($totales['campa'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-blue-700">₡{{ number_format($totales['prestamo'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-blue-700">₡{{ number_format($totales['construccion'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-blue-700">₡{{ number_format($totales['micro'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-green-700 text-lg">₡{{ number_format($totales['subtotal'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @endif

    <!-- Lista de Cultos Cerrados (Archivo) -->
    @if($cultosCerrados->count() > 0)
    <div class="mt-12 pt-8 border-t-2 border-gray-300">
        <div class="flex items-center gap-3 mb-6">
            <svg class="w-6 h-6 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z"></path>
                <path fill-rule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd"></path>
            </svg>
            <h2 class="text-2xl font-bold text-gray-800">Cultos Cerrados (Archivo)</h2>
            <span class="px-3 py-1 bg-gray-200 text-gray-700 rounded-full text-sm font-semibold">{{ $cultosCerrados->count() }}</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($cultosCerrados as $cultoCerrado)
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow border border-gray-200">
                <div class="p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">{{ $cultoCerrado->fecha->format('d/m/Y') }}</h3>
                            <p class="text-sm text-gray-600">{{ ucfirst($cultoCerrado->tipo_culto) }}</p>
                        </div>
                        <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs font-medium">
                            <svg class="w-3 h-3 inline" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                            </svg>
                            Cerrado
                        </span>
                    </div>

                    @if($cultoCerrado->totales)
                    <div class="bg-gray-50 rounded-lg p-3 mb-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs text-gray-600">Total General</span>
                            <span class="text-lg font-bold text-blue-600">₡{{ number_format($cultoCerrado->totales->total_general, 2) }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                            <div class="flex justify-between">
                                <span>Sobres:</span>
                                <span class="font-semibold">{{ $cultoCerrado->totales->cantidad_sobres }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Transferencias:</span>
                                <span class="font-semibold">{{ $cultoCerrado->totales->cantidad_transferencias }}</span>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="text-xs text-gray-500 mb-3">
                        <svg class="w-3 h-3 inline" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        Cerrado: {{ $cultoCerrado->cerrado_at->format('d/m/Y H:i') }}
                    </div>

                    <div class="flex gap-2">
                        <button onclick="verCultoCerrado({{ $cultoCerrado->id }})" 
                           class="flex-1 text-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                            </svg>
                            Ver
                        </button>
                        <a href="{{ route('ingresos-asistencia.pdf-recuento-individual', $cultoCerrado->id) }}" 
                           class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors">
                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </a>
                        <button type="button" onclick="mostrarModalEliminarCulto({{ $cultoCerrado->id }}, '{{ $cultoCerrado->fecha }}')" 
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                            <svg class="w-4 h-4 inline" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                        <form id="form-eliminar-culto-{{ $cultoCerrado->id }}" action="{{ route('cultos.destroy', $cultoCerrado) }}" method="POST" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

<!-- Modal para Ver Culto Cerrado -->
<div id="modalVerCultoCerrado" class="hidden fixed inset-0 bg-gray-900 bg-opacity-75 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-8 border w-11/12 max-w-6xl shadow-2xl rounded-lg bg-white mb-10">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-900">Resumen de Culto Cerrado</h3>
            <button onclick="cerrarModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>
        <div id="contenidoCultoCerrado">
            <!-- El contenido se cargará aquí dinámicamente -->
        </div>
    </div>
</div>

<script>
function verCultoCerrado(cultoId) {
    // Hacer petición AJAX para cargar el resumen
    fetch(`/recuento/culto-cerrado/${cultoId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('contenidoCultoCerrado').innerHTML = html;
            document.getElementById('modalVerCultoCerrado').classList.remove('hidden');
        })
        .catch(error => {
            alert('Error al cargar el resumen del culto');
            console.error(error);
        });
}

function cerrarModal() {
    document.getElementById('modalVerCultoCerrado').classList.add('hidden');
}
</script>

<!-- Modal para Dinero Suelto -->
<div id="modalSuelto" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-full max-w-sm sm:max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Agregar Dinero Suelto</h3>
            <form action="{{ route('recuento.store-suelto') }}" method="POST">
                @csrf
                <input type="hidden" name="culto_id" value="{{ $cultoSeleccionado?->id }}">
                
                <div class="mb-4">
                    <label for="monto_suelto" class="block text-sm font-medium text-gray-700 mb-2">Monto (₡) *</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">₡</span>
                        <input type="number" name="monto" id="monto_suelto" min="0.01" step="0.01" required
                               class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="descripcion_suelto" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <textarea name="descripcion" id="descripcion_suelto" rows="2"
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('modalSuelto').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Editar Dinero Suelto -->
<div id="modalEditarSuelto" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-full max-w-sm sm:max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Editar Dinero Suelto</h3>
            <form id="formEditarSuelto" method="POST">
                @csrf
                @method('PUT')
                
                <div class="mb-4">
                    <label for="monto_suelto_edit" class="block text-sm font-medium text-gray-700 mb-2">Monto (₡) *</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">₡</span>
                        <input type="number" name="monto" id="monto_suelto_edit" min="0.01" step="0.01" required
                               class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="descripcion_suelto_edit" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <textarea name="descripcion" id="descripcion_suelto_edit" rows="2"
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('modalEditarSuelto').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editarSuelto(id, monto, descripcion) {
    document.getElementById('formEditarSuelto').action = `/recuento/suelto/${id}`;
    document.getElementById('monto_suelto_edit').value = monto;
    document.getElementById('descripcion_suelto_edit').value = descripcion || '';
    document.getElementById('modalEditarSuelto').classList.remove('hidden');
}

function mostrarModalCerrarCulto(cultoId) {
    cultoIdCerrar = cultoId;
    document.getElementById('modalCerrarCulto').classList.remove('hidden');
}

function cerrarModalCerrarCulto() {
    document.getElementById('modalCerrarCulto').classList.add('hidden');
    cultoIdCerrar = null;
}

function confirmarCerrarCulto() {
    if (cultoIdCerrar) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/recuento/${cultoIdCerrar}/cerrar`;
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        form.appendChild(csrfToken);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Modales de eliminación
// Modales de eliminación
let sobreIdEliminar = null;
let sueltoIdEliminar = null;
let cultoIdEliminar = null;
let cultoIdCerrar = null;
let egresoIdEliminar = null;

function mostrarModalEliminarSobre(id, numero) {
    sobreIdEliminar = id;
    document.getElementById('numeroSobre').textContent = numero;
    document.getElementById('modalEliminarSobre').classList.remove('hidden');
}

function cerrarModalEliminarSobre() {
    document.getElementById('modalEliminarSobre').classList.add('hidden');
    sobreIdEliminar = null;
}

function confirmarEliminacionSobre() {
    if (sobreIdEliminar) {
        document.getElementById('form-eliminar-sobre-' + sobreIdEliminar).submit();
    }
}

function mostrarModalEliminarSuelto(id, descripcion) {
    sueltoIdEliminar = id;
    document.getElementById('descripcionSuelto').textContent = descripcion || 'este dinero suelto';
    document.getElementById('modalEliminarSuelto').classList.remove('hidden');
}

function cerrarModalEliminarSuelto() {
    document.getElementById('modalEliminarSuelto').classList.add('hidden');
    sueltoIdEliminar = null;
}

function confirmarEliminacionSuelto() {
    if (sueltoIdEliminar) {
        document.getElementById('form-eliminar-suelto-' + sueltoIdEliminar).submit();
    }
}

function editarEgreso(id, monto, descripcion) {
    document.getElementById('formEditarEgreso').action = `/recuento/egreso/${id}`;
    document.getElementById('monto_egreso_edit').value = monto;
    document.getElementById('descripcion_egreso_edit').value = descripcion || '';
    document.getElementById('modalEditarEgreso').classList.remove('hidden');
}

function mostrarModalEliminarEgreso(id, descripcion) {
    egresoIdEliminar = id;
    document.getElementById('descripcionEgreso').textContent = descripcion || 'este egreso';
    document.getElementById('modalEliminarEgreso').classList.remove('hidden');
}

function cerrarModalEliminarEgreso() {
    document.getElementById('modalEliminarEgreso').classList.add('hidden');
    egresoIdEliminar = null;
}

function confirmarEliminacionEgreso() {
    if (egresoIdEliminar) {
        document.getElementById('form-eliminar-egreso-' + egresoIdEliminar).submit();
    }
}

function mostrarModalEliminarCulto(id, fecha) {
    cultoIdEliminar = id;
    document.getElementById('fechaCulto').textContent = fecha;
    document.getElementById('modalEliminarCulto').classList.remove('hidden');
}

function cerrarModalEliminarCulto() {
    document.getElementById('modalEliminarCulto').classList.add('hidden');
    cultoIdEliminar = null;
}

function confirmarEliminacionCulto() {
    if (cultoIdEliminar) {
        document.getElementById('form-eliminar-culto-' + cultoIdEliminar).submit();
    }
}

function toggleSobreDropdown(id) {
    const dropdown = document.getElementById('sobre-dropdown-' + id);
    const allDropdowns = document.querySelectorAll('[id^="sobre-dropdown-"]');
    
    // Cierra todos los otros dropdowns
    allDropdowns.forEach(d => {
        if (d !== dropdown) {
            d.classList.add('hidden');
        }
    });
    
    // Toggle el dropdown actual
    dropdown.classList.toggle('hidden');
}

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', function(event) {
    if (!event.target.closest('[onclick^="toggleSobreDropdown"]') && !event.target.closest('[id^="sobre-dropdown-"]')) {
        const allDropdowns = document.querySelectorAll('[id^="sobre-dropdown-"]');
        allDropdowns.forEach(d => d.classList.add('hidden'));
    }
});
</script>

<!-- Modal: Eliminar Sobre -->
<div id="modalEliminarSobre" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-full max-w-sm sm:max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-red-900">⚠️ Eliminar Sobre</h3>
                <button onclick="cerrarModalEliminarSobre()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-yellow-800 mb-2">
                    ¿Estás seguro de que deseas eliminar el sobre <strong id="numeroSobre"></strong>?
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" 
                        onclick="cerrarModalEliminarSobre()"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="button" 
                        onclick="confirmarEliminacionSobre()"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                    Sí, Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Eliminar Dinero Suelto -->
<div id="modalEliminarSuelto" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-full max-w-sm sm:max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-red-900">⚠️ Eliminar Dinero Suelto</h3>
                <button onclick="cerrarModalEliminarSuelto()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-yellow-800 mb-2">
                    ¿Estás seguro de que deseas eliminar <strong id="descripcionSuelto"></strong>?
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" 
                        onclick="cerrarModalEliminarSuelto()"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="button" 
                        onclick="confirmarEliminacionSuelto()"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                    Sí, Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Egreso -->
<div id="modalEgreso" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-full max-w-sm sm:max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Agregar Egreso</h3>
            <form action="{{ route('recuento.store-egreso') }}" method="POST">
                @csrf
                <input type="hidden" name="culto_id" value="{{ $cultoSeleccionado?->id }}">
                
                <div class="mb-4">
                    <label for="monto_egreso" class="block text-sm font-medium text-gray-700 mb-2">Monto (₡) *</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">₡</span>
                        <input type="number" name="monto" id="monto_egreso" min="0.01" step="0.01" required
                               class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="descripcion_egreso" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <textarea name="descripcion" id="descripcion_egreso" rows="2"
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('modalEgreso').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Editar Egreso -->
<div id="modalEditarEgreso" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-full max-w-sm sm:max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Editar Egreso</h3>
            <form id="formEditarEgreso" method="POST">
                @csrf
                @method('PUT')
                
                <div class="mb-4">
                    <label for="monto_egreso_edit" class="block text-sm font-medium text-gray-700 mb-2">Monto (₡) *</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">₡</span>
                        <input type="number" name="monto" id="monto_egreso_edit" min="0.01" step="0.01" required
                               class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="descripcion_egreso_edit" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <textarea name="descripcion" id="descripcion_egreso_edit" rows="2"
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('modalEditarEgreso').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Eliminar Egreso -->
<div id="modalEliminarEgreso" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-full max-w-sm sm:max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-red-900">⚠️ Eliminar Egreso</h3>
                <button onclick="cerrarModalEliminarEgreso()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-yellow-800 mb-2">
                    ¿Estás seguro de que deseas eliminar <strong id="descripcionEgreso"></strong>?
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" 
                        onclick="cerrarModalEliminarEgreso()"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="button" 
                        onclick="confirmarEliminacionEgreso()"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                    Sí, Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Eliminar Culto -->
<div id="modalEliminarCulto" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-full max-w-sm sm:max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-red-900">⚠️ Eliminar Culto</h3>
                <button onclick="cerrarModalEliminarCulto()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-yellow-800 mb-2">
                    ¿Estás seguro de que deseas eliminar el culto del <strong id="fechaCulto"></strong> y todos sus datos asociados?
                </p>
                <p class="text-xs text-red-600 mt-2">
                    ⚠️ Esta acción no se puede deshacer.
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" 
                        onclick="cerrarModalEliminarCulto()"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="button" 
                        onclick="confirmarEliminacionCulto()"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                    Sí, Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cerrar Culto -->
<div id="modalCerrarCulto" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-4 sm:top-20 mx-4 sm:mx-auto p-5 border w-full max-w-sm sm:max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-orange-900">🔒 Cerrar Culto</h3>
                <button onclick="cerrarModalCerrarCulto()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-orange-800 mb-2">
                    ¿Cerrar este culto? Ya no podrás agregar o editar sobres después de cerrarlo.
                </p>
                <p class="text-xs text-orange-600 mt-2">
                    ⚠️ Esta acción bloqueará las ediciones del culto.
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" 
                        onclick="cerrarModalCerrarCulto()"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="button" 
                        onclick="confirmarCerrarCulto()"
                        class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700">
                    Sí, Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

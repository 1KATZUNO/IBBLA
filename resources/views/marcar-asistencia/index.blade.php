@extends('layouts.admin')

@section('title', 'Marcar Asistencia')
@section('page-title', 'Marcar Asistencia')

@section('content')
<div class="space-y-6" x-data="marcarAsistencia()">
    <!-- Boton principal -->
    <div class="flex justify-center">
        <button @click="abrirScanner()"
                class="px-8 py-6 bg-gradient-to-br from-green-500 to-green-700 text-white rounded-2xl shadow-lg hover:from-green-600 hover:to-green-800 transition-all transform hover:scale-[1.02] flex flex-col items-center gap-3">
            <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
            </svg>
            <span class="text-2xl font-bold">Marcar Asistencia</span>
            <span class="text-green-100 text-sm">Escanea el codigo QR del culto</span>
        </button>
    </div>

    <!-- Modal Scanner QR -->
    <div x-show="showScanner" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
         x-transition>
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-500 to-green-700 px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-white">Escanear QR</h3>
                <button @click="cerrarScanner()" class="text-white/80 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Scanner area -->
            <div class="p-6">
                <div id="qr-reader" class="w-full rounded-lg overflow-hidden"></div>

                <!-- Resultado -->
                <div x-show="resultado" x-cloak class="mt-4">
                    <div x-show="resultado === 'success'"
                         class="bg-green-50 border border-green-200 rounded-lg p-4 flex items-center gap-3">
                        <svg class="w-8 h-8 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <p class="font-semibold text-green-800">Asistencia Marcada</p>
                            <p class="text-sm text-green-600" x-text="mensaje"></p>
                        </div>
                    </div>
                    <div x-show="resultado === 'error'"
                         class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center gap-3">
                        <svg class="w-8 h-8 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <p class="font-semibold text-red-800">Error</p>
                            <p class="text-sm text-red-600" x-text="mensaje"></p>
                        </div>
                    </div>
                </div>

                <!-- Loading -->
                <div x-show="loading" x-cloak class="mt-4 flex items-center justify-center gap-2 text-gray-500">
                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Procesando...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Historial de asistencias -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold">Mi Historial de Asistencia</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Culto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Marcado</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($historial as $registro)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ $registro->culto ? $registro->culto->tipo_nombre : 'N/A' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $registro->culto ? $registro->culto->fecha->format('d/m/Y') : 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $registro->created_at->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-6 py-12 text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            No has marcado asistencia todavia. Escanea un codigo QR para comenzar.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
function marcarAsistencia() {
    return {
        showScanner: false,
        scanner: null,
        resultado: null,
        mensaje: '',
        loading: false,

        abrirScanner() {
            this.resultado = null;
            this.mensaje = '';
            this.showScanner = true;

            this.$nextTick(() => {
                this.scanner = new Html5Qrcode("qr-reader");
                this.scanner.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    (decodedText) => this.onScanSuccess(decodedText),
                    (errorMessage) => {}
                ).catch(err => {
                    this.resultado = 'error';
                    this.mensaje = 'No se pudo acceder a la camara. Verifica los permisos.';
                });
            });
        },

        cerrarScanner() {
            if (this.scanner) {
                this.scanner.stop().catch(() => {});
                this.scanner = null;
            }
            this.showScanner = false;
        },

        async onScanSuccess(decodedText) {
            if (this.loading) return;

            // Detener scanner
            if (this.scanner) {
                this.scanner.stop().catch(() => {});
            }

            this.loading = true;
            this.resultado = null;

            try {
                const data = JSON.parse(decodedText);
                if (!data.culto_id) {
                    throw new Error('QR invalido');
                }

                const response = await fetch('{{ route("marcar-asistencia.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ culto_id: data.culto_id }),
                });

                const result = await response.json();

                if (result.success) {
                    this.resultado = 'success';
                    this.mensaje = result.message;
                    // Recargar la pagina despues de 2 segundos para actualizar el historial
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    this.resultado = 'error';
                    this.mensaje = result.message;
                }
            } catch (e) {
                this.resultado = 'error';
                this.mensaje = e.message === 'QR invalido'
                    ? 'El codigo QR no es valido para marcar asistencia.'
                    : 'Error al procesar el codigo QR.';
            }

            this.loading = false;
        }
    };
}
</script>
@endsection

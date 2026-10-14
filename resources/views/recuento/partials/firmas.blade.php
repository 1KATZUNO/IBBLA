{{-- Firmas del recuento: van despues de los sobres y totales, que es cuando se firman --}}
@php
    $tesorerosFirmas = $culto->firmas_tesoreros_imagenes ?? [];
    if (empty($tesorerosFirmas)) {
        // Cultos viejos: solo guardaban los nombres
        $tesorerosFirmas = array_map(fn ($n) => ['nombre' => $n, 'imagen' => ''], $culto->firmas_tesoreros ?? []);
    }
    $tesorerosFirmas = old('firmas_tesoreros', $tesorerosFirmas);
@endphp
<section class="bg-white rounded-lg shadow p-4 sm:p-6" aria-labelledby="titulo-firmas">
    <h3 id="titulo-firmas" class="text-lg font-semibold text-gray-900 mb-4">Firmas del recuento</h3>

    @if(! $puedeEditar)
        {{-- Solo lectura: se ven las firmas, sin campos --}}
        <dl class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <div class="rounded-lg border border-gray-200 p-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Pastor</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $culto->firma_pastor ?: 'Sin nombre' }}</dd>
                <dd class="mt-2 h-14 flex items-center">
                    @if($culto->firma_pastor_imagen)
                        <img src="{{ $culto->firma_pastor_imagen }}" alt="Firma del pastor" class="max-h-14 object-contain">
                    @else
                        <span class="text-xs text-gray-400">Sin firma</span>
                    @endif
                </dd>
            </div>
            @forelse($tesorerosFirmas as $t)
                <div class="rounded-lg border border-gray-200 p-3">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Tesorero</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ (is_array($t) ? ($t['nombre'] ?? '') : $t) ?: 'Sin nombre' }}</dd>
                    <dd class="mt-2 h-14 flex items-center">
                        @if(is_array($t) && ! empty($t['imagen']))
                            <img src="{{ $t['imagen'] }}" alt="Firma del tesorero" class="max-h-14 object-contain">
                        @else
                            <span class="text-xs text-gray-400">Sin firma</span>
                        @endif
                    </dd>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-200 p-3 text-sm text-gray-500">Sin firmas de tesoreros.</div>
            @endforelse
        </dl>
    @else
        <form method="POST" action="{{ route('recuento.firmas.update', $culto->id) }}" class="space-y-4" id="firmasForm">
            @csrf

            <div class="border rounded-lg p-4 bg-gray-50">
                <label for="firma_pastor_nombre" class="block text-sm font-medium text-gray-700 mb-2">Pastor</label>
                <div class="flex flex-wrap gap-3 items-center">
                    <input type="text" name="firma_pastor" id="firma_pastor_nombre" autocomplete="off"
                           value="{{ old('firma_pastor', $culto->firma_pastor) }}"
                           class="flex-1 min-w-[200px] min-h-[44px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="Nombre del pastor">
                    <input type="hidden" name="firma_pastor_imagen" id="firma_pastor_imagen"
                           value="{{ old('firma_pastor_imagen', $culto->firma_pastor_imagen) }}">
                    <button type="button" data-firmar="pastor" class="btn bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 focus-visible:ring-blue-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        Firmar
                    </button>
                    <div id="preview_pastor" class="w-24 h-12 border rounded bg-white flex items-center justify-center overflow-hidden">
                        @if($culto->firma_pastor_imagen)
                            <img src="{{ $culto->firma_pastor_imagen }}" alt="Firma del pastor" class="max-w-full max-h-full object-contain">
                        @else
                            <span class="text-xs text-gray-400">Sin firma</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="border rounded-lg p-4 bg-gray-50">
                <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                    <span class="block text-sm font-medium text-gray-700">Tesoreros</span>
                    <button type="button" data-agregar-tesorero class="btn bg-green-50 text-green-800 border border-green-200 hover:bg-green-100 focus-visible:ring-green-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Agregar tesorero
                    </button>
                </div>
                <div id="tesorerosContainer" class="space-y-3" data-siguiente-indice="{{ max(count($tesorerosFirmas), 1) }}">
                    @foreach(empty($tesorerosFirmas) ? [['nombre' => '', 'imagen' => '']] : $tesorerosFirmas as $indice => $t)
                        @include('recuento.partials.fila-tesorero', [
                            'indice' => $indice,
                            'nombre' => is_array($t) ? ($t['nombre'] ?? '') : $t,
                            'imagen' => is_array($t) ? ($t['imagen'] ?? '') : '',
                        ])
                    @endforeach
                </div>
                <template id="plantillaTesorero">
                    @include('recuento.partials.fila-tesorero', ['indice' => '__INDICE__', 'nombre' => '', 'imagen' => ''])
                </template>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Guardar firmas
                </button>
            </div>
        </form>
    @endif
</section>

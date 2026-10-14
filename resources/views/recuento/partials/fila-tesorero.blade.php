<div class="tesorero-row flex flex-wrap gap-3 items-center p-3 bg-white rounded-lg border">
    <input type="text" name="firmas_tesoreros[{{ $indice }}][nombre]" value="{{ $nombre }}" autocomplete="off"
           class="tesorero-nombre flex-1 min-w-[200px] min-h-[44px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
           placeholder="Nombre del tesorero" aria-label="Nombre del tesorero">
    <input type="hidden" name="firmas_tesoreros[{{ $indice }}][imagen]" value="{{ $imagen }}" class="tesorero-imagen">
    <button type="button" data-firmar="tesorero" class="btn bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 focus-visible:ring-blue-500">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
        Firmar
    </button>
    <div class="tesorero-preview w-24 h-12 border rounded bg-white flex items-center justify-center overflow-hidden">
        @if($imagen)
            <img src="{{ $imagen }}" alt="Firma del tesorero" class="max-w-full max-h-full object-contain">
        @else
            <span class="text-xs text-gray-400">Sin firma</span>
        @endif
    </div>
    <button type="button" data-quitar-tesorero class="btn-icon text-red-600 hover:bg-red-50 hover:text-red-700" aria-label="Quitar tesorero" title="Quitar tesorero">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
    </button>
</div>

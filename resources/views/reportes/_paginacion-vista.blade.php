{{-- Paginación de la vista previa (HU-R4): solo si hay más de 100 filas. Variable: $vista (VistaPrevia). --}}
@if($vista->paginada())
    @php $p = $vista->pagina; @endphp
    <nav class="px-4 py-3 border-t border-gray-200 flex flex-wrap items-center gap-2" aria-label="Páginas de la vista previa">
        <p class="text-sm text-gray-600">Filas {{ $vista->desde() }} a {{ $vista->desde() + count($vista->filas()) - 1 }} de {{ $p->total() }}</p>
        <div class="flex flex-wrap gap-1 ml-auto">
            @if($p->onFirstPage())
                <span class="btn-secondary opacity-50 cursor-not-allowed min-h-[44px]">Anterior</span>
            @else
                <a href="{{ $p->previousPageUrl() }}" class="btn-secondary min-h-[44px]" rel="prev">Anterior</a>
            @endif
            @for($n = 1; $n <= $p->lastPage(); $n++)
                <a href="{{ $p->url($n) }}" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-lg text-sm {{ $n === $p->currentPage() ? 'bg-blue-600 text-white font-semibold' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                   @if($n === $p->currentPage()) aria-current="page" @endif>{{ $n }}</a>
            @endfor
            @if($p->hasMorePages())
                <a href="{{ $p->nextPageUrl() }}" class="btn-secondary min-h-[44px]" rel="next">Siguiente</a>
            @else
                <span class="btn-secondary opacity-50 cursor-not-allowed min-h-[44px]">Siguiente</span>
            @endif
        </div>
    </nav>
@endif

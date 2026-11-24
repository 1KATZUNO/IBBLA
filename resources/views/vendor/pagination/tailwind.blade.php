{{--
    Paginacion de la app (reemplaza la de Laravel): textos en espanol aunque APP_LOCALE sea "en"
    y botones de 44 px para tocar en tablet. Si son muchas paginas, los numeros bajan de linea.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginacion" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p class="text-sm text-gray-600">
            @if ($paginator->firstItem())
                Mostrando <span class="font-medium">{{ $paginator->firstItem() }}</span>
                a <span class="font-medium">{{ $paginator->lastItem() }}</span>
            @else
                Mostrando {{ $paginator->count() }}
            @endif
            de <span class="font-medium">{{ $paginator->total() }}</span>
        </p>

        <div class="flex flex-wrap items-center gap-1">
            {{-- Anterior --}}
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center justify-center min-h-[44px] px-3 text-sm font-medium text-gray-400 bg-white border border-gray-200 rounded-lg cursor-not-allowed" aria-disabled="true">
                    &lsaquo; Anterior
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center justify-center min-h-[44px] px-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    &lsaquo; Anterior
                </a>
            @endif

            {{-- Numeros (solo desde tablet; en telefono basta con anterior/siguiente) --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="hidden sm:inline-flex items-center justify-center min-h-[44px] min-w-[44px] px-2 text-sm text-gray-500" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="hidden sm:inline-flex items-center justify-center min-h-[44px] min-w-[44px] px-3 text-sm font-semibold text-white bg-brand-600 border border-brand-600 rounded-lg" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="hidden sm:inline-flex items-center justify-center min-h-[44px] min-w-[44px] px-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50" aria-label="Ir a la pagina {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Siguiente --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center justify-center min-h-[44px] px-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Siguiente &rsaquo;
                </a>
            @else
                <span class="inline-flex items-center justify-center min-h-[44px] px-3 text-sm font-medium text-gray-400 bg-white border border-gray-200 rounded-lg cursor-not-allowed" aria-disabled="true">
                    Siguiente &rsaquo;
                </span>
            @endif
        </div>
    </nav>
@endif

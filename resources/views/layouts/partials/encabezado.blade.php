{{--
    Encabezado de pagina: titulo (y descripcion) a la izquierda; acciones a la derecha, con el boton "?"
    de ayuda que abre la seccion del manual de esta pantalla.

    Cada pantalla lo llena con:
      @section('page-title', 'Gestion de Personas')          (obligatorio)
      @section('page-subtitle', 'Busca, agrega y edita...')   (opcional; por omision, la fecha de hoy)
      @section('page-actions') <a class="btn-primary">+ Nueva</a> @endsection   (opcional, a la derecha)
      @section('sin-encabezado', '1')                         (opcional: la pantalla trae su propio encabezado)
--}}
@php
    $ayudaUrl = \App\Support\Manual\Manual::enlaceAyuda(request()->route()?->getName());
@endphp
@if(View::hasSection('page-title') && ! View::hasSection('sin-encabezado'))
    <div class="page-header">
        <div class="min-w-0 flex-1 basis-72">
            <h1 class="page-title">@yield('page-title')</h1>
            <p class="page-subtitle">
                @hasSection('page-subtitle')
                    @yield('page-subtitle')
                @else
                    <span class="capitalize">{{ \Carbon\Carbon::now()->locale('es')->isoFormat('dddd') }}</span>{{ \Carbon\Carbon::now()->locale('es')->isoFormat(', D [de] MMMM [de] YYYY') }}
                @endif
            </p>
        </div>
        <div class="page-actions">
            @yield('page-actions')
            @if($ayudaUrl)
                <a href="{{ $ayudaUrl }}" target="_blank" rel="noopener" class="help-btn" title="Ayuda sobre esta pantalla (se abre en otra pestaña)" aria-label="Ayuda sobre esta pantalla, se abre en otra pestaña">
                    <span class="help-btn-mark" aria-hidden="true">?</span>
                    <span class="hidden sm:inline">Ayuda</span>
                </a>
            @endif
        </div>
    </div>
@endif

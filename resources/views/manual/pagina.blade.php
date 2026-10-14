@extends('layouts.admin')

@section('page-title', $pagina->titulo)
@section('page-subtitle')
    <a href="{{ route('manual.index') }}" class="link">Manual de usuario</a>
    <span aria-hidden="true">›</span> {{ $pagina->titulo }}
@endsection

@section('page-actions')
    <a href="{{ route('manual.pagina.pdf', $pagina->slug) }}" class="btn-secondary">
        <x-icono nombre="descargar" class="h-5 w-5" />
        <span>Descargar esta página (PDF)</span>
    </a>
@endsection

@section('content')
<div class="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)] xl:grid-cols-[15rem_minmax(0,1fr)_14rem]">

    {{-- Modulos del manual: lista fija desde 1024 px; desplegable en pantallas angostas --}}
    <aside class="lg:sticky lg:top-6 lg:self-start xl:top-8" aria-label="Módulos del manual">
        <details class="card lg:hidden">
            <summary class="flex min-h-[48px] cursor-pointer items-center justify-between gap-2 px-4 text-sm font-semibold text-gray-900">
                <span>Módulos del manual</span>
                <x-icono nombre="flecha-abajo" class="h-4 w-4 text-gray-500" />
            </summary>
            <nav class="border-t border-gray-100 p-2" aria-label="Módulos del manual">
                @foreach($paginas as $p)
                    <a href="{{ route('manual.pagina', $p->slug) }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-sm {{ $p->slug === $pagina->slug ? 'bg-brand-50 font-semibold text-brand-800' : 'text-gray-700 hover:bg-gray-50' }}">{{ $p->titulo }}</a>
                @endforeach
            </nav>
        </details>

        <nav class="card hidden p-2 lg:block" aria-label="Módulos del manual">
            <a href="{{ route('manual.index') }}" class="mb-1 flex min-h-[40px] items-center gap-2 rounded-lg px-3 text-xs font-semibold uppercase tracking-wide text-gray-500 hover:bg-gray-50">
                <x-icono nombre="flecha-izquierda" class="h-3.5 w-3.5" /> Índice del manual
            </a>
            @foreach($paginas as $p)
                <a href="{{ route('manual.pagina', $p->slug) }}"
                   class="flex min-h-[40px] items-center gap-2.5 rounded-lg px-3 text-sm leading-tight {{ $p->slug === $pagina->slug ? 'bg-brand-50 font-semibold text-brand-800' : 'text-gray-700 hover:bg-gray-50' }}"
                   @if($p->slug === $pagina->slug) aria-current="page" @endif>
                    <x-icono :nombre="$p->icono" class="h-4 w-4 shrink-0 {{ $p->slug === $pagina->slug ? 'text-brand-600' : 'text-gray-400' }}" />
                    <span>{{ $p->titulo }}</span>
                </a>
            @endforeach
        </nav>
    </aside>

    {{-- Contenido --}}
    <article class="min-w-0">
        <div class="card p-5 sm:p-8">
            <header class="mb-6 border-b border-gray-200 pb-5">
                @if($pagina->resumen)
                    <p class="text-lg leading-relaxed text-gray-700">{{ $pagina->resumen }}</p>
                @endif
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                    <span>Lo usan:</span>
                    @if(in_array('todos', $pagina->roles, true) || $pagina->roles === [])
                        <span class="badge-neutral">Todos los roles</span>
                    @else
                        @foreach($pagina->roles as $rol)
                            <span class="{{ in_array($rol, \App\Support\Manual\Manual::rolesDe($usuario), true) ? 'badge-success' : 'badge-neutral' }}">{{ \App\Support\Manual\Manual::ROLES[$rol] ?? ucfirst($rol) }}</span>
                        @endforeach
                    @endif
                </div>
            </header>

            <div class="manual-prose">
                {!! $render['html'] !!}
            </div>
        </div>

        <nav class="mt-6 grid gap-3 sm:grid-cols-2" aria-label="Página anterior y siguiente">
            @if($anterior)
                <a href="{{ route('manual.pagina', $anterior->slug) }}" class="card flex min-h-[64px] items-center gap-3 p-4 transition-shadow hover:shadow-card-hover">
                    <x-icono nombre="flecha-izquierda" class="h-5 w-5 shrink-0 text-gray-400" />
                    <span class="min-w-0"><span class="block text-xs text-gray-500">Anterior</span><span class="block truncate font-medium text-gray-900">{{ $anterior->titulo }}</span></span>
                </a>
            @else
                <span></span>
            @endif
            @if($siguiente)
                <a href="{{ route('manual.pagina', $siguiente->slug) }}" class="card flex min-h-[64px] items-center justify-end gap-3 p-4 text-right transition-shadow hover:shadow-card-hover">
                    <span class="min-w-0"><span class="block text-xs text-gray-500">Siguiente</span><span class="block truncate font-medium text-gray-900">{{ $siguiente->titulo }}</span></span>
                    <x-icono nombre="flecha-derecha" class="h-5 w-5 shrink-0 text-gray-400" />
                </a>
            @endif
        </nav>
    </article>

    {{-- En esta pagina --}}
    <aside class="hidden xl:sticky xl:top-8 xl:block xl:self-start" aria-label="En esta página">
        @if(count($render['secciones']))
            <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-wide text-gray-500">En esta página</p>
            <ul class="border-l border-gray-200">
                @foreach($render['secciones'] as $sec)
                    <li>
                        <a href="#{{ $sec['id'] }}" class="-ml-px block border-l-2 border-transparent py-1.5 pr-2 text-sm leading-snug text-gray-600 hover:border-brand-500 hover:text-brand-800 {{ $sec['nivel'] === 3 ? 'pl-6' : 'pl-3' }}">{{ $sec['titulo'] }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </aside>
</div>
@endsection

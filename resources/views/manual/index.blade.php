@extends('layouts.admin')

@section('page-title', 'Manual de usuario')
@section('page-subtitle', 'Qué hace cada pantalla y cada botón, quién lo puede usar y qué pasa después')

@section('page-actions')
    @if(count($paginas))
        <a href="{{ route('manual.pdf') }}" class="btn-secondary">
            <x-icono nombre="descargar" class="h-5 w-5" />
            <span>Descargar el manual (PDF)</span>
        </a>
    @endif
@endsection

@section('content')
<div class="max-w-6xl space-y-6" x-data="manualBuscador({ url: @js(route('buscar')) })">

    {{-- Buscador del manual: temas y secciones, con la frase donde aparece --}}
    <div class="card p-3 sm:p-4">
        <label for="manual-q" class="sr-only">Buscar en el manual</label>
        <div class="relative">
            <x-icono nombre="lupa" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
            <input id="manual-q" type="search" x-model="q" maxlength="100" autocomplete="off" class="input-primary !pl-11"
                   placeholder="Busca un tema: cerrar un culto, agregar una persona, descargar un reporte…">
        </div>
        <p class="mt-2 text-xs text-gray-500">
            Tu rol: <span class="font-semibold text-gray-700">{{ $usuario->rol_nombre }}</span>.
            Los módulos marcados con «Para tu rol» son los que puedes usar; el resto los usan otros roles.
        </p>
    </div>

    {{-- Resultados de la busqueda --}}
    <div x-show="q.trim().length >= 2" x-cloak class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Resultados</h2>
            <span class="badge-neutral" x-show="!cargando" x-text="resultados.length"></span>
        </div>
        <div x-show="cargando" class="flex items-center gap-3 p-4 text-sm text-gray-500" role="status">
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-brand-600"></span> Buscando…
        </div>
        <div x-show="!cargando && buscado === q.trim() && resultados.length === 0" class="empty-state">
            <span class="empty-state-icon"><x-icono nombre="lupa" class="h-6 w-6" /></span>
            <p class="empty-state-title">No hay temas con “<span x-text="q.trim()"></span>”</p>
            <p class="empty-state-text">Prueba con una palabra más corta o con el nombre del botón, por ejemplo «sobre», «culto» o «PDF».</p>
        </div>
        <ul class="divide-y divide-gray-100" x-show="!cargando">
            <template x-for="r in resultados" :key="r.url">
                <li>
                    <a :href="r.url" class="flex min-h-[56px] items-center gap-3 px-4 py-3 transition-colors hover:bg-gray-50 sm:px-6">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path :d="r.trazo"></path></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium text-gray-900" x-text="r.titulo"></span>
                            <span class="block text-sm text-gray-500 line-clamp-2" x-text="r.detalle"></span>
                        </span>
                        <x-icono nombre="flecha-derecha" class="h-4 w-4 shrink-0 text-gray-400" />
                    </a>
                </li>
            </template>
        </ul>
    </div>

    {{-- Modulos --}}
    <div x-show="q.trim().length < 2">
        @if(count($paginas) === 0)
            <div class="card empty-state">
                <span class="empty-state-icon"><x-icono nombre="libro" class="h-6 w-6" /></span>
                <p class="empty-state-title">El manual todavía no tiene páginas</p>
                <p class="empty-state-text">Las páginas se escriben en resources/views/manual/paginas (ver docs/UI.md).</p>
            </div>
        @else
            <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($paginas as $p)
                    <li class="flex">
                        <a href="{{ route('manual.pagina', $p->slug) }}"
                           class="card group flex w-full flex-col gap-3 p-5 transition-shadow hover:shadow-card-hover">
                            <div class="flex items-start justify-between gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                                    <x-icono :nombre="$p->icono" class="h-6 w-6" />
                                </span>
                                @if(\App\Support\Manual\Manual::esParaRol($p, $usuario))
                                    <span class="badge-success badge-dot">Para tu rol</span>
                                @endif
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 group-hover:text-brand-700">{{ $p->titulo }}</h2>
                                <p class="mt-1 text-sm text-gray-600 line-clamp-3">{{ $p->resumen }}</p>
                            </div>
                            <p class="mt-auto pt-1 text-xs text-gray-500">Lo usan: {{ \App\Support\Manual\Manual::etiquetaRoles($p) }}</p>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('manualBuscador', (cfg) => ({
            q: '',
            resultados: [],
            cargando: false,
            buscado: '',
            temporizador: null,
            peticion: null,

            init() {
                this.$watch('q', () => this.alEscribir());
            },

            alEscribir() {
                clearTimeout(this.temporizador);
                const q = this.q.trim();
                if (q.length < 2) {
                    if (this.peticion) { this.peticion.abort(); }
                    this.resultados = []; this.buscado = ''; this.cargando = false;
                    return;
                }
                this.cargando = true;
                this.temporizador = setTimeout(() => this.buscar(q), 200);
            },

            async buscar(q) {
                if (this.peticion) { this.peticion.abort(); }
                this.peticion = new AbortController();
                try {
                    const r = await fetch(cfg.url + '?solo=manual&q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        signal: this.peticion.signal,
                    });
                    const d = await r.json();
                    this.resultados = (d.grupos[0] ? d.grupos[0].items : []);
                    this.buscado = q;
                    this.cargando = false;
                } catch (e) {
                    if (e.name !== 'AbortError') { this.resultados = []; this.cargando = false; }
                }
            },
        }));
    });
</script>
@endpush

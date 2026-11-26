{{--
    Barra superior fija (upbar): menu, logo, buscador global, manual, usuario y salir.

    Convive con el menu lateral (layouts/partials/sidebar). Alto fijo de 56 px (--upbar-h); el
    contenido hace scroll dentro de <main>, asi que la barra nunca se mueve.
    El elemento <header> es la raiz del componente Alpine "buscadorGlobal" (script al final), por eso
    el buscador puede ocultar el resto de la barra en pantallas angostas.

    Variables que espera: $u, $tenantName, $tenantFullName, $tenantLogo
--}}
@php
    $tieneBuscador = Route::has('buscar');
    $tieneManual = Route::has('manual.index');
    $permisosMenu = \App\Support\Navegacion\Menu::permisos($u);
    $iniciales = \App\Support\Tema::iniciales($u->name);
@endphp
<header id="upbar" role="banner"
        x-data="buscadorGlobal({ url: @js($tieneBuscador ? route('buscar') : ''), q: @js(request()->routeIs('buscar') ? (string) request('q', '') : '') })"
        @keydown.window="atajo($event)"
        class="upbar relative z-40 flex flex-shrink-0 items-center gap-1.5 px-2 sm:gap-2 sm:px-3 lg:px-4">

    {{-- Izquierda: boton del menu y marca --}}
    <div class="flex min-w-0 shrink-0 items-center gap-1 sm:gap-2" :class="movil ? 'hidden md:flex' : ''">
        <button id="toggleSidebar" type="button" class="upbar-btn !px-0" aria-controls="sidebar" aria-label="Mostrar u ocultar el menú">
            <x-icono nombre="menu" class="h-6 w-6" />
        </button>
        <a href="{{ route('principal') }}" class="flex min-w-0 items-center gap-2.5 rounded-lg py-1 pl-1 pr-2 transition-colors hover:bg-white/10" title="Ir a la pantalla Principal">
            <span class="h-9 w-9 shrink-0 rounded-lg bg-white/10 p-1.5">
                <img src="{{ $tenantLogo }}" alt="" class="h-full w-full object-contain">
            </span>
            <span class="hidden min-w-0 leading-tight sm:block">
                <span class="block truncate text-[15px] font-semibold tracking-tight">{{ $tenantName }}</span>
                <span class="hidden max-w-[15rem] truncate text-[11px] text-white/70 xl:block">{{ $tenantFullName }}</span>
            </span>
        </a>
    </div>

    {{-- Centro: buscador global. En pantallas angostas se abre desde el icono de la lupa --}}
    @if($tieneBuscador)
        <div class="relative min-w-0 flex-1 md:mx-auto md:max-w-xl" :class="movil ? 'block' : 'hidden md:block'">
            <form method="GET" action="{{ route('buscar') }}" role="search" class="relative flex items-center gap-1" @submit="alEnviar($event)">
                <div class="relative min-w-0 flex-1">
                    <label for="busqueda-q" class="sr-only">Buscar en el sistema</label>
                    <input id="busqueda-q" x-ref="q" type="search" name="q" x-model="q" maxlength="100"
                           autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search"
                           placeholder="Buscar personas, cultos, pantallas…"
                           class="upbar-search-input"
                           role="combobox" aria-autocomplete="list" aria-controls="busqueda-panel"
                           :aria-expanded="(abierto && hayConsulta) ? 'true' : 'false'"
                           :aria-activedescendant="activo >= 0 ? 'res-' + activo : null"
                           @focus="abrir()"
                           @keydown.arrow-down.prevent="mover(1)"
                           @keydown.arrow-up.prevent="mover(-1)"
                           @keydown.escape.prevent="escape()"
                           @keydown.enter="enter($event)">
                    <x-icono nombre="lupa" class="upbar-search-icon" />
                    <span class="upbar-search-kbd hidden md:block" x-show="!q" aria-hidden="true"><kbd class="kbd !border-white/30 !bg-white/10 !text-white/80">/</kbd></span>
                </div>
                <button type="button" class="upbar-btn md:hidden" @click="cerrarMovil()" aria-label="Cerrar la búsqueda" x-show="movil" x-cloak>
                    <x-icono nombre="cerrar" class="h-5 w-5" />
                </button>
            </form>

            {{-- Resultados: panel bajo el campo (en movil ocupa el ancho de la pantalla) --}}
            <div id="busqueda-panel" x-show="abierto" x-cloak x-transition.opacity.duration.100ms
                 @click.outside="cerrar()"
                 class="fixed inset-x-2 top-[calc(var(--upbar-h)+0.25rem)] z-50 flex max-h-[calc(100dvh-var(--upbar-h)-1rem)] flex-col overflow-hidden rounded-xl border border-gray-200 bg-white text-gray-800 shadow-popover md:absolute md:inset-x-0 md:top-full md:mt-2 md:max-h-[min(34rem,calc(100dvh-5rem))]">
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain" role="listbox" aria-label="Resultados de la búsqueda">

                    {{-- Antes de escribir: que se puede buscar --}}
                    <div x-show="!hayConsulta" class="p-4 text-sm text-gray-600">
                        <p class="font-semibold text-gray-900">¿Qué estás buscando?</p>
                        <ul class="mt-2 space-y-1.5">
                            <li><span class="font-medium text-gray-800">Personas:</span> nombre, PIN o teléfono</li>
                            <li><span class="font-medium text-gray-800">Cultos:</span> por fecha (<span class="whitespace-nowrap">12/10</span>, octubre, domingo)</li>
                            <li><span class="font-medium text-gray-800">Pantallas y reportes:</span> recuento, asistencia, dashboard…</li>
                            <li><span class="font-medium text-gray-800">Manual:</span> “cómo cerrar un culto”, “agregar persona”…</li>
                        </ul>
                    </div>

                    {{-- Buscando --}}
                    <div x-show="hayConsulta && cargando && total === 0" class="flex items-center gap-3 p-4 text-sm text-gray-500" role="status">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-brand-600"></span>
                        Buscando…
                    </div>

                    {{-- Sin resultados --}}
                    <div x-show="hayConsulta && !cargando && !fallo && buscado === q.trim() && total === 0" class="p-4 text-sm text-gray-600" role="status">
                        <p class="font-semibold text-gray-900">No encontramos “<span x-text="q.trim()"></span>”</p>
                        <p class="mt-1">Prueba con otra palabra, un nombre completo o una fecha como <span class="whitespace-nowrap font-medium">12/10</span>.</p>
                        @if($tieneManual)
                            <a href="{{ route('manual.index') }}" class="link mt-2 inline-flex items-center gap-1">Abrir el manual de usuario</a>
                        @endif
                    </div>

                    {{-- Error de red --}}
                    <div x-show="fallo" class="p-4 text-sm text-red-700" role="alert">
                        No se pudo buscar ahora. Revisa tu conexión e inténtalo de nuevo.
                    </div>

                    {{-- Resultados agrupados --}}
                    <template x-for="g in grupos" :key="g.clave">
                        <div role="group" :aria-label="g.titulo">
                            <div class="sticky top-0 z-10 bg-gray-50 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-500" x-text="g.titulo"></div>
                            <template x-for="it in g.items" :key="it.i">
                                <a :href="it.url" :id="'res-' + it.i" role="option" :aria-selected="activo === it.i ? 'true' : 'false'"
                                   @mouseenter="activo = it.i" @click="cerrar()"
                                   class="flex min-h-[48px] items-center gap-3 border-l-2 px-3 py-2"
                                   :class="activo === it.i ? 'border-brand-600 bg-brand-50' : 'border-transparent'">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600" :class="activo === it.i ? '!bg-white !text-brand-700' : ''">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path :d="it.trazo"></path></svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-gray-900">
                                            <template x-for="(p, k) in partes(it.titulo)" :key="k"><span :class="p.m ? 'rounded-sm bg-yellow-100 text-gray-900' : ''" x-text="p.t"></span></template>
                                        </span>
                                        <span class="block truncate text-xs text-gray-500" x-text="it.detalle"></span>
                                    </span>
                                    <svg class="hidden h-4 w-4 shrink-0 text-gray-400 sm:block" :class="activo === it.i ? '!text-brand-600' : ''" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ \App\Support\Iconos::trazo('flecha-derecha') }}"></path></svg>
                                </a>
                            </template>
                        </div>
                    </template>

                    <a x-show="total > 0" x-cloak :href="verTodo" :id="'res-' + total" role="option" :aria-selected="activo === total ? 'true' : 'false'"
                       @mouseenter="activo = total" @click="cerrar()"
                       class="flex min-h-[44px] items-center justify-between gap-3 border-l-2 border-t border-t-gray-100 px-3 py-2 text-sm font-medium text-brand-700"
                       :class="activo === total ? 'border-l-brand-600 bg-brand-50' : 'border-l-transparent'">
                        <span>Ver todos los resultados de “<span x-text="buscado"></span>”</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ \App\Support\Iconos::trazo('flecha-derecha') }}"></path></svg>
                    </a>
                </div>

                <div class="hidden items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-3 py-2 text-xs text-gray-500 md:flex">
                    <span class="flex items-center gap-1.5"><kbd class="kbd">↑</kbd><kbd class="kbd">↓</kbd> moverte <kbd class="kbd ml-1.5">Enter</kbd> abrir <kbd class="kbd ml-1.5">Esc</kbd> cerrar</span>
                    <span class="flex items-center gap-1.5"><kbd class="kbd">/</kbd> buscar desde cualquier pantalla</span>
                </div>
            </div>
        </div>
    @else
        <div class="flex-1"></div>
    @endif

    {{-- Derecha: lupa (angosto), manual, usuario y salir --}}
    <div class="ml-auto flex shrink-0 items-center gap-0.5 sm:gap-1" :class="movil ? 'hidden md:flex' : ''">
        @if($tieneBuscador)
            <button type="button" class="upbar-btn md:hidden" @click="abrirMovil()" aria-label="Buscar en el sistema">
                <x-icono nombre="lupa" class="h-5 w-5" />
            </button>
        @endif

        @if($tieneManual)
            <a href="{{ route('manual.index') }}" class="upbar-btn" title="Manual de usuario" aria-label="Manual de usuario">
                <x-icono nombre="manual" class="h-5 w-5" />
                <span class="hidden lg:inline">Manual</span>
            </a>
        @endif

        <span class="mx-1 hidden h-6 w-px bg-white/20 md:block" aria-hidden="true"></span>

        {{-- Usuario y rol --}}
        <div class="relative" x-data="{ abierto: false }" @keydown.escape.window="abierto = false" @click.outside="abierto = false">
            <button type="button" class="upbar-btn !gap-2.5 !px-1.5 sm:!px-2" @click="abierto = !abierto" :aria-expanded="abierto ? 'true' : 'false'" aria-haspopup="menu" aria-label="Menu de {{ $u->name }}">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/15 text-xs font-bold ring-1 ring-inset ring-white/25">{{ $iniciales }}</span>
                <span class="hidden min-w-0 text-left leading-tight lg:block">
                    <span class="block max-w-[10rem] truncate text-sm font-semibold">{{ $u->name }}</span>
                    <span class="block max-w-[10rem] truncate text-xs font-normal text-white/70">{{ $u->rol_nombre }}</span>
                </span>
                <x-icono nombre="flecha-abajo" class="hidden h-4 w-4 opacity-70 lg:block" />
            </button>

            <div x-show="abierto" x-cloak x-transition.opacity.duration.100ms role="menu"
                 class="absolute right-0 top-full z-50 mt-2 w-72 overflow-hidden rounded-xl border border-gray-200 bg-white text-gray-800 shadow-popover">
                <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                    <p class="truncate text-sm font-semibold text-gray-900">{{ $u->name }}</p>
                    <p class="truncate text-xs text-gray-500">{{ $u->email ?? $u->usuario }}</p>
                    <span class="badge-brand mt-2">{{ $u->rol_nombre }}</span>
                </div>
                <div class="py-1">
                    @if($permisosMenu['miPerfil'])
                        <a href="{{ route('mi-perfil.index') }}" role="menuitem" class="flex min-h-[44px] items-center gap-3 px-4 py-2 text-sm hover:bg-gray-50">
                            <x-icono nombre="usuario" class="h-5 w-5 text-gray-500" />
                            <span><span class="block font-medium text-gray-900">Mi perfil</span><span class="block text-xs text-gray-500">Mis promesas y compromisos</span></span>
                        </a>
                    @endif
                    @if(Route::has('profile.edit'))
                        <a href="{{ route('profile.edit') }}" role="menuitem" class="flex min-h-[44px] items-center gap-3 px-4 py-2 text-sm hover:bg-gray-50">
                            <x-icono nombre="ajustes" class="h-5 w-5 text-gray-500" />
                            <span><span class="block font-medium text-gray-900">Mi cuenta</span><span class="block text-xs text-gray-500">Nombre, correo y contraseña</span></span>
                        </a>
                    @endif
                    @if($tieneManual)
                        <a href="{{ route('manual.index') }}" role="menuitem" class="flex min-h-[44px] items-center gap-3 px-4 py-2 text-sm hover:bg-gray-50">
                            <x-icono nombre="manual" class="h-5 w-5 text-gray-500" />
                            <span class="font-medium text-gray-900">Manual de usuario</span>
                        </a>
                    @endif
                </div>
                <form method="POST" action="{{ route('logout') }}" class="js-logout border-t border-gray-100 py-1 md:hidden">
                    @csrf
                    <button type="submit" role="menuitem" class="flex min-h-[44px] w-full items-center gap-3 px-4 py-2 text-left text-sm font-medium text-red-700 hover:bg-red-50">
                        <x-icono nombre="salir" class="h-5 w-5" />
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>

        {{-- Salir: siempre a un clic en pantallas medianas y grandes --}}
        <form method="POST" action="{{ route('logout') }}" class="js-logout hidden md:block">
            @csrf
            <button type="submit" class="upbar-btn" title="Cerrar sesión" aria-label="Cerrar sesión">
                <x-icono nombre="salir" class="h-5 w-5" />
                <span class="hidden xl:inline">Salir</span>
            </button>
        </form>
    </div>
</header>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('buscadorGlobal', (cfg) => ({
            url: cfg.url,
            q: cfg.q || '',
            abierto: false,
            movil: false,
            cargando: false,
            fallo: false,
            grupos: [],
            total: 0,
            verTodo: '',
            buscado: '',
            activo: -1,
            temporizador: null,
            peticion: null,

            get hayConsulta() { return this.q.trim().length >= 2; },
            get plano() { return this.grupos.flatMap((g) => g.items); },

            init() {
                this.$watch('q', () => this.alEscribir());
            },

            esMovil() { return window.matchMedia('(max-width: 767px)').matches; },

            abrir() { this.abierto = true; },

            cerrar() { this.abierto = false; this.activo = -1; },

            abrirMovil() {
                this.movil = true;
                this.abierto = true;
                this.$nextTick(() => this.$refs.q.focus());
            },

            cerrarMovil() { this.movil = false; this.cerrar(); },

            escape() {
                if (this.abierto) { this.cerrar(); return; }
                if (this.q) { this.q = ''; return; }
                this.$refs.q.blur();
                if (this.movil) { this.movil = false; }
            },

            // "/" o Ctrl+K desde cualquier pantalla (si no se esta escribiendo en otro campo)
            atajo(e) {
                if (!this.url) { return; }
                const t = e.target;
                const escribiendo = t && (['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName) || t.isContentEditable);
                const esSlash = e.key === '/' && !escribiendo && !e.ctrlKey && !e.metaKey && !e.altKey;
                const esCtrlK = (e.ctrlKey || e.metaKey) && !e.altKey && String(e.key).toLowerCase() === 'k';
                if (!esSlash && !esCtrlK) { return; }
                e.preventDefault();
                if (this.esMovil()) { this.movil = true; }
                this.$nextTick(() => { this.$refs.q.focus(); this.$refs.q.select(); });
            },

            alEscribir() {
                clearTimeout(this.temporizador);
                this.activo = -1;
                const q = this.q.trim();
                if (q.length < 2) {
                    if (this.peticion) { this.peticion.abort(); }
                    this.grupos = []; this.total = 0; this.buscado = ''; this.cargando = false; this.fallo = false;
                    return;
                }
                this.abierto = true;
                this.cargando = true;
                this.temporizador = setTimeout(() => this.buscar(q), 180);
            },

            async buscar(q) {
                if (this.peticion) { this.peticion.abort(); }
                this.peticion = new AbortController();
                try {
                    const r = await fetch(this.url + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        signal: this.peticion.signal,
                    });
                    if (!r.ok) { throw new Error('HTTP ' + r.status); }
                    const d = await r.json();
                    let i = 0;
                    d.grupos.forEach((g) => g.items.forEach((it) => { it.i = i++; }));
                    this.grupos = d.grupos;
                    this.total = i;
                    this.verTodo = d.ver_todo;
                    this.buscado = q;
                    this.fallo = false;
                    this.activo = i > 0 ? 0 : -1;
                    this.cargando = false;
                } catch (e) {
                    if (e.name !== 'AbortError') { this.fallo = true; this.cargando = false; }
                }
            },

            // Flechas: recorre los resultados y, al final, "Ver todos"
            mover(d) {
                const n = this.total + (this.total > 0 ? 1 : 0);
                if (!this.hayConsulta || n === 0) { return; }
                this.abierto = true;
                this.activo = this.activo < 0 ? (d > 0 ? 0 : n - 1) : (this.activo + d + n) % n;
                this.$nextTick(() => document.getElementById('res-' + this.activo)?.scrollIntoView({ block: 'nearest' }));
            },

            // Enter abre el resultado marcado; sin resultado marcado envia el formulario (pagina de resultados)
            enter(e) {
                if (!this.abierto || this.activo < 0 || this.buscado !== this.q.trim()) { return; }
                e.preventDefault();
                const it = this.plano[this.activo];
                window.location.href = it ? it.url : this.verTodo;
            },

            alEnviar(e) {
                if (this.q.trim().length < 2) { e.preventDefault(); this.$refs.q.focus(); }
            },

            // Resalta las palabras buscadas (sin importar tildes ni mayusculas)
            partes(texto) {
                texto = String(texto ?? '');
                const palabras = this.buscado.split(/\s+/).filter((w) => w.length > 1);
                if (!palabras.length) { return [{ t: texto, m: false }]; }
                const clases = { a: '[aáàäâ]', e: '[eéèëê]', i: '[iíìïî]', o: '[oóòöô]', u: '[uúùüû]', n: '[nñ]' };
                const patron = palabras.map((w) => w.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
                    .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
                    .replace(/[aeioun]/g, (c) => clases[c])).join('|');
                return texto.split(new RegExp('(' + patron + ')', 'i')).filter((s) => s !== '').map((s, k, todos) => {
                    const m = new RegExp('^(' + patron + ')$', 'i').test(s);
                    return { t: s, m };
                });
            },
        }));
    });
</script>

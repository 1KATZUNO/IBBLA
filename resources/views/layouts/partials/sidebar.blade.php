{{--
    Menu lateral por rol. Las entradas vienen de App\Support\Navegacion\Menu (la misma fuente que usa el
    buscador global), cada una con la regla de permisos de su ruta: nadie ve un enlace que despues le
    responde 403.

    Desde 1024 px es una columna fija debajo de la barra superior (se puede ocultar con el boton de la
    barra y se recuerda). En tablet vertical y movil es un cajon que se abre encima del contenido.

    Variables que espera: $u, $menu, $tenantName, $tenantLogo, $tenantSocial
--}}
<aside id="sidebar" aria-label="Menú principal"
       class="sidebar-bg fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full transform flex-col text-white shadow-xl transition-transform duration-300 ease-out lg:static lg:z-auto lg:w-[17rem] lg:translate-x-0 lg:flex-shrink-0 lg:shadow-none xl:w-72">

    {{-- Solo en el cajon: la marca y cerrar. En pantallas grandes la marca esta en la barra superior --}}
    <div class="sidebar-border flex h-upbar flex-shrink-0 items-center justify-between border-b px-4 lg:hidden">
        <div class="flex min-w-0 items-center gap-3">
            <div class="h-10 w-10 flex-shrink-0 rounded-lg bg-white/10 p-1.5">
                <img src="{{ $tenantLogo }}" alt="Logo" class="h-full w-full object-contain">
            </div>
            <span class="truncate font-display text-lg font-bold tracking-tight">{{ $tenantName }}</span>
        </div>
        <button id="closeSidebar" type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-white transition-colors hover:bg-white/10" aria-label="Cerrar menú">
            <x-icono nombre="cerrar" class="h-6 w-6" />
        </button>
    </div>

    <nav class="scrollbar-primary flex-1 overflow-y-auto px-3 py-4">
        @foreach($menu as $grupo)
            <div class="{{ $loop->first ? '' : 'sidebar-border mt-3 border-t pt-3' }} space-y-0.5">
                @if($grupo['titulo'])
                    <div class="nav-section-title px-3 pb-1 text-xs font-semibold uppercase tracking-wider">{{ $grupo['titulo'] }}</div>
                @endif
                @foreach($grupo['items'] as $it)
                    @php $activo = request()->routeIs(...explode('|', $it['patron'])); @endphp
                    <a href="{{ route($it['ruta']) }}"
                       class="nav-link group flex min-h-[44px] items-center rounded-lg px-3 py-2.5 text-[15px] leading-5 transition-colors duration-200 {{ $activo ? 'nav-item-active' : 'nav-item-default' }}"
                       @if($activo) aria-current="page" @endif>
                        <x-icono :nombre="$it['icono']" class="mr-3 h-5 w-5 flex-shrink-0" />
                        <span class="font-medium">{{ $it['etiqueta'] }}</span>
                        {{-- Contador de pendientes de WhatsApp: se incluye solo si la vista existe --}}
                        @includeWhen($it['ruta'] === 'avisos.index' && view()->exists('components.avisos-pendientes-badge'), 'components.avisos-pendientes-badge', ['attributes' => new \Illuminate\View\ComponentAttributeBag(['class' => 'ml-auto'])])
                    </a>
                @endforeach
            </div>
        @endforeach

        <!-- Redes Sociales -->
        @if(!empty($tenantSocial))
            <div class="sidebar-border mt-4 border-t pt-4">
                <p class="nav-text-tertiary mb-3 text-center text-xs font-medium">Síguenos</p>
                <div class="flex items-center justify-center gap-3">
                    @if(!empty($tenantSocial['instagram']))
                        <a href="{{ $tenantSocial['instagram'] }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram" class="flex h-11 w-11 items-center justify-center rounded-lg bg-gradient-to-br from-gray-600 via-pink-500 to-orange-400 transition-opacity hover:opacity-80">
                            <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                    @endif
                    @if(!empty($tenantSocial['facebook']))
                        <a href="{{ $tenantSocial['facebook'] }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook" class="avatar-bg flex h-11 w-11 items-center justify-center rounded-lg transition-opacity hover:opacity-80">
                            <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                    @endif
                    @if(!empty($tenantSocial['youtube']))
                        <a href="{{ $tenantSocial['youtube'] }}" target="_blank" rel="noopener noreferrer" aria-label="YouTube" class="flex h-11 w-11 items-center justify-center rounded-lg bg-red-600 transition-opacity hover:opacity-80">
                            <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </nav>

    {{-- Solo en el cajon: usuario y salir (en pantallas grandes estan en la barra superior) --}}
    <div class="sidebar-border sidebar-user-bg flex-shrink-0 border-t p-3 lg:hidden">
        <div class="flex items-center gap-3">
            <div class="avatar-bg flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg">
                <span class="text-sm font-bold text-white">{{ \App\Support\Tema::iniciales($u->name) }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-white">{{ $u->name }}</p>
                <p class="nav-text-secondary truncate text-xs capitalize">{{ $u->rol_nombre }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="js-logout flex-shrink-0">
                @csrf
                <button type="submit" class="btn-sidebar inline-flex min-h-[44px] items-center gap-2 rounded-lg px-3 text-sm font-medium text-white transition-colors duration-200" title="Cerrar sesión">
                    <x-icono nombre="salir" class="h-5 w-5" />
                    <span>Salir</span>
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- Fondo oscuro detras del cajon (tablet vertical y movil) --}}
<div id="sidebarOverlay" class="fixed inset-0 z-40 hidden bg-gray-900/50 transition-opacity duration-300 lg:hidden"></div>

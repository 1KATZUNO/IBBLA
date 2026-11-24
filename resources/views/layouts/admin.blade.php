{{--
    Layout principal de la app.

    Estructura (todo con 100dvh, el scroll es solo del <main>):
      barra superior fija (partials/upbar)  ->  logo, buscador global, manual, usuario y salir
      menu lateral (partials/sidebar)       ->  por rol; cajon en tablet vertical y movil
      <main>                                ->  encabezado de pagina (partials/encabezado), avisos y contenido

    Secciones que usan las pantallas: page-title, page-subtitle, page-actions, sin-encabezado, content.
    Solo el equipo de UI toca este archivo y resources/views/layouts (ver docs/UI.md).
--}}
@php
    $currentTenant = tenant();
    $tenantColors = $currentTenant ? $currentTenant->colors : \App\Models\Tenant::COLOR_THEMES['blue'];
    $tenantName = $currentTenant ? $currentTenant->siglas : 'Admin';
    $tenantFullName = $currentTenant ? $currentTenant->nombre : 'Sistema de Administración';
    $tenantLogo = $currentTenant ? $currentTenant->logo_url : asset('images/Logo.png');
    $tenantSocial = $currentTenant && $currentTenant->redes_sociales ? $currentTenant->redes_sociales : [];
    $esAppMovil = isset($isMobileApp) && $isMobileApp;

    // Menu lateral: App\Support\Navegacion\Menu (misma fuente que el buscador global)
    $u = Auth::user();
    $menu = \App\Support\Navegacion\Menu::grupos($u);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover{{ $esAppMovil ? ', maximum-scale=1, user-scalable=no' : '' }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $tenantColors['900'] ?? $tenantColors['700'] }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>{{ $tenantName }} - @yield('page-title', 'Sistema de Administración')</title>

    {{-- Recordar si el menu lateral esta oculto en pantallas grandes (antes de pintar, para que no parpadee) --}}
    <script>
        try { if (localStorage.getItem('ibbla.sidebar') === 'oculto') { document.documentElement.classList.add('sidebar-oculto'); } } catch (e) {}
    </script>

    <!-- PWA Manifest -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $tenantLogo }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ $tenantLogo }}">
    <link rel="shortcut icon" href="{{ $tenantLogo }}">
    <link rel="apple-touch-icon" href="{{ asset('images/Logo.png') }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Colores de la iglesia: --t-50 ... --t-900 y sus canales RGB (ver docs/UI.md) --}}
    <style>
        {!! \App\Support\Tema::variablesCss($tenantColors) !!}
        /* En tablet horizontal y PC el menu lateral se puede ocultar para ganar ancho */
        @media (min-width: 1024px) {
            html.sidebar-oculto #sidebar { display: none; }
        }
    </style>

    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-50 overflow-hidden {{ $esAppMovil ? 'ibbsc-mobile-app' : '' }}">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-gray-900 focus:shadow-popover">Saltar al contenido</a>

    <div class="flex h-screen h-dvh flex-col overflow-hidden">
        @include('partials.aviso-ambiente')
        @unless($esAppMovil)
            @include('layouts.partials.upbar')
        @endunless

        <div class="relative flex min-h-0 flex-1">
            @unless($esAppMovil)
                @include('layouts.partials.sidebar')
            @endunless

            <!-- Loading Overlay para Logout -->
            <div id="logoutOverlay" class="overlay-bg fixed inset-0 z-[80] flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
                <div class="text-center">
                    <div class="mb-6">
                        <img src="{{ $tenantLogo }}" alt="{{ $tenantName }}" class="mx-auto h-24 w-24">
                    </div>

                    <!-- Spinner simple -->
                    <div class="mx-auto mb-6 h-12 w-12">
                        <div class="h-full w-full animate-spin rounded-full border-4 border-white/30 border-t-white"></div>
                    </div>

                    <h2 class="mb-2 font-display text-2xl font-bold text-white">Hasta pronto</h2>
                    <p class="nav-text-secondary">Cerrando sesión...</p>
                </div>
            </div>

            <!-- Main Content -->
            <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
                @if($esAppMovil)
                    {{-- App movil (WebView): sin barra superior, solo el titulo --}}
                    <header class="z-10 flex-shrink-0 border-b border-gray-200 bg-white shadow-sm">
                        <div class="flex h-12 items-center gap-3 px-4">
                            <h2 class="min-w-0 flex-1 truncate text-center font-display text-lg font-bold text-gray-900">@yield('page-title', 'Dashboard')</h2>
                        </div>
                    </header>
                @endif

                <main id="contenido" tabindex="-1" class="app-main flex-1 animate-fade-in overflow-y-auto overflow-x-hidden p-4 focus:outline-none sm:p-6 xl:p-8 {{ $esAppMovil ? 'pb-20' : '' }}">
                    @unless($esAppMovil)
                        @include('layouts.partials.encabezado')
                    @endunless

                    @include('layouts.partials.avisos')

                    @yield('content')
                </main>
            </div>
        </div>
    </div>

    @if($esAppMovil)
        @include('layouts.partials.mobile-bottom-nav')
    @endif

    <script>
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            const overlay = document.getElementById('sidebarOverlay');
            const toggleBtn = document.getElementById('toggleSidebar');
            const closeBtn = document.getElementById('closeSidebar');
            const pantallaGrande = window.matchMedia('(min-width: 1024px)');

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            }

            // Tablet vertical / movil: abre el menu encima del contenido.
            // Tablet horizontal / PC: oculta o muestra el menu fijo y lo recuerda.
            toggleBtn?.addEventListener('click', () => {
                if (pantallaGrande.matches) {
                    const oculto = document.documentElement.classList.toggle('sidebar-oculto');
                    try { localStorage.setItem('ibbla.sidebar', oculto ? 'oculto' : 'visible'); } catch (e) {}
                } else if (sidebar.classList.contains('-translate-x-full')) {
                    openSidebar();
                } else {
                    closeSidebar();
                }
            });
            closeBtn?.addEventListener('click', closeSidebar);
            overlay?.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !pantallaGrande.matches) {
                    closeSidebar();
                }
            });

            // Al pasar de tablet vertical a horizontal (o al girar), el cajon no debe quedar abierto
            pantallaGrande.addEventListener('change', () => closeSidebar());

            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    if (!pantallaGrande.matches) {
                        closeSidebar();
                    }
                });
            });
        }

        // Cerrar sesión: pantalla de despedida mientras se envia el formulario
        document.querySelectorAll('form.js-logout').forEach((form) => {
            form.addEventListener('submit', () => {
                const logoutOverlay = document.getElementById('logoutOverlay');
                if (logoutOverlay) {
                    logoutOverlay.classList.remove('opacity-0', 'pointer-events-none');
                    logoutOverlay.classList.add('opacity-100');
                }
            });
        });
    </script>

    @stack('scripts')

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js');
        }
    </script>
</body>
</html>

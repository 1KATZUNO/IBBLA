@php
    $currentTenant = tenant();
    $tenantColors = $currentTenant ? $currentTenant->colors : \App\Models\Tenant::COLOR_THEMES['blue'];
    $tenantName = $currentTenant ? $currentTenant->siglas : 'Admin';
    $tenantFullName = $currentTenant ? $currentTenant->nombre : 'Sistema de Administracion';
    $tenantLogo = $currentTenant ? $currentTenant->logo_url : asset('images/Logo.png');
    $tenantSocial = $currentTenant && $currentTenant->redes_sociales ? $currentTenant->redes_sociales : [];
    $esAppMovil = isset($isMobileApp) && $isMobileApp;

    // Menu lateral. Cada entrada se muestra con la misma regla de permisos que protege su ruta,
    // para que nadie vea un enlace que despues le responde 403.
    $u = Auth::user();
    $puedeRecuento = $u->canAccessRecuento();
    $puedeAsistencia = $u->canAccessAsistencia();
    $puedeAppClase = $u->isAdmin() || $u->isSuperAdmin() || $u->clase_asistencia_id
        || ($u->persona && $u->persona->clasesAsistencia()->wherePivot('es_maestro', true)->exists());

    $iconos = [
        'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'regalo' => 'M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 00-2 2v6a2 2 0 002 2h14a2 2 0 002-2v-6a2 2 0 00-2-2',
        'usuario' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'check' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        'libro' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
        'barras' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        'documento' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'grupo' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        'reporte' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'escudo' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        'calendario' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'ajustes' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4',
        'personas' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        'estrella' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
        'subir' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
        'lupa' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'mensaje' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    ];

    // [titulo de grupo|null, [[ruta, etiqueta, icono, patron activo, visible, sub-item]]]
    $menu = [
        [null, [
            ['principal', 'Principal', 'home', 'principal', true, false],
            ['cumpleaneros.index', 'Cumpleañeros', 'regalo', 'cumpleaneros.*', true, false],
            ['mi-perfil.index', 'Yo', 'usuario', 'mi-perfil.*', $u->isMiembro() || in_array($u->rol, ['servidor', 'tesorero', 'asistente']), false],
            ['marcar-asistencia.index', 'Marcar Asistencia', 'check', 'marcar-asistencia.*', $u->hasPermission('marcar_asistencia'), false],
            ['clase-app.index', 'App de Clases', 'libro', 'clase-app.*', $puedeAppClase, false],
        ]],
        // Lo que se usa cada culto va primero
        ['Registro', [
            ['recuento.index', 'Recuento', 'documento', 'recuento.*', $puedeRecuento, false],
            ['asistencia.index', 'Asistencia', 'grupo', 'asistencia.index|asistencia.create|asistencia.edit|asistencia.show', $puedeAsistencia, false],
        ]],
        ['Consultas', [
            ['dashboard', 'Dashboard', 'barras', 'dashboard', $puedeRecuento, false],
            ['dashboard.promesas', 'Dashboard de promesas', 'barras', 'dashboard.promesas', $puedeRecuento, false],
            ['dashboard.asistencia', 'Dashboard de asistencia', 'barras', 'dashboard.asistencia', $puedeRecuento, false],
            // Reportes: tesoreria entra al indice; quien solo maneja asistencia va directo a su reporte (el indice es solo de tesoreria).
            [$puedeRecuento ? 'ingresos-asistencia.index' : 'ingresos-asistencia.asistencia', 'Reportes', 'reporte', 'ingresos-asistencia.index|ingresos-asistencia.ingresos|ingresos-asistencia.asistencia|ingresos-asistencia.promesas*|ingresos-asistencia.pdf-*|ingresos-asistencia.excel-*', $puedeRecuento || $puedeAsistencia, false],
            // Reporte por clase y rango (rama de asistencia por rango): es de admin/asistente, el asistente no entra al indice de Reportes
            ['ingresos-asistencia.asistencia-clases', 'Asistencia por clase', 'grupo', 'ingresos-asistencia.asistencia-clases*', $puedeAsistencia && Route::has('ingresos-asistencia.asistencia-clases'), false],
            // Panel de avisos de WhatsApp a lideres (rama de WhatsApp): admin y tesorero, con contador de pendientes
            ['avisos.index', 'Avisos de WhatsApp', 'mensaje', 'avisos.*', $puedeRecuento && Route::has('avisos.index'), false],
        ]],
        ['Super Admin', [
            ['super-admin.dashboard', 'Panel Super Admin', 'escudo', 'super-admin.*', $u->isSuperAdmin(), false],
        ]],
        ['Administracion', [
            ['cultos.index', 'Gestionar Cultos', 'calendario', 'cultos.*', $u->canAccessAdmin(), false],
            ['admin.clases.index', 'Gestionar Clases', 'ajustes', 'admin.clases.*', $u->canAccessAdmin(), false],
            ['personas.index', 'Gestionar Personas', 'personas', 'personas.*|compromisos.*', $u->canAccessAdmin(), false],
            ['admin.ministerios.index', 'Ministerios', 'estrella', 'admin.ministerios.*', $u->canAccessAdmin(), false],
            ['usuarios.index', 'Gestionar Usuarios', 'personas', 'usuarios.*', $u->canAccessAdmin(), false],
            ['admin.servidores.index', 'Servidores', 'grupo', 'admin.servidores.*', $u->canAccessAdmin(), false],
            ['asistencia.importar.index', 'Cargar Historico', 'subir', 'asistencia.importar.*', $u->canAccessAdmin(), false],
            ['admin.auditoria.index', 'Auditoria', 'lupa', 'admin.auditoria.index', $u->canAccessAdmin(), false],
            // Pantalla de la rama de historial de ingresos: aparece cuando su ruta exista
            ['admin.auditoria.ingresos', 'Historial de ingresos', 'usuario', 'admin.auditoria.ingresos*', $u->canAccessAdmin() && Route::has('admin.auditoria.ingresos'), false],
        ]],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover{{ $esAppMovil ? ', maximum-scale=1, user-scalable=no' : '' }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $tenantColors['700'] }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>{{ $tenantName }} - @yield('page-title', 'Sistema de Administracion')</title>

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
    <link rel="apple-touch-icon" href="{{ asset('images/angeda_deep_ocean.png') }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --t-50: {{ $tenantColors['50'] }};
            --t-100: {{ $tenantColors['100'] }};
            --t-200: {{ $tenantColors['200'] }};
            --t-300: {{ $tenantColors['300'] }};
            --t-400: {{ $tenantColors['400'] }};
            --t-500: {{ $tenantColors['500'] }};
            --t-600: {{ $tenantColors['600'] }};
            --t-700: {{ $tenantColors['700'] }};
            --t-800: {{ $tenantColors['800'] }};
            --t-900: {{ $tenantColors['900'] }};
        }
        .sidebar-bg { background-color: var(--t-800); }
        .sidebar-border { border-color: var(--t-700); }
        .sidebar-user-bg { background-color: var(--t-900); }
        .nav-item-active { background-color: var(--t-700); color: #fff; }
        .nav-item-default { color: var(--t-100); }
        .nav-item-default:hover { background-color: color-mix(in srgb, var(--t-700) 50%, transparent); }
        .nav-section-title { color: var(--t-300); }
        .nav-text-secondary { color: var(--t-200); }
        .nav-text-tertiary { color: var(--t-300); }
        .avatar-bg { background-color: var(--t-600); }
        .btn-sidebar { background-color: var(--t-700); }
        .btn-sidebar:hover { background-color: var(--t-600); }
        .overlay-bg { background-color: var(--t-800); }
        /* En tablet horizontal y PC el menu lateral se puede ocultar para ganar ancho */
        @media (min-width: 1024px) {
            html.sidebar-oculto #sidebar { display: none; }
        }
    </style>

    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-50 overflow-hidden {{ $esAppMovil ? 'ibbsc-mobile-app' : '' }}">
    <div class="flex h-screen h-dvh overflow-hidden relative z-10">
        @if(!$esAppMovil)
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 lg:w-[17rem] xl:w-72 sidebar-bg text-white transform transition-transform duration-300 ease-out -translate-x-full lg:translate-x-0 lg:static lg:inset-0 flex flex-col shadow-xl" aria-label="Menu principal">
            <div class="flex items-center justify-between h-16 px-4 border-b sidebar-border flex-shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 bg-white/10 rounded-lg p-1.5 flex-shrink-0">
                        <img src="{{ $tenantLogo }}" alt="Logo" class="w-full h-full object-contain">
                    </div>
                    <h1 class="text-lg font-display font-bold tracking-tight truncate">{{ $tenantName }} Admin</h1>
                </div>
                <button id="closeSidebar" type="button" class="lg:hidden inline-flex items-center justify-center w-11 h-11 rounded-lg text-white hover:bg-white/10 transition-colors" aria-label="Cerrar menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <nav class="flex-1 px-3 py-4 overflow-y-auto scrollbar-primary">
                @foreach($menu as [$tituloGrupo, $items])
                    @php $visibles = array_filter($items, fn ($it) => $it[4]); @endphp
                    @if(count($visibles))
                        <div class="{{ $loop->first ? '' : 'pt-3 mt-3 border-t sidebar-border' }} space-y-0.5">
                            @if($tituloGrupo)
                                <div class="px-3 pb-1 text-xs font-semibold nav-section-title uppercase tracking-wider">{{ $tituloGrupo }}</div>
                            @endif
                            @foreach($visibles as [$ruta, $etiqueta, $icono, $patron, $visible, $esSub])
                                @php $activo = request()->routeIs(...explode('|', $patron)); @endphp
                                <a href="{{ route($ruta) }}"
                                   class="nav-link group flex items-center min-h-[44px] rounded-lg text-[15px] leading-5 transition-colors duration-200 {{ $esSub ? 'pl-11 pr-3 py-2 text-sm' : 'px-3 py-2.5' }} {{ $activo ? 'nav-item-active' : 'nav-item-default' }}"
                                   @if($activo) aria-current="page" @endif>
                                    @unless($esSub)
                                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconos[$icono] }}"></path>
                                        </svg>
                                    @endunless
                                    <span class="font-medium">{{ $etiqueta }}</span>
                                    {{-- Contador de la rama de WhatsApp: se incluye en tiempo de ejecucion solo si la vista existe --}}
                                    @includeWhen($ruta === 'avisos.index' && view()->exists('components.avisos-pendientes-badge'), 'components.avisos-pendientes-badge', ['attributes' => new \Illuminate\View\ComponentAttributeBag(['class' => 'ml-auto'])])
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endforeach

                <!-- Redes Sociales -->
                @if(!empty($tenantSocial))
                <div class="pt-4 mt-4 border-t sidebar-border">
                    <p class="text-xs nav-text-tertiary mb-3 text-center font-medium">Siguenos</p>
                    <div class="flex items-center justify-center gap-3">
                        @if(!empty($tenantSocial['instagram']))
                        <a href="{{ $tenantSocial['instagram'] }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram" class="w-11 h-11 rounded-lg bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400 flex items-center justify-center hover:opacity-80 transition-opacity">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                        </a>
                        @endif
                        @if(!empty($tenantSocial['facebook']))
                        <a href="{{ $tenantSocial['facebook'] }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook" class="w-11 h-11 rounded-lg avatar-bg flex items-center justify-center hover:opacity-80 transition-opacity">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                        @endif
                        @if(!empty($tenantSocial['youtube']))
                        <a href="{{ $tenantSocial['youtube'] }}" target="_blank" rel="noopener noreferrer" aria-label="YouTube" class="w-11 h-11 rounded-lg bg-red-600 flex items-center justify-center hover:opacity-80 transition-opacity">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                            </svg>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
            </nav>

            <!-- Usuario y cierre de sesion: una sola fila para dejar mas espacio al menu -->
            <div class="p-3 border-t sidebar-border sidebar-user-bg flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg avatar-bg flex items-center justify-center flex-shrink-0">
                        <span class="text-sm font-bold text-white">{{ substr($u->name, 0, 2) }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white truncate">{{ $u->name }}</p>
                        <p class="text-xs nav-text-secondary capitalize truncate">{{ $u->rol_nombre }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" id="logoutForm" class="flex-shrink-0">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 min-h-[44px] px-3 text-sm font-medium text-white btn-sidebar rounded-lg transition-colors duration-200" title="Cerrar sesion">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            <span>Salir</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Overlay para mobile/tablet vertical -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-gray-900/50 z-40 lg:hidden hidden transition-opacity duration-300"></div>
        @endif {{-- !isMobileApp --}}

        <!-- Loading Overlay para Logout -->
        <div id="logoutOverlay" class="fixed inset-0 overlay-bg z-[80] flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
            <div class="text-center">
                <div class="mb-6">
                    <img src="{{ $tenantLogo }}" alt="{{ $tenantName }}" class="w-24 h-24 mx-auto">
                </div>

                <!-- Spinner simple -->
                <div class="w-12 h-12 mx-auto mb-6">
                    <div class="w-full h-full border-4 border-white/30 border-t-white rounded-full animate-spin"></div>
                </div>

                <h2 class="text-2xl font-display font-bold text-white mb-2">Hasta pronto</h2>
                <p class="nav-text-secondary">Cerrando sesion...</p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 min-w-0 flex flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="bg-white border-b border-gray-200 shadow-sm z-10 flex-shrink-0">
                <div class="flex items-center gap-3 {{ $esAppMovil ? 'h-12 px-4' : 'h-16 px-3 sm:px-4 xl:px-6' }}">
                    @if(!$esAppMovil)
                    <button id="toggleSidebar" type="button" class="inline-flex items-center justify-center w-11 h-11 -ml-1 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors flex-shrink-0" aria-controls="sidebar" aria-label="Mostrar u ocultar el menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    @endif
                    <h2 class="{{ $esAppMovil ? 'text-lg flex-1 text-center' : 'text-lg sm:text-xl flex-1' }} min-w-0 truncate font-display font-bold text-gray-900">@yield('page-title', 'Dashboard')</h2>
                    <div class="hidden md:block text-sm text-gray-500 flex-shrink-0">
                        {{ \Carbon\Carbon::now()->locale('es')->isoFormat('dddd, D [de] MMMM') }}
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 sm:p-6 xl:p-8 animate-fade-in {{ $esAppMovil ? 'pb-20' : '' }}">
                {{-- Mensajes del sistema: siempre arriba del contenido, que es donde cae la pantalla despues de guardar --}}
                @php
                    $avisos = [
                        'success' => ['alert-success', 'text-green-600', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'Listo'],
                        'error' => ['alert-error', 'text-red-600', 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'Error'],
                        'warning' => ['alert-warning', 'text-yellow-600', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 'Atencion'],
                        'info' => ['alert-info', 'text-blue-600', 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'Aviso'],
                    ];
                @endphp
                @foreach($avisos as $clave => [$claseAviso, $colorIcono, $iconoAviso, $tituloAviso])
                    @if(session($clave))
                        <div class="{{ $claseAviso }} mb-6 flex items-start gap-3 animate-slide-in-right" role="{{ $clave === 'error' ? 'alert' : 'status' }}" data-aviso>
                            <svg class="w-5 h-5 mt-0.5 flex-shrink-0 {{ $colorIcono }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconoAviso }}"></path>
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold">{{ $tituloAviso }}</p>
                                <p class="text-sm">{{ session($clave) }}</p>
                            </div>
                            <button type="button" class="btn-icon -my-2 -mr-3 text-current opacity-70 hover:opacity-100 hover:bg-black/5" onclick="this.closest('[data-aviso]').remove()" aria-label="Cerrar mensaje">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    @endif
                @endforeach

                @if($errors->any())
                    <div class="alert-error mb-6 flex items-start gap-3 animate-slide-in-right" role="alert" data-aviso>
                        <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold">No se pudo guardar. Revisa estos datos:</p>
                            <ul class="mt-1 text-sm list-disc list-inside space-y-0.5">
                                @foreach($errors->all() as $mensajeError)
                                    <li>{{ $mensajeError }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <button type="button" class="btn-icon -my-2 -mr-3 text-current opacity-70 hover:opacity-100 hover:bg-black/5" onclick="this.closest('[data-aviso]').remove()" aria-label="Cerrar mensaje">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                @endif

                @yield('content')
            </main>
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

            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    if (!pantallaGrande.matches) {
                        closeSidebar();
                    }
                });
            });
        }

        document.getElementById('logoutForm')?.addEventListener('submit', function(e) {
            const logoutOverlay = document.getElementById('logoutOverlay');
            if (logoutOverlay) {
                logoutOverlay.classList.remove('opacity-0', 'pointer-events-none');
                logoutOverlay.classList.add('opacity-100');
            }
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

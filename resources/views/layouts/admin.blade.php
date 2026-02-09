<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1{{ isset($isMobileApp) && $isMobileApp ? ', maximum-scale=1, user-scalable=no' : '' }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(isset($isMobileApp) && $isMobileApp)
    <meta name="theme-color" content="#1D4ED8">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    @endif

    <title>@yield('title', 'IBBSC - Sistema de Administración')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/Logo.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/Logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/Logo.png') }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-50 overflow-hidden {{ isset($isMobileApp) && $isMobileApp ? 'ibbsc-mobile-app' : '' }}">
    <div class="flex h-screen overflow-hidden relative z-10">
        @if(!(isset($isMobileApp) && $isMobileApp))
        <!-- Sidebar - Azul corporativo sólido -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-blue-800 text-white transform transition-transform duration-300 ease-out -translate-x-full lg:translate-x-0 lg:static lg:inset-0 flex flex-col shadow-xl">
            <div class="flex items-center justify-between h-20 px-6 border-b border-blue-700 flex-shrink-0">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white/10 rounded-xl p-2">
                        <img src="{{ asset('images/Logo.png') }}" alt="Logo" class="w-full h-full object-contain">
                    </div>
                    <h1 class="text-xl font-display font-bold tracking-tight">IBBSC Admin</h1>
                </div>
                <button id="closeSidebar" class="lg:hidden text-white hover:text-blue-200 transition-colors p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto scrollbar-primary">
                <!-- Principal - Para todos los usuarios -->
                <a href="{{ route('principal') }}" class="nav-link group flex items-center px-4 py-3 rounded-lg transition-colors duration-200 {{ request()->routeIs('principal') ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700/50' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    <span class="font-medium">Principal</span>
                </a>

                @if(Auth::user()->isMiembro())
                <!-- Yo - Menú exclusivo para miembros -->
                <a href="{{ route('mi-perfil.index') }}" class="nav-link group flex items-center px-4 py-3 rounded-lg transition-colors duration-200 {{ request()->routeIs('mi-perfil.*') ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700/50' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span class="font-medium">Yo</span>
                </a>
                @endif

                @if(Auth::user()->canAccessRecuento())
                <!-- Dashboard - Solo para tesorería y admin -->
                <a href="{{ route('dashboard') }}" class="nav-link group flex items-center px-4 py-3 rounded-lg transition-colors duration-200 {{ request()->routeIs('dashboard') ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700/50' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span class="font-medium">Dashboard</span>
                </a>
                @endif

                @if(Auth::user()->canAccessRecuento())
                <a href="{{ route('recuento.index') }}" class="nav-link group flex items-center px-4 py-3 rounded-lg transition-colors duration-200 {{ request()->routeIs('recuento.*') ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700/50' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span class="font-medium">Recuento</span>
                </a>
                @endif

                @if(Auth::user()->canAccessAsistencia())
                <a href="{{ route('asistencia.index') }}" class="nav-link group flex items-center px-4 py-3 rounded-lg transition-colors duration-200 {{ request()->routeIs('asistencia.*') ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700/50' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span class="font-medium">Asistencia</span>
                </a>
                @endif

                @if(Auth::user()->canAccessRecuento() || Auth::user()->canAccessAsistencia())
                <a href="{{ route('ingresos-asistencia.index') }}" class="nav-link group flex items-center px-4 py-3 rounded-lg transition-colors duration-200 {{ request()->routeIs('ingresos-asistencia.*') ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700/50' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span class="font-medium">Reportes</span>
                </a>
                @endif

                @if(Auth::user()->rol === 'admin')
                <div class="pt-4 mt-4 border-t border-blue-700">
                    <div class="px-4 py-2 text-xs font-semibold text-blue-300 uppercase tracking-wider">Administración</div>

                    <a href="{{ route('cultos.index') }}" class="nav-link group flex items-center px-4 py-3 rounded-lg transition-colors duration-200 {{ request()->routeIs('cultos.*') ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700/50' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="font-medium">Gestionar Cultos</span>
                    </a>

                    <a href="{{ route('admin.clases.index') }}" class="nav-link group flex items-center px-4 py-3 rounded-lg transition-colors duration-200 {{ request()->routeIs('admin.clases.*') ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700/50' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                        </svg>
                        <span class="font-medium">Gestionar Clases</span>
                    </a>

                    <a href="{{ route('personas.index') }}" class="nav-link group flex items-center px-4 py-3 rounded-lg transition-colors duration-200 {{ request()->routeIs('personas.*') ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700/50' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span class="font-medium">Gestionar Personas</span>
                    </a>

                    <a href="{{ route('usuarios.index') }}" class="nav-link group flex items-center px-4 py-3 rounded-lg transition-colors duration-200 {{ request()->routeIs('usuarios.*') ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700/50' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span class="font-medium">Gestionar Usuarios</span>
                    </a>

                    <a href="{{ route('admin.auditoria.index') }}" class="nav-link group flex items-center px-4 py-3 rounded-lg transition-colors duration-200 {{ request()->routeIs('admin.auditoria.index') ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700/50' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="font-medium">Auditoría</span>
                    </a>
                </div>
                @endif
            </nav>

            <!-- User Profile Section -->
            <div class="p-4 border-t border-blue-700 bg-blue-900">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <div class="w-11 h-11 rounded-lg bg-blue-600 flex items-center justify-center">
                            <span class="text-sm font-bold text-white">{{ substr(Auth::user()->name, 0, 2) }}</span>
                        </div>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-semibold text-white truncate">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-blue-200 capitalize">{{ Auth::user()->rol }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mb-4" id="logoutForm">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2.5 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-600 transition-colors duration-200">
                        <span class="flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            Cerrar Sesión
                        </span>
                    </button>
                </form>

                <!-- Redes Sociales -->
                <div class="pt-4 border-t border-blue-700">
                    <p class="text-xs text-blue-300 mb-3 text-center font-medium">Síguenos</p>
                    <div class="flex items-center justify-center gap-3">
                        <a href="https://www.instagram.com/ibb_santacruz?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank" rel="noopener noreferrer" class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400 flex items-center justify-center hover:opacity-80 transition-opacity">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                        </a>
                        <a href="https://www.facebook.com/iglesia.biblica.bautista.santa.cruz" target="_blank" rel="noopener noreferrer" class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center hover:bg-blue-500 transition-colors">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Overlay para mobile -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-gray-900/50 z-40 lg:hidden hidden transition-opacity duration-300"></div>
        @endif {{-- !isMobileApp --}}

        <!-- Loading Overlay para Logout -->
        <div id="logoutOverlay" class="fixed inset-0 bg-blue-800 z-[80] flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
            <div class="text-center">
                <div class="mb-6">
                    <img src="{{ asset('images/Logo.png') }}" alt="IBBSC" class="w-24 h-24 mx-auto">
                </div>

                <!-- Spinner simple -->
                <div class="w-12 h-12 mx-auto mb-6">
                    <div class="w-full h-full border-4 border-white/30 border-t-white rounded-full animate-spin"></div>
                </div>

                <h2 class="text-2xl font-display font-bold text-white mb-2">Hasta pronto</h2>
                <p class="text-blue-200">Cerrando sesión...</p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="bg-white border-b border-gray-200 shadow-sm z-10">
                <div class="flex items-center justify-between {{ isset($isMobileApp) && $isMobileApp ? 'h-12 px-4' : 'h-16 px-4 sm:px-6 lg:px-8' }}">
                    @if(!(isset($isMobileApp) && $isMobileApp))
                    <button id="openSidebar" class="lg:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    @endif
                    <h2 class="{{ isset($isMobileApp) && $isMobileApp ? 'text-lg flex-1 text-center' : 'text-xl' }} font-display font-bold text-gray-900">@yield('page-title', 'Dashboard')</h2>
                    <div class="flex items-center gap-3">
                        <div class="hidden md:block text-sm text-gray-500">
                            {{ \Carbon\Carbon::now()->locale('es')->isoFormat('dddd, D [de] MMMM') }}
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 sm:p-6 lg:p-8 animate-fade-in {{ isset($isMobileApp) && $isMobileApp ? 'pb-20' : '' }}">
                @if(session('success'))
                    <div class="alert-success mb-6 flex items-start animate-slide-in-right">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="font-semibold">¡Éxito!</p>
                            <p class="text-sm">{{ session('success') }}</p>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert-error mb-6 flex items-start animate-slide-in-right">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="font-semibold">¡Error!</p>
                            <p class="text-sm">{{ session('error') }}</p>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @if(isset($isMobileApp) && $isMobileApp)
        @include('layouts.partials.mobile-bottom-nav')
    @endif

    <script>
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            const overlay = document.getElementById('sidebarOverlay');
            const openBtn = document.getElementById('openSidebar');
            const closeBtn = document.getElementById('closeSidebar');

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            }

            openBtn?.addEventListener('click', openSidebar);
            closeBtn?.addEventListener('click', closeSidebar);
            overlay?.addEventListener('click', closeSidebar);

            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 1024) {
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
</body>
</html>

{{-- Bottom Navigation para app movil --}}
<nav id="mobileBottomNav" class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 shadow-lg">
    <div class="flex items-center justify-around h-16">
        {{-- Principal - Todos --}}
        <a href="{{ route('principal') }}" class="mobile-nav-item {{ request()->routeIs('principal') ? 'text-blue-600' : 'text-gray-500' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            <span class="text-xs mt-1">Principal</span>
        </a>

        @if(Auth::user()->isMiembro())
            {{-- Mi Perfil - Miembros --}}
            <a href="{{ route('mi-perfil.index') }}" class="mobile-nav-item {{ request()->routeIs('mi-perfil.*') ? 'text-blue-600' : 'text-gray-500' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span class="text-xs mt-1">Mi Perfil</span>
            </a>
        @endif

        @if(Auth::user()->canAccessRecuento())
            {{-- Recuento - Admin/Tesorero --}}
            <a href="{{ route('recuento.index') }}" class="mobile-nav-item {{ request()->routeIs('recuento.*') ? 'text-blue-600' : 'text-gray-500' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="text-xs mt-1">Recuento</span>
            </a>
        @endif

        @if(Auth::user()->canAccessAsistencia() && !Auth::user()->canAccessRecuento())
            {{-- Asistencia - Solo Asistente --}}
            <a href="{{ route('asistencia.index') }}" class="mobile-nav-item {{ request()->routeIs('asistencia.*') ? 'text-blue-600' : 'text-gray-500' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="text-xs mt-1">Asistencia</span>
            </a>
        @endif

        @if(Auth::user()->canAccessRecuento() || Auth::user()->canAccessAsistencia())
            {{-- Reportes --}}
            <a href="{{ route('ingresos-asistencia.index') }}" class="mobile-nav-item {{ request()->routeIs('ingresos-asistencia.*') ? 'text-blue-600' : 'text-gray-500' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="text-xs mt-1">Reportes</span>
            </a>
        @endif

        @if(!Auth::user()->isMiembro())
            {{-- Mas... - Menu expandible --}}
            <button onclick="toggleMobileMenu()" class="mobile-nav-item text-gray-500" id="mobileMoreBtn">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"></path>
                </svg>
                <span class="text-xs mt-1">Mas</span>
            </button>
        @endif
    </div>
</nav>

{{-- Menu "Mas..." slide-up --}}
@if(!Auth::user()->isMiembro())
<div id="mobileMenuOverlay" class="fixed inset-0 bg-gray-900/50 z-[60] hidden" onclick="closeMobileMenu()"></div>
<div id="mobileMenuSlide" class="fixed bottom-0 left-0 right-0 bg-white rounded-t-2xl z-[70] transform translate-y-full transition-transform duration-300 ease-out">
    <div class="p-4">
        {{-- Handle --}}
        <div class="w-10 h-1 bg-gray-300 rounded-full mx-auto mb-4"></div>

        <div class="space-y-1">
            @if(Auth::user()->canAccessRecuento())
            <a href="{{ route('dashboard') }}" class="flex items-center px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100">
                <svg class="w-5 h-5 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span class="font-medium">Dashboard</span>
            </a>
            @endif

            @if(Auth::user()->canAccessAsistencia())
            <a href="{{ route('asistencia.index') }}" class="flex items-center px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100">
                <svg class="w-5 h-5 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="font-medium">Asistencia</span>
            </a>
            @endif

            @if(Auth::user()->rol === 'admin')
            <div class="border-t border-gray-200 my-2"></div>
            <p class="px-4 py-1 text-xs font-semibold text-gray-400 uppercase">Administracion</p>

            <a href="{{ route('cultos.index') }}" class="flex items-center px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100">
                <svg class="w-5 h-5 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span class="font-medium">Gestionar Cultos</span>
            </a>

            <a href="{{ route('admin.clases.index') }}" class="flex items-center px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100">
                <svg class="w-5 h-5 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                </svg>
                <span class="font-medium">Gestionar Clases</span>
            </a>

            <a href="{{ route('personas.index') }}" class="flex items-center px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100">
                <svg class="w-5 h-5 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span class="font-medium">Gestionar Personas</span>
            </a>

            <a href="{{ route('usuarios.index') }}" class="flex items-center px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100">
                <svg class="w-5 h-5 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span class="font-medium">Gestionar Usuarios</span>
            </a>

            <a href="{{ route('admin.auditoria.index') }}" class="flex items-center px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100">
                <svg class="w-5 h-5 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="font-medium">Auditoria</span>
            </a>
            @endif

            <div class="border-t border-gray-200 my-2"></div>

            {{-- Cerrar Sesion --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex items-center w-full px-4 py-3 rounded-lg text-red-600 hover:bg-red-50">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span class="font-medium">Cerrar Sesion</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleMobileMenu() {
    const overlay = document.getElementById('mobileMenuOverlay');
    const slide = document.getElementById('mobileMenuSlide');
    overlay.classList.toggle('hidden');
    if (slide.classList.contains('translate-y-full')) {
        slide.classList.remove('translate-y-full');
        slide.classList.add('translate-y-0');
    } else {
        closeMobileMenu();
    }
}

function closeMobileMenu() {
    const overlay = document.getElementById('mobileMenuOverlay');
    const slide = document.getElementById('mobileMenuSlide');
    overlay.classList.add('hidden');
    slide.classList.remove('translate-y-0');
    slide.classList.add('translate-y-full');
}
</script>
@endif

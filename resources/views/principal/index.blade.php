@php
    $t = tenant();
    $tColors = $t ? $t->colors : \App\Models\Tenant::COLOR_THEMES['blue'];
    $tName = $t ? $t->nombre : 'Sistema de Administracion';
    $tSiglas = $t ? $t->siglas : 'Admin';
    $tLogo = $t ? $t->logo_url : asset('images/Logo.png');
    $tSocial = $t && $t->redes_sociales ? $t->redes_sociales : [];
    // Identidad de la iglesia: editable por tenant en BD. Fallbacks genéricos.
    $tMision = $t && $t->mision ? $t->mision : 'Proclamar el evangelio de Jesucristo, edificar a los creyentes en la fe y servir a nuestra comunidad con amor y dedicacion, siguiendo las ensenanzas de la Palabra de Dios.';
    $tVision = $t && $t->vision ? $t->vision : 'Ser una iglesia que impacte al mundo, formando discipulos comprometidos con Cristo, viviendo en santidad y extendiendo el Reino de Dios a traves del amor y la verdad.';
    $tValores = $t && $t->valores ? $t->valores : [
        ['nombre' => 'Amor', 'descripcion' => 'Amar a Dios sobre todas las cosas y a nuestro prójimo como a nosotros mismos'],
        ['nombre' => 'Integridad', 'descripcion' => 'Vivir con transparencia y honestidad según los principios bíblicos'],
        ['nombre' => 'Comunidad', 'descripcion' => 'Crecer juntos en fe, apoyándonos y edificándonos mutuamente'],
    ];
@endphp

@extends('layouts.admin')

@section('title', $tSiglas . ' - Principal')
@section('page-title', $tName)

@section('content')
<div class="space-y-6">
    <!-- Hero Section -->
    <div class="rounded-lg shadow-xl overflow-hidden" style="background: linear-gradient(to right, {{ $tColors['600'] }}, {{ $tColors['800'] }});">
        <div class="px-6 py-8 xl:px-8 xl:py-10 text-center text-white">
            <h1 class="text-3xl xl:text-4xl font-bold mb-3">Bienvenido a {{ $tName }}</h1>
            <p class="text-lg xl:text-xl max-w-2xl mx-auto" style="color: {{ $tColors['100'] }};">
                Una comunidad de fe comprometida con el amor de Cristo y el servicio a Dios
            </p>
        </div>
    </div>

    <!-- Accesos rapidos: las tareas de cada rol a un toque desde la pantalla de inicio -->
    @php
        $usuario = Auth::user();
        $accesos = array_values(array_filter([
            $usuario->canAccessRecuento() ? ['recuento.index', 'Registrar sobres', 'Recuento del culto abierto', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'bg-blue-50 text-blue-700'] : null,
            $usuario->canAccessAsistencia() ? ['asistencia.index', 'Registrar asistencia', 'Capilla, clases, salvos y visitas', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'bg-green-50 text-green-700'] : null,
            $usuario->hasPermission('marcar_asistencia') ? ['marcar-asistencia.index', 'Marcar mi asistencia', 'Escanear el QR del culto', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'bg-green-50 text-green-700'] : null,
            $usuario->canAccessRecuento() ? ['dashboard', 'Dashboard', 'Resumen del mes', 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'bg-indigo-50 text-indigo-700'] : null,
            ($usuario->canAccessRecuento() || $usuario->canAccessAsistencia()) ? [$usuario->canAccessRecuento() ? 'ingresos-asistencia.index' : 'ingresos-asistencia.asistencia', 'Reportes', 'PDF y Excel', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'bg-purple-50 text-purple-700'] : null,
            $usuario->canAccessAdmin() ? ['personas.index', 'Personas', 'Buscar, agregar y editar', 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'bg-amber-50 text-amber-700'] : null,
            $usuario->canAccessAdmin() ? ['cultos.index', 'Cultos', 'Crear el culto de hoy', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'bg-sky-50 text-sky-700'] : null,
            ($usuario->puedeAltaRapidaPersonas() && ! $usuario->canAccessAdmin()) ? ['personas.alta-rapida', 'Agregar persona', 'Alta rápida: nombre y teléfono', 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z', 'bg-green-50 text-green-700'] : null,
            $usuario->tieneMiPerfil() ? ['mi-perfil.index', 'Mi perfil', 'Mis promesas y compromisos', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'bg-gray-100 text-gray-700'] : null,
        ]));
    @endphp
    @if(count($accesos))
    <div>
        <h2 class="text-lg font-semibold text-gray-800 mb-3">¿Qué quieres hacer?</h2>
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4">
            @foreach($accesos as [$rutaAcceso, $tituloAcceso, $detalleAcceso, $iconoAcceso, $colorAcceso])
            <a href="{{ route($rutaAcceso) }}" class="flex items-center gap-3 min-h-[72px] p-4 bg-white rounded-lg shadow hover:shadow-md border border-gray-100 hover:border-blue-300 transition">
                <span class="flex-shrink-0 w-11 h-11 rounded-lg flex items-center justify-center {{ $colorAcceso }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconoAcceso }}"></path></svg>
                </span>
                <span class="min-w-0">
                    <span class="block font-semibold text-gray-900">{{ $tituloAcceso }}</span>
                    <span class="block text-sm text-gray-500 truncate">{{ $detalleAcceso }}</span>
                </span>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Informacion Principal -->
    <div class="grid md:grid-cols-2 gap-6">
        <!-- Nuestra Mision -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 rounded-lg flex items-center justify-center mr-4" style="background-color: {{ $tColors['100'] }};">
                    <svg class="w-6 h-6" style="color: {{ $tColors['600'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Nuestra Mision</h2>
            </div>
            <p class="text-gray-600 leading-relaxed">{{ $tMision }}</p>
        </div>

        <!-- Nuestra Vision -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Nuestra Vision</h2>
            </div>
            <p class="text-gray-600 leading-relaxed">{{ $tVision }}</p>
        </div>
    </div>

    <!-- Valores -->
    @php
        // Iconos SVG por nombre de valor. Si no hay match, se usa el corazon como fallback.
        $iconosValores = [
            'amor' => ['bg' => 'bg-green-100', 'color' => 'text-green-600', 'path' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>'],
            'integridad' => ['bg' => 'bg-blue-100', 'color' => 'text-blue-600', 'path' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>'],
            'compañerismo' => ['bg' => 'bg-yellow-100', 'color' => 'text-yellow-600', 'path' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>'],
            'comunidad' => ['bg' => 'bg-yellow-100', 'color' => 'text-yellow-600', 'path' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>'],
        ];
        $iconoDefault = ['bg' => 'bg-purple-100', 'color' => 'text-purple-600', 'path' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>'];
    @endphp
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Nuestros Valores</h2>
        {{-- Clases completas para que Tailwind las genere (con "md:grid-cols-{{ n }}" no aparecian en el CSS) --}}
        @php $columnasValores = [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3', 4 => 'md:grid-cols-4'][min(max(count($tValores), 1), 4)]; @endphp
        <div class="grid {{ $columnasValores }} gap-6">
            @foreach($tValores as $v)
                @php
                    $nombre = is_array($v) ? ($v['nombre'] ?? '') : $v;
                    $descripcion = is_array($v) ? ($v['descripcion'] ?? '') : '';
                    $ico = $iconosValores[mb_strtolower($nombre, 'UTF-8')] ?? $iconoDefault;
                @endphp
                <div class="text-center">
                    <div class="w-16 h-16 {{ $ico['bg'] }} rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-8 h-8 {{ $ico['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $ico['path'] !!}</svg>
                    </div>
                    <h3 class="font-bold text-lg text-gray-800 mb-2">{{ $nombre }}</h3>
                    @if($descripcion)
                        <p class="text-gray-600 text-sm">{{ $descripcion }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Redes Sociales -->
    @if(!empty($tSocial))
    <div class="rounded-lg shadow-md p-6 text-white" style="background: linear-gradient(to right, {{ $tColors['700'] }}, {{ $tColors['900'] }});">
        <h2 class="text-2xl font-bold mb-4 text-center">Siguenos en Redes Sociales</h2>
        <p class="text-center mb-6">Mantente conectado con nuestra comunidad y conoce nuestras actividades</p>
        <div class="flex justify-center gap-6 flex-wrap">
            @if(!empty($tSocial['instagram']))
            <a href="{{ $tSocial['instagram'] }}"
               target="_blank"
               rel="noopener noreferrer"
               class="flex items-center gap-3 bg-white/20 hover:bg-white/30 backdrop-blur-sm px-6 py-3 rounded-lg transition-all duration-200 hover:scale-105">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
                <span class="font-semibold">Instagram</span>
            </a>
            @endif
            @if(!empty($tSocial['facebook']))
            <a href="{{ $tSocial['facebook'] }}"
               target="_blank"
               rel="noopener noreferrer"
               class="flex items-center gap-3 bg-white/20 hover:bg-white/30 backdrop-blur-sm px-6 py-3 rounded-lg transition-all duration-200 hover:scale-105">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                <span class="font-semibold">Facebook</span>
            </a>
            @endif
            @if(!empty($tSocial['youtube']))
            <a href="{{ $tSocial['youtube'] }}"
               target="_blank"
               rel="noopener noreferrer"
               class="flex items-center gap-3 bg-white/20 hover:bg-white/30 backdrop-blur-sm px-6 py-3 rounded-lg transition-all duration-200 hover:scale-105">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                </svg>
                <span class="font-semibold">YouTube</span>
            </a>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection

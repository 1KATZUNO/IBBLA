@php
    // Marca de la pantalla de entrada: la iglesia de la sesion o la unica de la instalacion; si hay varias
    // iglesias y nadie entro todavia, la generica de siempre.
    $iglesiaMarca = iglesia_de_marca();
    $marcaSiglas = $iglesiaMarca?->siglas ?? 'IBBLA';
    $marcaNombre = $iglesiaMarca?->nombre ?? 'Iglesia Bíblica Bautista en La Aurora';
    $marcaLogo = $iglesiaMarca?->logo_url ?? asset('images/Logo.png');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <meta name="theme-color" content="#1e40af">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <title>Iniciar sesión · {{ $marcaSiglas }}</title>

        <!-- PWA Manifest -->
        <link rel="manifest" href="{{ url_manifiesto() }}">

        <!-- Favicon & App Icons -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $marcaLogo }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $marcaLogo }}">
        <link rel="shortcut icon" href="{{ $marcaLogo }}">
        <link rel="apple-touch-icon" href="{{ $marcaLogo }}">

        <!-- Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        @include('partials.aviso-ambiente')
        <div class="flex min-h-screen min-h-dvh flex-col items-center justify-center px-4 py-8">
            <div class="w-full max-w-md">
                <!-- Encabezado de marca -->
                <div class="mb-6 flex flex-col items-center text-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-800 shadow-card">
                        <img src="{{ $marcaLogo }}" alt="" class="h-11 w-11 object-contain">
                    </span>
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight text-gray-900">{{ $marcaSiglas }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $marcaNombre }} · Administración</p>
                </div>

                <!-- Tarjeta -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-card sm:p-8">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-gray-400">&copy; {{ date('Y') }} {{ $marcaSiglas }} · {{ $marcaNombre }}</p>
            </div>
        </div>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js');
        }
    </script>
    </body>
</html>

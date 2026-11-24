<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <meta name="theme-color" content="#1e40af">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <title>Iniciar sesión · IBBLA</title>

        <!-- PWA Manifest -->
        <link rel="manifest" href="{{ asset('manifest.json') }}">

        <!-- Favicon & App Icons -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/Logo.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/Logo.png') }}">
        <link rel="shortcut icon" href="{{ asset('images/Logo.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/Logo.png') }}">

        <!-- Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        <div class="flex min-h-screen min-h-dvh flex-col items-center justify-center px-4 py-8">
            <div class="w-full max-w-md">
                <!-- Encabezado de marca -->
                <div class="mb-6 flex flex-col items-center text-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-800 shadow-card">
                        <img src="{{ asset('images/Logo.png') }}" alt="" class="h-11 w-11 object-contain">
                    </span>
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight text-gray-900">{{ tenant()?->siglas ?? 'IBBLA' }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ tenant()?->nombre ?? 'Iglesia Bíblica Bautista en La Aurora' }} · Administración</p>
                </div>

                <!-- Tarjeta -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-card sm:p-8">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-gray-400">&copy; {{ date('Y') }} {{ tenant()?->siglas ?? 'IBBLA' }} · {{ tenant()?->nombre ?? 'Iglesia Bíblica Bautista en La Aurora' }}</p>
            </div>
        </div>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js');
        }
    </script>
    </body>
</html>

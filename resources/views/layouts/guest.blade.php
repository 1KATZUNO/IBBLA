<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <meta name="theme-color" content="#0e7490">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <title>Iniciar Sesion - Sistema de Administracion</title>

        <!-- PWA Manifest -->
        <link rel="manifest" href="{{ asset('manifest.json') }}">

        <!-- Favicon & App Icons -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/Logo_ANGEDA_azul.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/Logo_ANGEDA_azul.png') }}">
        <link rel="shortcut icon" href="{{ asset('images/Logo_ANGEDA_azul.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/angeda_deep_ocean.png') }}">

        <!-- Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <!-- Background degradado -->
        <div class="fixed inset-0 bg-gradient-to-br from-slate-900 via-blue-900 to-cyan-800"></div>

        <!-- Patron decorativo sutil -->
        <div class="fixed inset-0 opacity-10" style="background-image: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.2) 1px, transparent 1px), radial-gradient(circle at 75% 75%, rgba(255,255,255,0.15) 1px, transparent 1px); background-size: 50px 50px;"></div>

        <div class="min-h-screen flex flex-col sm:justify-center items-center px-4 py-6 sm:pt-0 relative z-10">
            <!-- Logo y Titulo -->
            <div class="mb-8 text-center animate-fade-in">
                <div class="inline-block p-4">
                    <img src="{{ asset('images/Logo_ANGEDA_azul.png') }}" alt="Logo" class="w-20 h-20 mx-auto mb-4 drop-shadow-lg">
                    <h1 class="text-2xl font-bold text-white">Sistema Admin</h1>
                    <p class="text-cyan-200 font-medium mt-1 text-sm">Administracion de Iglesia</p>
                </div>
            </div>

            <!-- Tarjeta de login -->
            <div class="w-full sm:max-w-md animate-fade-in-up">
                <div class="bg-white/95 backdrop-blur-sm rounded-xl p-8 shadow-2xl border-t-4 border-cyan-500">
                    {{ $slot }}
                </div>

                <!-- Footer -->
                <div class="text-center mt-6 text-sm text-cyan-200/70">
                    <p>&copy; {{ date('Y') }} Sistema de Administracion. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js');
        }
    </script>
    </body>
</html>

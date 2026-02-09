<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>IBBSC - Iniciar Sesión</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/Logo.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/Logo.png') }}">
        <link rel="shortcut icon" href="{{ asset('images/Logo.png') }}">

        <!-- Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <!-- Background con imagen del banner -->
        <div class="fixed inset-0 bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('images/Banner.png') }}');"></div>

        <!-- Overlay azul simple -->
        <div class="fixed inset-0 bg-blue-900/60"></div>

        <div class="min-h-screen flex flex-col sm:justify-center items-center px-4 py-6 sm:pt-0 relative z-10">
            <!-- Logo principal -->
            <div class="mb-8 text-center animate-fade-in">
                <img src="{{ asset('images/Logo.png') }}" alt="IBBSC" class="w-20 h-20 mx-auto drop-shadow-lg">
                <div class="mt-4 inline-block px-6 py-3 rounded-xl bg-white shadow-lg">
                    <h1 class="text-2xl font-display font-bold text-blue-700">IBBSC Admin</h1>
                    <p class="text-gray-600 font-medium mt-1 text-sm">Sistema de Administración</p>
                </div>
            </div>

            <!-- Tarjeta de login -->
            <div class="w-full sm:max-w-md animate-fade-in-up">
                <div class="bg-white rounded-xl p-8 shadow-xl border-t-4 border-blue-600">
                    {{ $slot }}
                </div>

                <!-- Footer -->
                <div class="text-center mt-6 text-sm text-blue-100">
                    <p>© {{ date('Y') }} IBBSC. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    </body>
</html>

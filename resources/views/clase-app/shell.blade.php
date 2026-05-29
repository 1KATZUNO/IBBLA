<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ tenant_title('Clase ' . $clase->nombre) }}</title>
    <link rel="icon" href="{{ tenant()?->favicon_url ?? tenant()?->logo_url ?? asset('images/Logo.png') }}">
    @viteReactRefresh
    @vite('resources/js/clase-app/main.jsx')
</head>
<body>
    <div
        id="clase-app"
        data-clase-slug="{{ $clase->slug }}"
        data-clase-nombre="{{ $clase->nombre }}"
        data-clase-color="{{ $clase->color }}"
        data-tenant-siglas="{{ tenant()?->siglas ?? 'IBBSC' }}"
        data-tenant-nombre="{{ tenant()?->nombre ?? '' }}"
        data-user-id="{{ auth()->id() }}"
        data-user-name="{{ auth()->user()->name }}"
        data-csrf="{{ csrf_token() }}"
    ></div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Asistencia</title>
    @viteReactRefresh
    @vite('resources/js/asistencia-app/main.jsx')
</head>
<body>
    <div id="asistencia-app"></div>
</body>
</html>

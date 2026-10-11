<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Enlace no válido</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f3f4f6; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #374151; }
        .tarjeta { max-width: 26rem; margin: 1rem; padding: 2rem 1.5rem; background: #fff; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12); text-align: center; }
        h1 { margin: 0 0 0.75rem; font-size: 1.35rem; color: #1f2937; }
        p { margin: 0 0 0.75rem; line-height: 1.5; }
        .pequeno { font-size: 0.875rem; color: #6b7280; margin-bottom: 0; }
    </style>
</head>
<body>
    <div class="tarjeta">
        <h1>Este enlace ya no es válido</h1>
        <p>El enlace a tu informe venció o no está completo. Por seguridad, cada enlace dura {{ (int) config('whatsapp.informe.vigencia_dias', 7) }} días.</p>
        <p class="pequeno">Pide a la iglesia que te envíe uno nuevo.</p>
    </div>
</body>
</html>

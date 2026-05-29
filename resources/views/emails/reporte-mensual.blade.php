@php
    $t = function_exists('tenant') ? tenant() : null;
    $tNombre = $t?->nombre ?? 'Sistema de Administración';
    $tSiglas = $t?->siglas ?? 'IBBSC';
    $tColor = $t && $t->colors ? ($t->colors['600'] ?? '#3b82f6') : '#3b82f6';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Mensual {{ $nombreMes }} {{ $año }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f3f4f6; margin:0; padding:24px; color:#1f2937;">
    <div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; padding:32px; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
        <h1 style="color:{{ $tColor }}; font-size:22px; margin:0 0 16px;">{{ $tSiglas }} — Reportes Mensuales</h1>
        <h2 style="font-size:18px; color:#1f2937; margin:0 0 24px;">{{ $nombreMes }} {{ $año }}</h2>

        <p>Hola,</p>
        <p>Adjunto encontrará los reportes consolidados del mes de <strong>{{ $nombreMes }} {{ $año }}</strong>:</p>

        <ul style="line-height:1.8;">
            <li>PDF: Asistencia detallada del mes</li>
            <li>Excel: Asistencia del mes (para gráficos y comparativos)</li>
            <li>Excel: Ingresos del mes</li>
            <li>Excel: Promesas con cumplimiento del año en curso</li>
        </ul>

        <p style="font-size:13px; color:#6b7280; margin-top:24px;">
            Este correo fue generado y enviado automáticamente por el sistema {{ $tSiglas }} el día 1 del mes en curso.
            Adjuntos totales: {{ $totalAdjuntos }}.
        </p>

        <hr style="border:0; border-top:1px solid #e5e7eb; margin:24px 0;">

        <p style="font-size:12px; color:#9ca3af; text-align:center;">
            {{ $tSiglas }} — Sistema de Administración<br>
            {{ $tNombre }}
        </p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>QR Culto - {{ $culto->fecha->format('d/m/Y') }}</title>
    <style>
        @page { size: landscape; margin: 20mm; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; text-align: center; }
        .header { margin-bottom: 30px; border-bottom: 3px solid #3b82f6; padding-bottom: 15px; }
        .header img { width: 60px; height: 60px; }
        .header h1 { margin: 10px 0 0 0; color: #1f2937; font-size: 24px; }
        .header h2 { margin: 5px 0 0 0; color: #3b82f6; font-size: 14px; font-weight: normal; }
        .culto-info { background-color: #f0fdf4; padding: 15px; border-radius: 10px; margin-bottom: 30px; display: inline-block; }
        .culto-info p { margin: 5px 0; font-size: 16px; color: #166534; }
        .culto-info .tipo { font-size: 22px; font-weight: bold; color: #15803d; }
        .culto-info .fecha { font-size: 18px; }
        .qr-container { margin: 20px auto; }
        .qr-container img { width: 350px; height: 350px; }
        .instructions { margin-top: 30px; color: #6b7280; font-size: 13px; }
        .instructions p { margin: 5px 0; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
    </style>
</head>
<body>
    @php extract(tenant_pdf_data()); @endphp

    <div class="header">
        <div style="display: inline-block; background-color: {{ $tenantColor }}; border-radius: 50%; width: 70px; height: 70px; text-align: center; padding-top: 10px;">
            <img src="data:image/png;base64,{{ $tenantLogoBase64 }}" style="width: 50px; height: 50px;" alt="Logo">
        </div>
        <h1>{{ $tenantSiglas }} - {{ $tenantNombre }}</h1>
        <h2>Codigo QR para Marcar Asistencia de Servidores</h2>
    </div>

    <div class="culto-info">
        <p class="tipo">{{ $culto->tipo_nombre }}</p>
        <p class="fecha">{{ $culto->fecha->format('d/m/Y') }}</p>
        @if($culto->hora)
        <p>Hora: {{ $culto->hora->format('h:i A') }}</p>
        @endif
    </div>

    <div class="qr-container">
        <img src="data:image/svg+xml;base64,{{ $qrBase64 }}" alt="QR Code">
    </div>

    <div class="instructions">
        <p><strong>Instrucciones:</strong> Escanea este codigo QR desde la seccion "Marcar Asistencia" en tu cuenta de servidor.</p>
    </div>

    <div class="footer">
        <p>Sistema de Administracion - {{ $tenantSiglas }} - {{ $tenantNombre }}</p>
        <p>Generado: {{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>

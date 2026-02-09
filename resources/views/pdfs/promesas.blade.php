<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Promesas</title>
    <style>
        @page { margin: 15mm; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; }
        .header { display: flex; align-items: center; margin-bottom: 20px; border-bottom: 3px solid #8b5cf6; padding-bottom: 10px; }
        .header img { width: 60px; height: 60px; margin-right: 15px; }
        .header-text { flex: 1; }
        .header-text h1 { margin: 0; color: #1f2937; font-size: 18px; }
        .header-text h2 { margin: 5px 0 0 0; color: #8b5cf6; font-size: 12px; font-weight: normal; }
        .header-text p { margin: 3px 0 0 0; color: #6b7280; font-size: 9px; }
        .info-box { background-color: #f5f3ff; padding: 10px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #8b5cf6; }
        .info-box p { margin: 3px 0; font-size: 10px; }
        .info-box strong { color: #8b5cf6; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: right; font-size: 10px; }
        th { background-color: #8b5cf6; color: white; font-weight: bold; text-transform: uppercase; }
        td:first-child, th:first-child { text-align: left; }
        tbody tr:nth-child(even) { background-color: #f9fafb; }
        tbody tr:hover { background-color: #f3f4f6; }
        .total-row { font-weight: bold; background-color: #e9d5ff !important; border-top: 2px solid #8b5cf6; }
        .total-row td { font-size: 11px; }
        .summary-cards { display: flex; justify-content: space-between; margin-bottom: 20px; gap: 10px; }
        .summary-card { flex: 1; padding: 12px; border-radius: 8px; text-align: center; }
        .summary-card.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .summary-card.green { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .summary-card.red { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .summary-card.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        .summary-card p { margin: 0; font-size: 9px; opacity: 0.9; }
        .summary-card h3 { margin: 5px 0 0 0; font-size: 16px; font-weight: bold; }
        .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
        .nota { background-color: #dbeafe; padding: 10px; border-radius: 5px; margin-top: 15px; font-size: 9px; border-left: 4px solid #3b82f6; }
    </style>
</head>
<body>
    <div class="header">
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/Logo2.png'))) }}" alt="Logo IBBSC">
        <div class="header-text">
            <h1>IBBSC - Iglesia Bíblica Bautista en Santa Cruz</h1>
            <h2>Reporte de Promesas - {{ \Carbon\Carbon::create($año, $mes, 1)->locale('es')->translatedFormat('F Y') }}</h2>
            <p><strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    @if($categoria)
    <div class="info-box">
        <p><strong>Filtro aplicado:</strong> Mostrando solo categoría <strong>{{ ucfirst($categoria) }}</strong></p>
    </div>
    @endif

    <!-- Resumen en Tarjetas -->
    <div class="summary-cards">
        <div class="summary-card blue">
            <p>Total Prometido</p>
            <h3>{{ number_format($totales['grand_total']['prometido'], 2) }}</h3>
        </div>
        <div class="summary-card green">
            <p>Total Dado</p>
            <h3>{{ number_format($totales['grand_total']['dado'], 2) }}</h3>
        </div>
        <div class="summary-card red">
            <p>Total Faltante</p>
            <h3>{{ number_format($totales['grand_total']['faltante'], 2) }}</h3>
        </div>
        <div class="summary-card purple">
            <p>Total Profit</p>
            <h3>{{ number_format($totales['grand_total']['profit'], 2) }}</h3>
        </div>
    </div>
    
    <!-- Tabla Detallada -->
    <table>
        <thead>
            <tr>
                <th>Categoría</th>
                <th>Total Prometido</th>
                <th>Total Dado</th>
                <th>Faltante</th>
                <th>Profit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($totales['categorias'] as $cat)
            <tr>
                <td style="text-align: left; font-weight: bold;">{{ $cat['categoria'] }}</td>
                <td style="color: #3b82f6; font-weight: 600;">{{ number_format($cat['total_prometido'], 2) }}</td>
                <td style="color: #10b981; font-weight: 600;">{{ number_format($cat['total_dado'], 2) }}</td>
                <td style="color: #ef4444; font-weight: 600;">{{ number_format($cat['faltante'], 2) }}</td>
                <td style="color: #8b5cf6; font-weight: 600;">{{ number_format($cat['profit'], 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td style="text-align: left;">TOTALES GENERALES</td>
                <td style="color: #1e40af;">{{ number_format($totales['grand_total']['prometido'], 2) }}</td>
                <td style="color: #047857;">{{ number_format($totales['grand_total']['dado'], 2) }}</td>
                <td style="color: #b91c1c;">{{ number_format($totales['grand_total']['faltante'], 2) }}</td>
                <td style="color: #6d28d9;">{{ number_format($totales['grand_total']['profit'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Nota Informativa -->
    <div class="nota">
        <strong>Nota:</strong> Este reporte muestra los datos del mes seleccionado. El profit representa el monto extra 
        dado por encima de lo prometido en ese mes específico.
    </div>
    
    <div class="footer">
        <p>Sistema de Administración - IBBSC - Iglesia Bíblica Bautista en Santa Cruz</p>
    </div>
</body>
</html>

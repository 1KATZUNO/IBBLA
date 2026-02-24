<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ingresos</title>
    <style>
        @page { size: landscape; margin: 15mm; }
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
        .header { display: flex; align-items: center; margin-bottom: 20px; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; }
        .header img { width: 60px; height: 60px; margin-right: 15px; }
        .header-text { flex: 1; }
        .header-text h1 { margin: 0; color: #1f2937; font-size: 18px; }
        .header-text h2 { margin: 5px 0 0 0; color: #3b82f6; font-size: 12px; font-weight: normal; }
        .header-text p { margin: 3px 0 0 0; color: #6b7280; font-size: 9px; }
        .info-box { background-color: #f3f4f6; padding: 8px; border-radius: 5px; margin-bottom: 15px; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: right; font-size: 9px; }
        th { background-color: #3b82f6; color: white; font-weight: bold; text-transform: uppercase; }
        td:first-child, th:first-child { text-align: left; }
        tbody tr:nth-child(even) { background-color: #f9fafb; }
        tbody tr:hover { background-color: #f3f4f6; }
        .total-row { font-weight: bold; background-color: #dbeafe !important; border-top: 2px solid #3b82f6; }
        .total-row td { font-size: 10px; }
        .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
    </style>
</head>
<body>
    @php extract(tenant_pdf_data()); @endphp
    <div class="header">
        <div style="background-color: {{ $tenantColor }}; border-radius: 50%; width: 70px; height: 70px; text-align: center; padding-left: 3px; margin-right: 15px;">
            <img src="data:image/png;base64,{{ $tenantLogoBase64 }}" style="width: 50px; height: 50px; margin-top: 10px;" alt="Logo">
        </div>
        <div class="header-text">
            <h1>{{ $tenantSiglas }} - {{ $tenantNombre }}</h1>
            <h2>Reporte de Ingresos - {{ ucfirst($tipoReporte) }}</h2>
            <p><strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>
    @if(isset($soloTransferencias) && $soloTransferencias)
    @php $totalTransferenciasGlobal = collect($registros)->sum('total'); @endphp
    <div class="info-box" style="margin-top:10px;">
        <strong>Total Transferencias:</strong> {{ number_format($totalTransferenciasGlobal, 2) }}
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Fecha/Periodo</th>
                @foreach($categories as $cat)
                <th>{{ $cat->nombre }}</th>
                @endforeach
                <th>Suelto</th>
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totales = [];
                foreach($categories as $cat) {
                    $totales[$cat->slug] = 0;
                }
                $totales['suelto'] = 0;
                $totales['total'] = 0;
            @endphp
            @foreach($registros as $registro)
            <tr>
                <td style="text-align: left;">{{ $registro['fecha'] }}</td>
                @foreach($categories as $cat)
                <td>{{ number_format($registro[$cat->slug] ?? 0, 2) }}</td>
                @endforeach
                <td>{{ number_format($registro['suelto'], 2) }}</td>
                <td style="font-weight: bold;">{{ number_format($registro['total'], 2) }}</td>
            </tr>
            @php
                foreach($categories as $cat) {
                    $totales[$cat->slug] += $registro[$cat->slug] ?? 0;
                }
                $totales['suelto'] += $registro['suelto'];
                $totales['total'] += $registro['total'];
            @endphp
            @endforeach
            <tr class="total-row">
                <td style="text-align: left;">TOTALES</td>
                @foreach($categories as $cat)
                <td>{{ number_format($totales[$cat->slug], 2) }}</td>
                @endforeach
                <td>{{ number_format($totales['suelto'], 2) }}</td>
                <td>{{ number_format($totales['total'], 2) }}</td>
            </tr>
        </tbody>
    </table>
    
    @if(isset($tesorerosPorFecha) && is_array($tesorerosPorFecha) && count($tesorerosPorFecha) > 0)
    <h3 style="margin-top: 20px; font-size: 11px;">Tesoreros por Fecha</h3>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tesoreros</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tesorerosPorFecha as $fecha => $nombres)
            <tr>
                <td style="text-align:left;">{{ $fecha }}</td>
                <td style="text-align:left;">{{ is_array($nombres) ? implode(', ', $nombres) : $nombres }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        <p>Sistema de Administracion - {{ $tenantSiglas }} - {{ $tenantNombre }}</p>
    </div>
</body>
</html>

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
    <div class="header">
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/Logo2.png'))) }}" alt="Logo IBBSC">
        <div class="header-text">
            <h1>IBBSC - Iglesia Bíblica Bautista en Santa Cruz</h1>
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
                <th>Diezmo</th>
                <th>Ofr. Esp.</th>
                <th>Misiones</th>
                <th>Seminario</th>
                <th>Camp.</th>
                <th>Const.</th>
                <th>Prést.</th>
                <th>Micro</th>
                <th>Suelto</th>
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totales = [
                    'diezmo' => 0,
                    'misiones' => 0,
                    'seminario' => 0,
                    'campa' => 0,
                    'construccion' => 0,
                    'prestamo' => 0,
                    'micro' => 0,
                    'suelto' => 0,
                    'total' => 0
                ];
            @endphp
            @foreach($registros as $registro)
            <tr>
                <td style="text-align: left;">{{ $registro['fecha'] }}</td>
                <td>{{ number_format($registro['diezmo'], 2) }}</td>
                <td>{{ number_format($registro['ofrenda_especial'] ?? 0, 2) }}</td>
                <td>{{ number_format($registro['misiones'], 2) }}</td>
                <td>{{ number_format($registro['seminario'], 2) }}</td>
                <td>{{ number_format($registro['campa'], 2) }}</td>
                <td>{{ number_format($registro['construccion'], 2) }}</td>
                <td>{{ number_format($registro['prestamo'], 2) }}</td>
                <td>{{ number_format($registro['micro'], 2) }}</td>
                <td>{{ number_format($registro['suelto'], 2) }}</td>
                <td style="font-weight: bold;">{{ number_format($registro['total'], 2) }}</td>
            </tr>
            @php
                $totales['diezmo'] += $registro['diezmo'];
                $totales['ofrenda_especial'] = ($totales['ofrenda_especial'] ?? 0) + ($registro['ofrenda_especial'] ?? 0);
                $totales['misiones'] += $registro['misiones'];
                $totales['seminario'] += $registro['seminario'];
                $totales['campa'] += $registro['campa'];
                $totales['construccion'] += $registro['construccion'];
                $totales['prestamo'] += $registro['prestamo'];
                $totales['micro'] += $registro['micro'];
                $totales['suelto'] += $registro['suelto'];
                $totales['total'] += $registro['total'];
            @endphp
            @endforeach
            <tr class="total-row">
                <td style="text-align: left;">TOTALES</td>
                <td>{{ number_format($totales['diezmo'], 2) }}</td>
                <td>{{ number_format($totales['ofrenda_especial'] ?? 0, 2) }}</td>
                <td>{{ number_format($totales['misiones'], 2) }}</td>
                <td>{{ number_format($totales['seminario'], 2) }}</td>
                <td>{{ number_format($totales['campa'], 2) }}</td>
                <td>{{ number_format($totales['construccion'], 2) }}</td>
                <td>{{ number_format($totales['prestamo'], 2) }}</td>
                <td>{{ number_format($totales['micro'], 2) }}</td>
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
        <p>Sistema de Administración - IBBSC - Iglesia Bíblica Bautista en Santa Cruz</p>
    </div>
</body>
</html>

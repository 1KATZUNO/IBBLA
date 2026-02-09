<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte Anual de Promesas {{ $año }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #1f2937;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid #7c3aed;
        }
        .logo {
            width: 80px;
            height: auto;
            margin-bottom: 10px;
        }
        .title {
            font-size: 18pt;
            font-weight: bold;
            color: #7c3aed;
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 12pt;
            color: #6b7280;
            margin-bottom: 3px;
        }
        .info {
            font-size: 9pt;
            color: #9ca3af;
        }
        
        .summary-grid {
            display: table;
            width: 100%;
            margin-bottom: 25px;
        }
        .summary-row {
            display: table-row;
        }
        .summary-card {
            display: table-cell;
            width: 25%;
            padding: 15px;
            margin: 0 5px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-card.prometido {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-left: 4px solid #3b82f6;
        }
        .summary-card.dado {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-left: 4px solid #22c55e;
        }
        .summary-card.faltante {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-left: 4px solid #ef4444;
        }
        .summary-card.profit {
            background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%);
            border-left: 4px solid #eab308;
        }
        .summary-label {
            font-size: 9pt;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .summary-amount {
            font-size: 16pt;
            font-weight: bold;
            color: #1f2937;
        }
        
        .section-title {
            font-size: 13pt;
            font-weight: bold;
            color: #374151;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 9pt;
        }
        thead {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
            color: white;
        }
        th {
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 8pt;
            letter-spacing: 0.5px;
        }
        th.number {
            text-align: right;
        }
        tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }
        tbody tr:hover {
            background-color: #f9fafb;
        }
        td {
            padding: 10px 8px;
            color: #374151;
        }
        td.number {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        
        .grand-total-row {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            font-weight: bold;
            font-size: 10pt;
            border-top: 3px solid #eab308;
        }
        .grand-total-row td {
            padding: 12px 8px;
            color: #78350f;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 8pt;
            color: #9ca3af;
        }
        
        .positive {
            color: #16a34a;
            font-weight: 600;
        }
        .negative {
            color: #dc2626;
            font-weight: 600;
        }
        .zero {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('logo-ibbsc.png') }}" alt="IBBSC Logo" class="logo">
        <div class="title">Reporte Anual de Promesas</div>
        <div class="subtitle">Año {{ $año }}</div>
        @if($categoria && $categoria !== 'todas')
        <div class="info">Categoría: {{ ucfirst($categoria) }}</div>
        @else
        <div class="info">Todas las categorías</div>
        @endif
        <div class="info">Generado el {{ \Carbon\Carbon::now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</div>
    </div>

    <div class="summary-grid">
        <div class="summary-row">
            <div class="summary-card prometido">
                <div class="summary-label">Total Prometido</div>
                <div class="summary-amount">{{ number_format($grandTotal['prometido'], 0, ',', '.') }}</div>
            </div>
            <div class="summary-card dado">
                <div class="summary-label">Total Dado</div>
                <div class="summary-amount">{{ number_format($grandTotal['dado'], 0, ',', '.') }}</div>
            </div>
            <div class="summary-card faltante">
                <div class="summary-label">Total Faltante</div>
                <div class="summary-amount">{{ number_format($grandTotal['faltante'], 0, ',', '.') }}</div>
            </div>
            <div class="summary-card profit">
                <div class="summary-label">Total Profit</div>
                <div class="summary-amount 
                    @if($grandTotal['profit'] > 0) positive
                    @elseif($grandTotal['profit'] < 0) negative
                    @else zero
                    @endif">
                    {{ number_format($grandTotal['profit'], 0, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    <div class="section-title">Desglose Mensual</div>
    
    <table>
        <thead>
            <tr>
                <th>Mes</th>
                <th class="number">Prometido</th>
                <th class="number">Dado</th>
                <th class="number">Faltante</th>
                <th class="number">Profit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($totalesPorMes as $mesData)
            <tr>
                <td>{{ ucfirst($mesData['mes']) }} {{ $año }}</td>
                <td class="number">{{ number_format($mesData['totales']['prometido'], 0, ',', '.') }}</td>
                <td class="number">{{ number_format($mesData['totales']['dado'], 0, ',', '.') }}</td>
                <td class="number 
                    @if($mesData['totales']['faltante'] > 0) negative
                    @else zero
                    @endif">
                    {{ number_format($mesData['totales']['faltante'], 0, ',', '.') }}
                </td>
                <td class="number 
                    @if($mesData['totales']['profit'] > 0) positive
                    @elseif($mesData['totales']['profit'] < 0) negative
                    @else zero
                    @endif">
                    {{ number_format($mesData['totales']['profit'], 0, ',', '.') }}
                </td>
            </tr>
            @endforeach
            
            <tr class="grand-total-row">
                <td><strong>TOTAL ANUAL</strong></td>
                <td class="number"><strong>{{ number_format($grandTotal['prometido'], 0, ',', '.') }}</strong></td>
                <td class="number"><strong>{{ number_format($grandTotal['dado'], 0, ',', '.') }}</strong></td>
                <td class="number"><strong>{{ number_format($grandTotal['faltante'], 0, ',', '.') }}</strong></td>
                <td class="number 
                    @if($grandTotal['profit'] > 0) positive
                    @elseif($grandTotal['profit'] < 0) negative
                    @else zero
                    @endif">
                    <strong>{{ number_format($grandTotal['profit'], 0, ',', '.') }}</strong>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p><strong>Iglesia Bautista Bíblica Sión de Coronado</strong></p>
        <p>Sistema de Gestión de Ingresos y Promesas</p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte Individual — {{ $servidor->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; color: #1f2937; }
        .header { display: flex; align-items: center; margin-bottom: 14px; border-bottom: 3px solid #8b5cf6; padding-bottom: 10px; }
        .header img { width: 60px; height: 60px; margin-right: 14px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header h2 { margin: 4px 0 0; font-size: 13px; color: #7c3aed; font-weight: normal; }
        .header p { margin: 2px 0 0; font-size: 9px; color: #6b7280; }
        h3 { margin: 18px 0 8px; font-size: 12px; color: #374151; border-left: 4px solid #8b5cf6; padding-left: 8px; }
        .kpis { display: table; width: 100%; margin-bottom: 10px; }
        .kpi { display: table-cell; padding: 10px; background: #f3f4f6; border-radius: 4px; }
        .kpi .label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .kpi .value { font-size: 18px; font-weight: bold; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; font-size: 10px; }
        th { background: #8b5cf6; color: white; font-weight: bold; text-transform: uppercase; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    @php extract(tenant_pdf_data()); @endphp
    <div class="header">
        <div style="background:{{ $tenantColor }}; border-radius:50%; width:70px; height:70px; padding-left:3px; margin-right:14px;">
            <img src="data:image/png;base64,{{ $tenantLogoBase64 }}" style="width:54px; height:54px; margin-top:8px;" alt="Logo">
        </div>
        <div>
            <h1>{{ $servidor->name }}</h1>
            <h2>Reporte de Servidor — {{ $ano }}</h2>
            <p>{{ $servidor->email }} | Generado: {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="kpis">
        <div class="kpi">
            <div class="label">Cultos en {{ $ano }}</div>
            <div class="value">{{ $totalCultosAño }}</div>
        </div>
        <div class="kpi">
            <div class="label">Asistencias</div>
            <div class="value">{{ $totalAsistencias }}</div>
        </div>
        <div class="kpi">
            <div class="label">% Asistencia</div>
            <div class="value" style="color: {{ $pctAño >= 80 ? '#059669' : ($pctAño >= 50 ? '#d97706' : '#dc2626') }};">
                {{ $pctAño }}%
            </div>
        </div>
    </div>

    {{-- Asistencia mensual --}}
    <h3>Asistencia mensual {{ $ano }}</h3>
    <table>
        <thead>
            <tr>
                <th>Mes</th>
                <th>Cultos del mes</th>
                <th>Asistencias</th>
                <th>%</th>
            </tr>
        </thead>
        <tbody>
            @foreach($asistenciaSerie as $row)
            @php
                $pctMes = $row['cultos'] > 0 ? round(($row['asistencias'] / $row['cultos']) * 100) : 0;
                $pctColor = $pctMes >= 80 ? '#059669' : ($pctMes >= 50 ? '#d97706' : '#dc2626');
            @endphp
            <tr>
                <td>{{ ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'][$row['mes']] }}</td>
                <td style="text-align:center;">{{ $row['cultos'] }}</td>
                <td style="text-align:center;">{{ $row['asistencias'] }}</td>
                <td style="text-align:center; color: {{ $pctColor }}; font-weight:bold;">{{ $pctMes }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Promesas + cumplimiento --}}
    <h3>Promesas y cumplimiento {{ $ano }}</h3>
    @if(empty($cumplimiento))
        <p style="color:#6b7280; font-style:italic;">Este servidor no tiene promesas registradas.</p>
    @else
    <table>
        <thead>
            <tr>
                <th>Categoría</th>
                <th>Prometido</th>
                <th>Dado</th>
                <th>Saldo</th>
                <th>% Cumplim.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cumplimiento as $c)
            @php
                $simbolo = $c['moneda'] === 'USD' ? '$' : '₡';
                $pctColor = $c['pct'] >= 80 ? '#059669' : ($c['pct'] >= 50 ? '#d97706' : '#dc2626');
            @endphp
            <tr>
                <td>{{ ucfirst($c['categoria']) }}</td>
                <td style="text-align:right;">{{ $simbolo }}{{ number_format($c['prometido'], 2, '.', ',') }}</td>
                <td style="text-align:right; color:#059669;">{{ $simbolo }}{{ number_format($c['dado'], 2, '.', ',') }}</td>
                <td style="text-align:right; color: {{ $c['saldo'] >= 0 ? '#059669' : '#dc2626' }};">
                    {{ $c['saldo'] >= 0 ? '+' : '' }}{{ $simbolo }}{{ number_format($c['saldo'], 2, '.', ',') }}
                </td>
                <td style="text-align:center; color: {{ $pctColor }}; font-weight:bold;">{{ $c['pct'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        {{ $tenantSiglas }} — {{ $tenantNombre }} | Documento confidencial
    </div>
</body>
</html>

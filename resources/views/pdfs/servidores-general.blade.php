<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte General de Servidores</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 0; padding: 0; color: #1f2937; }
        .header { display: flex; align-items: center; margin-bottom: 14px; border-bottom: 3px solid #3b82f6; padding-bottom: 8px; }
        .header img { width: 50px; height: 50px; margin-right: 12px; }
        .header h1 { margin: 0; font-size: 16px; color: #1f2937; }
        .header h2 { margin: 2px 0 0; font-size: 11px; color: #3b82f6; font-weight: normal; }
        .header p { margin: 1px 0 0; font-size: 8px; color: #6b7280; }
        .kpis { display: table; width: 100%; margin-bottom: 12px; }
        .kpi { display: table-cell; padding: 8px; background: #f3f4f6; border-radius: 4px; }
        .kpi .label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .kpi .value { font-size: 14px; font-weight: bold; margin-top: 2px; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; font-size: 8.5px; }
        th { background: #3b82f6; color: white; font-weight: bold; text-transform: uppercase; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .pct-ok { color: #059669; font-weight: bold; }
        .pct-warn { color: #d97706; font-weight: bold; }
        .pct-bad { color: #dc2626; font-weight: bold; }
        .footer { margin-top: 14px; text-align: center; font-size: 7px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 6px; }
        .small { font-size: 8px; }
    </style>
</head>
<body>
    @php extract(tenant_pdf_data()); @endphp
    <div class="header">
        <div style="background:{{ $tenantColor }}; border-radius:50%; width:60px; height:60px; padding-left:2px; margin-right:12px;">
            <img src="data:image/png;base64,{{ $tenantLogoBase64 }}" style="width:46px; height:46px; margin-top:7px;" alt="Logo">
        </div>
        <div>
            <h1>{{ $tenantSiglas }} — Reporte General de Servidores</h1>
            <h2>
                {{ $meses[$mes] }} {{ $ano }}
                @if(!empty($ministerioFiltro)) — {{ $ministerioFiltro->nombre }} @endif
            </h2>
            <p>Generado: {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    <div class="kpis">
        <div class="kpi">
            <div class="label">Total Servidores</div>
            <div class="value">{{ $servidores->count() }}</div>
        </div>
        <div class="kpi">
            <div class="label">Cultos del Mes</div>
            <div class="value">{{ $totalCultosMes }}</div>
        </div>
        <div class="kpi">
            <div class="label">Asistencias Totales</div>
            <div class="value">{{ $asistenciasPorServidor->sum() }}</div>
        </div>
        <div class="kpi">
            <div class="label">% Asistencia Prom.</div>
            <div class="value">
                @if($servidores->count() > 0 && $totalCultosMes > 0)
                    {{ round($asistenciasPorServidor->sum() / $servidores->count() / $totalCultosMes * 100) }}%
                @else
                    0%
                @endif
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:18%">Servidor</th>
                <th style="width:16%">Ministerios</th>
                <th style="width:7%">Asist.</th>
                <th style="width:7%">% A.</th>
                <th style="width:14%">Promesas</th>
                <th style="width:10%">Prom. {{ $ano }}</th>
                <th style="width:10%">Dado {{ $ano }}</th>
                <th style="width:10%">Saldo</th>
                <th style="width:8%">% C.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($servidores as $s)
            @php
                $asist = $asistenciasPorServidor[$s->id] ?? 0;
                $pctAsist = $totalCultosMes > 0 ? round(($asist / $totalCultosMes) * 100) : 0;
                $promesas = $promesasPorServidor[$s->id] ?? collect();
                $c = $cumplimientoPorServidor[$s->id] ?? null;
                $pctClass = $pctAsist >= 80 ? 'pct-ok' : ($pctAsist >= 50 ? 'pct-warn' : 'pct-bad');
                $pctCumplClass = $c && $c['pct'] >= 80 ? 'pct-ok' : ($c && $c['pct'] >= 50 ? 'pct-warn' : 'pct-bad');
            @endphp
            <tr>
                <td>{{ $s->name }}<br><span class="small" style="color:#9ca3af">{{ $s->email }}</span></td>
                <td class="small">
                    @php $mins = $s->persona?->ministerios ?? collect(); @endphp
                    @forelse($mins as $min)
                        <span style="color:{{ $min->color }}; font-weight:bold;">{{ $min->nombre }}</span>@if(!$loop->last), @endif
                    @empty
                        <span style="color:#9ca3af">—</span>
                    @endforelse
                </td>
                <td style="text-align:center;">{{ $asist }}/{{ $totalCultosMes }}</td>
                <td style="text-align:center;" class="{{ $pctClass }}">{{ $pctAsist }}%</td>
                <td>
                    @forelse($promesas as $p)
                        {{ ucfirst($p->categoria) }}@if(!$loop->last), @endif
                    @empty
                        <span class="small" style="color:#9ca3af">—</span>
                    @endforelse
                </td>
                <td style="text-align:right;">
                    {{ $c ? '₡'.number_format($c['prometido'], 0, '.', ',') : '—' }}
                </td>
                <td style="text-align:right;" style="color:#059669;font-weight:bold;">
                    {{ $c ? '₡'.number_format($c['dado'], 0, '.', ',') : '—' }}
                </td>
                <td style="text-align:right;" class="{{ $c && $c['saldo'] >= 0 ? 'pct-ok' : 'pct-bad' }}">
                    {{ $c ? ($c['saldo'] >= 0 ? '+' : '').'₡'.number_format($c['saldo'], 0, '.', ',') : '—' }}
                </td>
                <td style="text-align:center;" class="{{ $pctCumplClass }}">
                    {{ $c ? $c['pct'].'%' : '—' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>{{ $tenantSiglas }} — {{ $tenantNombre }} | Confidencial</p>
    </div>
</body>
</html>

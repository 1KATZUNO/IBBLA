<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Asistencia por clase - {{ $reporte['desde']->format('d/m/Y') }} al {{ $reporte['hasta']->format('d/m/Y') }}</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; margin: 0; padding: 0; color: #1f2937; }
        .header { display: flex; align-items: center; margin-bottom: 15px; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; }
        .header img { width: 60px; height: 60px; margin-right: 15px; }
        .header-text { flex: 1; }
        .header-text h1 { margin: 0; color: #1f2937; font-size: 18px; }
        .header-text h2 { margin: 5px 0 0 0; color: #3b82f6; font-size: 12px; font-weight: normal; }
        .header-text p { margin: 3px 0 0 0; color: #6b7280; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        .kpis td { width: 25%; padding: 8px 10px; border: 1px solid #d1d5db; border-left: 3px solid #3b82f6; background-color: #f9fafb; }
        .kpis .etiqueta { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .kpis .valor { font-size: 15px; font-weight: bold; margin-top: 3px; }
        .nota { margin: 10px 0 12px 0; padding: 8px 10px; background-color: #eff6ff; border: 1px solid #bfdbfe; font-size: 8.5px; color: #1e3a8a; line-height: 1.5; }
        .nota strong { color: #1e40af; }
        .bloque { page-break-inside: avoid; margin-bottom: 12px; }
        .clase-titulo { padding: 6px 8px; font-weight: bold; font-size: 11px; color: #1f2937; border: 1px solid #d1d5db; border-bottom: none; }
        table.datos th, table.datos td { border: 1px solid #d1d5db; padding: 3px 6px; font-size: 9.5px; }
        table.datos th { background-color: #3b82f6; color: white; font-weight: bold; text-align: center; }
        table.datos th.izq { text-align: left; }
        table.datos tr { page-break-inside: avoid; }
        td.num { text-align: center; }
        .total-row td { font-weight: bold; background-color: #dbeafe; border-top: 2px solid #3b82f6; }
        .promedio-row td { font-weight: bold; background-color: #fef3c7; }
        .general-row td { font-weight: bold; background-color: #e5e7eb; }
        .sin-registro { margin-top: 12px; font-size: 8.5px; color: #4b5563; line-height: 1.5; }
        .vacio { margin-top: 20px; padding: 20px; text-align: center; color: #6b7280; border: 1px dashed #d1d5db; }
        .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
    </style>
</head>
<body>
    @php
        extract(tenant_pdf_data());
        $formato = fn ($promedio) => \App\Services\ReporteAsistenciaClasesService::formatearPromedio($promedio);
        $general = $reporte['general'];
        $variasClases = count($reporte['clases']) > 1;
        $fechas = fn ($cantidad) => $cantidad.' '.($cantidad === 1 ? 'fecha' : 'fechas');
        // DomPDF no entiende colores con transparencia: se mezcla el color de la clase con blanco
        $tinte = function (string $color, float $peso = 0.15): string {
            $hex = ltrim($color, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }
            if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
                return '#eff6ff';
            }
            [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));
            $mezclar = fn ($canal) => (int) round($canal * $peso + 255 * (1 - $peso));

            return sprintf('#%02x%02x%02x', $mezclar($r), $mezclar($g), $mezclar($b));
        };
    @endphp
    <div class="header">
        <div style="background-color: {{ $tenantColor }}; border-radius: 50%; width: 70px; height: 70px; text-align: center; padding-left: 3px; margin-right: 15px;">
            <img src="data:image/png;base64,{{ $tenantLogoBase64 }}" style="width: 50px; height: 50px; margin-top: 10px;" alt="Logo">
        </div>
        <div class="header-text">
            <h1>{{ $tenantSiglas }} - {{ $tenantNombre }}</h1>
            <h2>Reporte de Asistencia por Clase - del {{ $reporte['desde']->format('d/m/Y') }} al {{ $reporte['hasta']->format('d/m/Y') }}</h2>
            <p><strong>Clase:</strong> {{ $reporte['clase_nombre'] ?? 'Todas las clases' }} &nbsp;|&nbsp; <strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    @if(count($reporte['clases']) === 0)
        <div class="vacio">No hay asistencia por clase registrada en este rango.</div>
    @else
        <table class="kpis">
            <tr>
                <td><div class="etiqueta">Promedio niños</div><div class="valor">{{ $formato($general['promedios']['ninos']) }}</div></td>
                <td><div class="etiqueta">Promedio niñas</div><div class="valor">{{ $formato($general['promedios']['ninas']) }}</div></td>
                <td><div class="etiqueta">Promedio maestros</div><div class="valor">{{ $formato($general['promedios']['maestros']) }}</div></td>
                <td><div class="etiqueta">Promedio total por fecha</div><div class="valor">{{ $formato($general['promedios']['total']) }}</div></td>
            </tr>
        </table>

        <div class="nota">
            <strong>Cómo se calcula el promedio:</strong>
            suma de la clase en el rango ÷ fechas en que la clase tiene registro (niños, niñas, maestros y total).
            Las fechas sin registro no cuentan, así que no bajan el promedio.
            El promedio general es la suma de todas las clases ÷ fechas con al menos una clase registrada ({{ $fechas($general['fechas']) }}).
            Total = niños + niñas + maestros.
            @if($reporte['incluir_ceros'])
                Se cuentan también las fechas en 0.
            @else
                Las fechas con 0 niños, 0 niñas y 0 maestros se tratan como sin registro.
            @endif
        </div>

        @foreach($reporte['clases'] as $clase)
            <div class="bloque">
                <div class="clase-titulo" style="background-color: {{ $tinte($clase['color']) }}; border-left: 4px solid {{ $clase['color'] }};">
                    {{ $clase['nombre'] }}{{ $clase['activa'] ? '' : ' (inactiva)' }}
                </div>
                <table class="datos">
                    <thead>
                        <tr>
                            <th class="izq" style="width: 20%;">Fecha</th>
                            <th class="izq" style="width: 28%;">Culto</th>
                            <th style="width: 13%;">Niños</th>
                            <th style="width: 13%;">Niñas</th>
                            <th style="width: 13%;">Maestros</th>
                            <th style="width: 13%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clase['filas'] as $fila)
                            <tr>
                                <td>{{ $fila['fecha']->isoFormat('ddd DD/MM/YYYY') }}</td>
                                <td>{{ $fila['cultos'] }}</td>
                                <td class="num">{{ $fila['ninos'] }}</td>
                                <td class="num">{{ $fila['ninas'] }}</td>
                                <td class="num">{{ $fila['maestros'] }}</td>
                                <td class="num"><strong>{{ $fila['total'] }}</strong></td>
                            </tr>
                        @endforeach
                        <tr class="total-row">
                            <td colspan="2">TOTAL ({{ $fechas($clase['fechas']) }})</td>
                            <td class="num">{{ $clase['totales']['ninos'] }}</td>
                            <td class="num">{{ $clase['totales']['ninas'] }}</td>
                            <td class="num">{{ $clase['totales']['maestros'] }}</td>
                            <td class="num">{{ $clase['totales']['total'] }}</td>
                        </tr>
                        <tr class="promedio-row">
                            <td colspan="2">PROMEDIO (total ÷ {{ $fechas($clase['fechas']) }})</td>
                            <td class="num">{{ $formato($clase['promedios']['ninos']) }}</td>
                            <td class="num">{{ $formato($clase['promedios']['ninas']) }}</td>
                            <td class="num">{{ $formato($clase['promedios']['maestros']) }}</td>
                            <td class="num">{{ $formato($clase['promedios']['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endforeach

        @if($variasClases)
            <div class="bloque">
                <div class="clase-titulo" style="background-color: #f3f4f6; border-left: 4px solid #6b7280;">Todas las clases</div>
                <table class="datos">
                    <thead>
                        <tr>
                            <th class="izq" style="width: 48%;" colspan="2">&nbsp;</th>
                            <th style="width: 13%;">Niños</th>
                            <th style="width: 13%;">Niñas</th>
                            <th style="width: 13%;">Maestros</th>
                            <th style="width: 13%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="general-row">
                            <td colspan="2">TOTAL GENERAL ({{ $fechas($general['fechas']) }})</td>
                            <td class="num">{{ $general['totales']['ninos'] }}</td>
                            <td class="num">{{ $general['totales']['ninas'] }}</td>
                            <td class="num">{{ $general['totales']['maestros'] }}</td>
                            <td class="num">{{ $general['totales']['total'] }}</td>
                        </tr>
                        <tr class="promedio-row">
                            <td colspan="2">PROMEDIO GENERAL (total ÷ {{ $fechas($general['fechas']) }})</td>
                            <td class="num">{{ $formato($general['promedios']['ninos']) }}</td>
                            <td class="num">{{ $formato($general['promedios']['ninas']) }}</td>
                            <td class="num">{{ $formato($general['promedios']['maestros']) }}</td>
                            <td class="num">{{ $formato($general['promedios']['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    @if(count($reporte['fechas_sin_registro']) > 0)
        <div class="sin-registro">
            <strong>Fechas del rango sin registro{{ $reporte['clase_id'] ? ' de esta clase' : ' de clases' }} (no cuentan en el promedio):</strong>
            {{ collect($reporte['fechas_sin_registro'])->map(fn ($fecha) => $fecha->format('d/m/Y'))->implode(', ') }}
        </div>
    @endif

    <div class="footer">
        <p>Sistema de Administración - {{ $tenantSiglas }} - {{ $tenantNombre }}</p>
    </div>
</body>
</html>

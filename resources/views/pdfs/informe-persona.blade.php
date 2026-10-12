<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de {{ $persona->nombre }} - {{ tenant()?->siglas ?? 'Admin' }}</title>
    <style>
        /* Sin el reset «* { margin: 0 }» de los otros PDF: DomPDF lo aplica también al <html> y se
           come el margen de @page (el contenido llegaría al borde de la hoja). */
        @page {
            margin: 14mm 12mm;
        }

        body {
            margin: 0;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #333;
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        h1, h2, h3, p {
            margin: 0;
            padding: 0;
        }

        .header td {
            vertical-align: middle;
            padding: 0 0 10px 0;
            border-bottom: 3px solid #1e40af;
            text-align: left;
        }

        .header .logo-cell {
            width: 66px;
        }

        .logo {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            text-align: center;
        }

        .logo img {
            width: 44px;
            height: 44px;
            margin-top: 6px;
        }

        .header h1 {
            font-size: 18px;
            color: #1e40af;
            margin-bottom: 3px;
        }

        .header h2 {
            font-size: 11px;
            color: #666;
            font-weight: normal;
        }

        .persona {
            margin: 14px 0;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .persona td {
            padding: 9px 12px;
            vertical-align: middle;
            text-align: left;
        }

        .persona .nombre {
            font-size: 15px;
            font-weight: bold;
            color: #1e3a8a;
        }

        .persona .periodo {
            text-align: right;
            font-size: 11px;
            font-weight: bold;
            color: #1e40af;
        }

        .resumen {
            margin-bottom: 16px;
        }

        .resumen td {
            width: 33%;
            padding: 0 4px;
            text-align: center;
        }

        .resumen td.primero {
            padding-left: 0;
        }

        .resumen td.ultimo {
            padding-right: 0;
        }

        .tarjeta {
            padding: 9px 10px;
            border: 1px solid #d1d5db;
            background: #f9fafb;
            text-align: center;
        }

        .tarjeta .etiqueta {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .tarjeta .valor {
            font-size: 14px;
            font-weight: bold;
        }

        .verde { color: #166534; }
        .azul { color: #1d4ed8; }
        .rojo { color: #b91c1c; }
        .ambar { color: #b45309; }

        h3 {
            font-size: 12px;
            color: #1e40af;
            margin: 4px 0 6px 0;
        }

        /* DomPDF inserta <tbody> solo: las reglas de celda van acotadas a las tablas de datos. */
        .datos thead th {
            background: #1e40af;
            color: #fff;
            padding: 7px 5px;
            font-size: 8px;
            text-transform: uppercase;
            text-align: right;
        }

        .datos thead th.izq {
            text-align: left;
        }

        .datos tbody td {
            padding: 6px 5px;
            border-bottom: 1px solid #e5e7eb;
            text-align: right;
            font-size: 9px;
        }

        .datos tbody td.izq {
            text-align: left;
        }

        .datos tbody tr.par td {
            background: #f9fafb;
        }

        .datos tbody tr.total td {
            background: #dbeafe;
            border-top: 2px solid #1e40af;
            font-weight: bold;
            color: #1e40af;
        }

        .datos td.mes {
            text-align: left;
            font-weight: bold;
            color: #1f2937;
        }

        .datos td.destacado {
            font-weight: bold;
            color: #166534;
            background: #f0fdf4;
        }

        .compromisos {
            margin-bottom: 16px;
        }

        .compromisos .datos thead th {
            background: #6d28d9;
        }

        .nota {
            margin-top: 12px;
            padding: 8px 10px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #78350f;
            font-size: 8px;
            line-height: 1.5;
        }

        .sin-datos {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            font-style: italic;
            font-size: 11px;
        }

        .footer {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    @php extract(tenant_pdf_data()); @endphp

    <table class="header">
        <tr>
            <td class="logo-cell">
                <div class="logo" style="background-color: {{ $tenantColor }};">
                    <img src="data:image/png;base64,{{ $tenantLogoBase64 }}" alt="Logo">
                </div>
            </td>
            <td>
                <h1>Informe de contribuciones y compromisos</h1>
                <h2>{{ $tenantSiglas }} - {{ $tenantNombre }}</h2>
            </td>
        </tr>
    </table>

    <table class="persona">
        <tr>
            <td class="nombre">{{ $persona->nombre }}</td>
            <td class="periodo">{{ $periodo }}</td>
        </tr>
    </table>

    <table class="resumen">
        <tr>
            <td class="primero {{ $tiene_compromisos ? '' : 'ultimo' }}" @if(! $tiene_compromisos) style="width: 100%;" @endif>
                <div class="tarjeta">
                    <div class="etiqueta">Total contribuido en {{ $anio }}</div>
                    <div class="valor verde">₡{{ number_format($total_dado, 2) }}</div>
                </div>
            </td>
            @if($tiene_compromisos)
            <td>
                <div class="tarjeta">
                    <div class="etiqueta">Compromisos a la fecha</div>
                    <div class="valor azul">₡{{ number_format($total_esperado, 2) }}</div>
                </div>
            </td>
            <td class="ultimo">
                <div class="tarjeta">
                    <div class="etiqueta">Diferencia en compromisos</div>
                    <div class="valor {{ $diferencia >= 0 ? 'verde' : 'rojo' }}">{{ $diferencia >= 0 ? '+' : '-' }}₡{{ number_format(abs($diferencia), 2) }}</div>
                </div>
            </td>
            @endif
        </tr>
    </table>

    @if($tiene_compromisos)
    <div class="compromisos">
        <h3>Compromisos</h3>
        <table class="datos">
            <thead>
                <tr>
                    <th class="izq">Categoría</th>
                    <th class="izq">Compromiso</th>
                    <th>Esperado a la fecha</th>
                    <th>Dado</th>
                    <th>Diferencia</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($compromisos as $c)
                <tr class="{{ $loop->even ? 'par' : '' }}">
                    <td class="mes">{{ $c['nombre'] }}</td>
                    <td class="izq">{{ $c['detalle'] }}</td>
                    <td>₡{{ number_format($c['esperado'], 2) }}</td>
                    <td>₡{{ number_format($c['dado'], 2) }}</td>
                    <td class="{{ $c['diferencia'] >= 0 ? 'verde' : 'rojo' }}">{{ $c['diferencia'] >= 0 ? '+' : '-' }}₡{{ number_format(abs($c['diferencia']), 2) }}</td>
                    <td class="{{ $c['al_dia'] ? 'verde' : 'ambar' }}">{{ $c['al_dia'] ? 'Al día' : 'Pendiente' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(count($categorias) > 0)
    <h3>Contribuciones por mes</h3>
    <table class="datos">
        <thead>
            <tr>
                <th class="izq">Mes</th>
                @foreach($categorias as $slug => $nombre)
                <th>{{ $nombre }}</th>
                @endforeach
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($meses as $m)
            <tr class="{{ $loop->even ? 'par' : '' }}">
                <td class="mes">{{ $m['nombre'] }}</td>
                @foreach($categorias as $slug => $nombre)
                <td>{{ $m['por_categoria'][$slug] > 0 ? '₡'.number_format($m['por_categoria'][$slug], 2) : '-' }}</td>
                @endforeach
                <td class="destacado">{{ $m['total'] > 0 ? '₡'.number_format($m['total'], 2) : '-' }}</td>
            </tr>
            @endforeach
            <tr class="total">
                <td class="izq">TOTAL</td>
                @foreach($categorias as $slug => $nombre)
                <td>₡{{ number_format($totales_categoria[$slug], 2) }}</td>
                @endforeach
                <td>₡{{ number_format($total_dado, 2) }}</td>
            </tr>
        </tbody>
    </table>
    @else
    <div class="sin-datos">No hay contribuciones registradas en {{ $anio }}.</div>
    @endif

    <div class="nota">
        Este informe resume lo registrado en el periodo indicado ({{ $periodo }}).
        @if($hay_usd)
        Los sobres en dólares se muestran convertidos a colones con el tipo de cambio guardado en cada sobre.
        @endif
        @if($tiene_compromisos)
        «Esperado a la fecha» suma, por cada compromiso, el monto de cada mes completo desde enero hasta el mes en curso.
        @endif
        Si nota alguna diferencia, comuníquese con la iglesia.
    </div>

    <div class="footer">
        <p>Generado el {{ \Carbon\Carbon::now()->locale('es')->translatedFormat('d \d\e F \d\e Y \a \l\a\s h:i A') }}</p>
        <p>Sistema de Administracion - {{ $tenantSiglas }} - {{ $tenantNombre }}</p>
    </div>
</body>
</html>

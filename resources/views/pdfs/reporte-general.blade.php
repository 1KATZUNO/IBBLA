<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte General - IBBSC</title>
    <style>
        @page {
            size: landscape;
            margin: 10mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            color: #333;
            background: #fff;
        }

        .container {
            padding: 5px;
        }

        .header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #1e40af;
        }

        .logo {
            width: 50px;
            height: 50px;
            margin-right: 10px;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .header-text h1 {
            font-size: 16px;
            color: #1e40af;
            margin-bottom: 3px;
        }

        .header-text h2 {
            font-size: 10px;
            color: #666;
            font-weight: normal;
        }

        .periodo {
            background: #eff6ff;
            padding: 8px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid #bfdbfe;
        }

        .periodo p {
            font-size: 11px;
            font-weight: bold;
            color: #1e40af;
        }

        .persona-block {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .persona-nombre {
            background: #1e3a8a;
            color: white;
            padding: 8px 12px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 6px 6px 0 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #3b82f6;
            color: white;
            padding: 6px 4px;
            text-align: center;
            font-size: 7px;
            text-transform: uppercase;
            font-weight: bold;
            border: 1px solid #2563eb;
        }

        thead th.col-mes {
            width: 8%;
            text-align: left;
        }

        thead th.col-tipo {
            width: 5%;
        }

        thead th.col-cat {
            width: 11%;
        }

        thead th.col-total {
            width: 10%;
            background: #166534;
            border-color: #15803d;
        }

        thead th.col-dif {
            width: 10%;
            background: #7c3aed;
            border-color: #6d28d9;
        }

        tbody td {
            padding: 4px 3px;
            text-align: right;
            border: 1px solid #e5e7eb;
            font-size: 7px;
        }

        tbody td:first-child {
            text-align: left;
            font-weight: 600;
        }

        .row-dado {
            background: #f0fdf4;
        }

        .row-dado td {
            color: #166534;
        }

        .row-esperado {
            background: #eff6ff;
        }

        .row-esperado td {
            color: #1d4ed8;
        }

        .col-mes-name {
            text-align: left !important;
            font-weight: bold;
            background: #f3f4f6;
        }

        .col-tipo-cell {
            text-align: center !important;
            font-weight: bold;
            font-size: 6px;
        }

        .col-total-cell {
            background: #dcfce7 !important;
            color: #166534 !important;
            font-weight: bold;
        }

        .col-dif-cell {
            font-weight: bold;
        }

        .col-dif-positive {
            background: #dcfce7 !important;
            color: #166534 !important;
        }

        .col-dif-negative {
            background: #fef2f2 !important;
            color: #dc2626 !important;
        }

        .totales-persona {
            background: #fef3c7 !important;
        }

        .totales-persona td {
            font-weight: bold;
            color: #92400e;
            padding: 6px 4px;
            border-top: 2px solid #f59e0b;
        }

        .totales-generales {
            margin-top: 25px;
            page-break-inside: avoid;
        }

        .totales-generales-header {
            background: #7c3aed;
            color: white;
            padding: 10px 15px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 6px 6px 0 0;
            text-align: center;
        }

        .totales-generales table {
            border: 2px solid #7c3aed;
        }

        .totales-generales thead th {
            background: #8b5cf6;
            border-color: #7c3aed;
        }

        .totales-generales .row-dado {
            background: #f0fdf4;
        }

        .totales-generales .row-esperado {
            background: #eff6ff;
        }

        .totales-finales {
            background: #7c3aed !important;
        }

        .totales-finales td {
            color: white !important;
            font-weight: bold;
            font-size: 8px;
            padding: 8px 4px;
        }

        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 7px;
            color: #9ca3af;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #6b7280;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/Logo2.png'))) }}" alt="Logo IBBSC">
            </div>
            <div class="header-text">
                <h1>Reporte General de Compromisos</h1>
                <h2>Iglesia Biblica Bautista en Santa Cruz</h2>
            </div>
        </div>

        <div class="periodo">
            <p>Periodo: Enero - {{ $mesesNombres[$mesActual] }} {{ $anioActual }}</p>
        </div>

        @if(count($reporteData) > 0)
            @foreach($reporteData as $persona)
            <div class="persona-block">
                <div class="persona-nombre">{{ $persona['nombre'] }}</div>
                <table>
                    <thead>
                        <tr>
                            <th class="col-mes">Mes</th>
                            <th class="col-tipo"></th>
                            @foreach($categoriasConPromesa as $cat)
                            <th class="col-cat">{{ ucfirst($cat) }}</th>
                            @endforeach
                            <th class="col-total">Total</th>
                            <th class="col-dif">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($persona['meses'] as $idx => $mesDatos)
                        {{-- Fila de Dado (verde) --}}
                        <tr class="row-dado">
                            <td class="col-mes-name" rowspan="2">{{ $mesDatos['nombre'] }}</td>
                            <td class="col-tipo-cell">Dado</td>
                            @foreach($categoriasConPromesa as $cat)
                            <td>{{ number_format($mesDatos['categorias'][$cat]['dado'], 0) }}</td>
                            @endforeach
                            <td class="col-total-cell" rowspan="2">{{ number_format($mesDatos['total_dado'], 0) }}</td>
                            <td class="col-dif-cell {{ $mesDatos['diferencia'] >= 0 ? 'col-dif-positive' : 'col-dif-negative' }}" rowspan="2">
                                {{ $mesDatos['diferencia'] >= 0 ? '+' : '' }}{{ number_format($mesDatos['diferencia'], 0) }}
                            </td>
                        </tr>
                        {{-- Fila de Esperado (azul) --}}
                        <tr class="row-esperado">
                            <td class="col-tipo-cell">Esperado</td>
                            @foreach($categoriasConPromesa as $cat)
                            <td>{{ number_format($mesDatos['categorias'][$cat]['esperado'], 0) }}</td>
                            @endforeach
                        </tr>
                        @endforeach
                        {{-- Fila de totales de la persona --}}
                        <tr class="totales-persona">
                            <td colspan="2">TOTAL {{ strtoupper($persona['nombre']) }}</td>
                            @foreach($categoriasConPromesa as $cat)
                            @php
                                $totalCatDado = 0;
                                foreach($persona['meses'] as $m) {
                                    $totalCatDado += $m['categorias'][$cat]['dado'];
                                }
                            @endphp
                            <td>{{ number_format($totalCatDado, 0) }}</td>
                            @endforeach
                            <td class="col-total-cell">{{ number_format($persona['totales']['dado'], 0) }}</td>
                            <td class="col-dif-cell {{ $persona['totales']['diferencia'] >= 0 ? 'col-dif-positive' : 'col-dif-negative' }}">
                                {{ $persona['totales']['diferencia'] >= 0 ? '+' : '' }}{{ number_format($persona['totales']['diferencia'], 0) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @endforeach

            {{-- Totales Generales --}}
            <div class="totales-generales">
                <div class="totales-generales-header">TOTALES GENERALES - TODAS LAS PERSONAS</div>
                <table>
                    <thead>
                        <tr>
                            <th class="col-mes">Mes</th>
                            <th class="col-tipo"></th>
                            @foreach($categoriasConPromesa as $cat)
                            <th class="col-cat">{{ ucfirst($cat) }}</th>
                            @endforeach
                            <th class="col-total">Total</th>
                            <th class="col-dif">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for($mes = 1; $mes <= $mesActual; $mes++)
                        @php $mesData = $totalesGenerales['meses'][$mes]; @endphp
                        {{-- Fila de Dado (verde) --}}
                        <tr class="row-dado">
                            <td class="col-mes-name" rowspan="2">{{ $mesesNombres[$mes] }}</td>
                            <td class="col-tipo-cell">Dado</td>
                            @foreach($categoriasConPromesa as $cat)
                            <td>{{ number_format($mesData['categorias'][$cat]['dado'], 0) }}</td>
                            @endforeach
                            <td class="col-total-cell" rowspan="2">{{ number_format($mesData['total_dado'], 0) }}</td>
                            <td class="col-dif-cell {{ $mesData['diferencia'] >= 0 ? 'col-dif-positive' : 'col-dif-negative' }}" rowspan="2">
                                {{ $mesData['diferencia'] >= 0 ? '+' : '' }}{{ number_format($mesData['diferencia'], 0) }}
                            </td>
                        </tr>
                        {{-- Fila de Esperado (azul) --}}
                        <tr class="row-esperado">
                            <td class="col-tipo-cell">Esperado</td>
                            @foreach($categoriasConPromesa as $cat)
                            <td>{{ number_format($mesData['categorias'][$cat]['esperado'], 0) }}</td>
                            @endforeach
                        </tr>
                        @endfor
                        {{-- Fila de totales finales --}}
                        <tr class="totales-finales">
                            <td colspan="2">GRAN TOTAL</td>
                            @foreach($categoriasConPromesa as $cat)
                            @php
                                $totalCat = 0;
                                for($m = 1; $m <= $mesActual; $m++) {
                                    $totalCat += $totalesGenerales['meses'][$m]['categorias'][$cat]['dado'];
                                }
                            @endphp
                            <td>{{ number_format($totalCat, 0) }}</td>
                            @endforeach
                            <td>{{ number_format($totalesGenerales['total_dado'], 0) }}</td>
                            <td class="{{ $totalesGenerales['total_diferencia'] >= 0 ? 'col-dif-positive' : 'col-dif-negative' }}">
                                {{ $totalesGenerales['total_diferencia'] >= 0 ? '+' : '' }}{{ number_format($totalesGenerales['total_diferencia'], 0) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
        <div class="no-data">
            No hay datos de compromisos para mostrar en este periodo.
        </div>
        @endif

        <div class="footer">
            <p>Generado el {{ \Carbon\Carbon::now()->locale('es')->translatedFormat('d \d\e F \d\e Y \a \l\a\s h:i A') }}</p>
            <p>IBBSC - Sistema de Administracion</p>
        </div>
    </div>
</body>
</html>

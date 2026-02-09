<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Contribuciones - IBBSC</title>
    <style>
        @page {
            size: landscape;
            margin: 15mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #333;
            background: #fff;
        }

        .container {
            padding: 10px;
        }

        .header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #1e40af;
        }

        .logo {
            width: 60px;
            height: 60px;
            margin-right: 15px;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .header-text h1 {
            font-size: 20px;
            color: #1e40af;
            margin-bottom: 4px;
        }

        .header-text h2 {
            font-size: 12px;
            color: #666;
            font-weight: normal;
        }

        .periodo {
            background: #eff6ff;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #bfdbfe;
        }

        .periodo p {
            font-size: 14px;
            font-weight: bold;
            color: #1e40af;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        thead th {
            background: #1e40af;
            color: white;
            padding: 10px 8px;
            text-align: center;
            font-size: 9px;
            text-transform: uppercase;
            font-weight: bold;
        }

        thead th:first-child {
            text-align: left;
            width: 20%;
        }

        tbody td {
            padding: 8px 6px;
            text-align: right;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }

        tbody td:first-child {
            text-align: left;
            font-weight: 600;
            color: #1f2937;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        tbody tr:hover {
            background: #eff6ff;
        }

        .total-row {
            background: #dbeafe !important;
            font-weight: bold;
        }

        .total-row td {
            padding: 12px 8px;
            border-top: 2px solid #1e40af;
            font-size: 10px;
            color: #1e40af;
        }

        .total-col {
            background: #f0fdf4;
            font-weight: bold;
            color: #166534;
        }

        .resumen {
            margin-top: 20px;
            padding: 15px;
            background: #f0fdf4;
            border-radius: 8px;
            border: 1px solid #86efac;
        }

        .resumen h3 {
            font-size: 12px;
            color: #166534;
            margin-bottom: 10px;
        }

        .resumen-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .resumen-item {
            background: white;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #d1fae5;
            text-align: center;
        }

        .resumen-item label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            display: block;
            margin-bottom: 4px;
        }

        .resumen-item value {
            font-size: 14px;
            font-weight: bold;
            color: #166534;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
            padding-top: 10px;
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
                <h1>Reporte de Contribuciones</h1>
                <h2>Iglesia Bíblica Bautista en Santa Cruz</h2>
            </div>
        </div>

        <div class="periodo">
            <p>Acumulado: {{ $tituloPeriodo }}</p>
        </div>

        @if($personas->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Diezmo</th>
                    <th>Misiones</th>
                    <th>Seminario</th>
                    <th>Campamento</th>
                    <th>Construcción</th>
                    <th>Micro</th>
                    <th style="background: #166534;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($personas as $persona)
                <tr>
                    <td>{{ $persona->nombre }}</td>
                    @foreach($categorias as $cat)
                    <td>₡{{ number_format($persona->contribuciones[$cat] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="total-col">₡{{ number_format($persona->total_dado, 2) }}</td>
                </tr>
                @endforeach

                <tr class="total-row">
                    <td>TOTALES</td>
                    @foreach($categorias as $cat)
                    <td>₡{{ number_format($totalesPorCategoria[$cat] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="total-col">₡{{ number_format($totalGeneral, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="resumen">
            <h3>Resumen General</h3>
            <div class="resumen-grid">
                <div class="resumen-item">
                    <label>Total Personas</label>
                    <value>{{ $personas->count() }}</value>
                </div>
                <div class="resumen-item">
                    <label>Total Recaudado</label>
                    <value>₡{{ number_format($totalGeneral, 2) }}</value>
                </div>
                @foreach($categorias as $cat)
                <div class="resumen-item">
                    <label>{{ ucfirst($cat) }}</label>
                    <value>₡{{ number_format($totalesPorCategoria[$cat] ?? 0, 2) }}</value>
                </div>
                @endforeach
            </div>
        </div>
        @else
        <div class="no-data">
            No hay contribuciones registradas en este período.
        </div>
        @endif

        <div class="footer">
            <p>Generado el {{ \Carbon\Carbon::now()->locale('es')->translatedFormat('d \d\e F \d\e Y \a \l\a\s h:i A') }}</p>
            <p>IBBSC - Sistema de Administración</p>
        </div>
    </div>
</body>
</html>

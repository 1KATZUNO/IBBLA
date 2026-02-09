<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Personas - IBBSC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page { 
            size: landscape; 
            margin: 12mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 9px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #4facfe 75%, #00f2fe 100%);
            background-size: 400% 400%;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            animation: backgroundPulse 20s ease-in-out infinite;
            pointer-events: none;
        }
        
        @keyframes backgroundPulse {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.1); }
        }
        
        .container {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1) inset,
                0 0 100px rgba(138, 96, 255, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Header con estilo Gemini */
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            border-radius: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 8px 32px rgba(102, 126, 234, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.1) inset;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }
        
        .header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 10s ease-in-out infinite reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-20px, -20px) scale(1.1); }
        }
        
        .header-content {
            display: flex;
            align-items: center;
            position: relative;
            z-index: 2;
            width: 100%;
        }
        
        .logo-container {
            width: 70px;
            height: 70px;
            margin-right: 20px;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border-radius: 18px;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }
        
        .header-text {
            flex: 1;
        }
        
        .header-text h1 {
            font-family: 'Google Sans', 'Inter', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 4px 0;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .header-text h2 {
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.95);
            margin: 0;
            letter-spacing: 0.2px;
        }
        
        /* Período Badge */
        .periodo {
            background: linear-gradient(135deg, 
                rgba(102, 126, 234, 0.1) 0%, 
                rgba(118, 75, 162, 0.1) 50%, 
                rgba(240, 147, 251, 0.1) 100%);
            backdrop-filter: blur(10px);
            padding: 16px 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            text-align: center;
            border: 2px solid rgba(102, 126, 234, 0.3);
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .periodo::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { left: -100%; }
            50%, 100% { left: 100%; }
        }
        
        .periodo p {
            margin: 0;
            font-family: 'Google Sans', 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            z-index: 1;
        }
        
        /* Tabla con estilo Gemini */
        .table-container {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 16px;
            margin-bottom: 24px;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2) inset;
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 8px;
        }
        
        table thead tr:first-child th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: 600;
            font-size: 9px;
            font-family: 'Google Sans', 'Inter', sans-serif;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            position: relative;
            border: none;
        }
        
        table thead tr:first-child th:first-child {
            border-top-left-radius: 12px;
        }
        
        table thead tr:first-child th:last-child {
            border-top-right-radius: 12px;
        }
        
        table thead tr:last-child th {
            background: linear-gradient(135deg, #5568d3 0%, #653d8b 100%);
            color: rgba(255, 255, 255, 0.95);
            padding: 8px 4px;
            font-size: 7px;
            font-weight: 500;
            border: none;
        }
        
        table tbody td {
            padding: 10px 6px;
            text-align: center;
            background: rgba(255, 255, 255, 0.8);
            border: none;
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
        }
        
        table tbody tr:hover td {
            background: rgba(102, 126, 234, 0.05);
            transform: scale(1.01);
        }
        
        .nombre-col {
            text-align: left;
            font-weight: 600;
            color: #1a1a2e;
            font-size: 9px;
            font-family: 'Google Sans', 'Inter', sans-serif;
        }
        
        .cumple {
            background: linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%) !important;
            color: #1a5f3a;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(150, 230, 161, 0.4);
            position: relative;
        }
        
        .cumple::before {
            content: '✓';
            position: absolute;
            left: 4px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            color: #1a5f3a;
            font-weight: bold;
        }
        
        .no-cumple {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%) !important;
            color: #7d1f3a;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(245, 87, 108, 0.4);
            position: relative;
        }
        
        .no-cumple::before {
            content: '!';
            position: absolute;
            left: 4px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            color: #7d1f3a;
            font-weight: bold;
        }
        
        .total-row {
            background: linear-gradient(135deg, 
                rgba(102, 126, 234, 0.2) 0%, 
                rgba(118, 75, 162, 0.2) 100%) !important;
            font-weight: 700;
            color: #4a3f8f;
            font-size: 9px;
        }
        
        .total-row td {
            padding: 12px 8px !important;
            border-top: 2px solid rgba(102, 126, 234, 0.4) !important;
            font-family: 'Google Sans', 'Inter', sans-serif;
        }
        
        /* Resumen Final - Estilo Gemini Avanzado */
        .resumen-final {
            margin-top: 24px;
            padding: 24px;
            background: linear-gradient(135deg, 
                rgba(102, 126, 234, 0.15) 0%, 
                rgba(118, 75, 162, 0.15) 50%, 
                rgba(240, 147, 251, 0.15) 100%);
            backdrop-filter: blur(20px) saturate(180%);
            border-radius: 20px;
            border: 2px solid rgba(102, 126, 234, 0.3);
            box-shadow: 
                0 12px 40px rgba(102, 126, 234, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.2) inset;
            position: relative;
            overflow: hidden;
        }
        
        .resumen-final::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -25%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(240, 147, 251, 0.3) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse 4s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.8; }
        }
        
        .resumen-final h3 {
            margin: 0 0 16px 0;
            font-family: 'Google Sans', 'Inter', sans-serif;
            font-size: 16px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            z-index: 2;
            letter-spacing: -0.3px;
        }
        
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            position: relative;
            z-index: 2;
        }
        
        .resumen-item {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 16px 12px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.08),
                0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .resumen-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .resumen-item:hover {
            transform: translateY(-4px);
            box-shadow: 
                0 12px 48px rgba(102, 126, 234, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.2) inset;
        }
        
        .resumen-item:hover::before {
            transform: scaleX(1);
        }
        
        .resumen-item label {
            display: block;
            font-size: 9px;
            font-weight: 600;
            color: #6366f1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-family: 'Inter', sans-serif;
        }
        
        .resumen-item value {
            display: block;
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-family: 'Google Sans', 'Inter', sans-serif;
            letter-spacing: -0.5px;
        }
        
        /* Footer */
        .footer {
            margin-top: 24px;
            text-align: center;
            font-size: 8px;
            color: rgba(102, 126, 234, 0.7);
            padding: 16px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .footer p {
            margin: 4px 0;
        }
        
        .footer p:first-child {
            font-weight: 600;
            color: #667eea;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: rgba(102, 126, 234, 0.6);
            font-style: italic;
            font-size: 14px;
            font-family: 'Google Sans', 'Inter', sans-serif;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 16px;
            border: 2px dashed rgba(102, 126, 234, 0.3);
        }
        
        /* Animaciones adicionales */
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }
        
        .shimmer {
            animation: shimmer 3s infinite;
            background: linear-gradient(to right, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%);
            background-size: 1000px 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Estilo Gemini -->
        <div class="header">
            <div class="header-content">
                <div class="logo-container">
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/Logo2.png'))) }}" alt="Logo IBBSC">
                </div>
                <div class="header-text">
                    <h1>Reporte de Personas</h1>
                    <h2>Iglesia Bautista Bíblica Shekinah de la Ciudad</h2>
                </div>
            </div>
        </div>

        <!-- Período Badge -->
        <div class="periodo">
            <p>📊 Período: {{ $tituloPeriodo }}</p>
        </div>

        @if($personas->count() > 0)
            @php
                // Obtener todas las categorías únicas
                $categorias = [];
                foreach ($personas as $persona) {
                    foreach ($persona->promesas_periodo as $categoria => $datos) {
                        if (!in_array($categoria, $categorias)) {
                            $categorias[] = $categoria;
                        }
                    }
                }
                sort($categorias);
            @endphp

            <div class="table-container">
                <table>
            <thead>
                <tr>
                    <th rowspan="2" style="width: 12%;">NOMBRE</th>
                    @foreach($categorias as $categoria)
                        <th colspan="2">{{ strtoupper($categoria) }}</th>
                    @endforeach
                    <th rowspan="2" style="width: 7%;">TOTAL<br>ESPERADO</th>
                    <th rowspan="2" style="width: 7%;">TOTAL<br>DADO</th>
                    <th rowspan="2" style="width: 7%;">DIFERENCIA</th>
                    <th rowspan="2" style="width: 5%;">%</th>
                </tr>
                <tr>
                    @foreach($categorias as $categoria)
                        <th style="font-size: 7px; padding: 4px 2px;">Esperado</th>
                        <th style="font-size: 7px; padding: 4px 2px;">Dado</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    $totalesEsperado = array_fill_keys($categorias, 0);
                    $totalesDado = array_fill_keys($categorias, 0);
                    $granTotalEsperado = 0;
                    $granTotalDado = 0;
                @endphp

                @foreach($personas as $persona)
                    @php
                        $totalPersonaEsperado = 0;
                        $totalPersonaDado = 0;
                    @endphp
                    <tr>
                        <td class="nombre-col">{{ $persona->nombre }}</td>
                        @foreach($categorias as $categoria)
                            @php
                                $datos = $persona->promesas_periodo[$categoria] ?? ['esperado' => 0, 'dado' => 0];
                                $esperado = $datos['esperado'];
                                $dado = $datos['dado'];
                                $totalPersonaEsperado += $esperado;
                                $totalPersonaDado += $dado;
                                $totalesEsperado[$categoria] += $esperado;
                                $totalesDado[$categoria] += $dado;
                                
                                $cumple = $dado >= $esperado && $esperado > 0;
                            @endphp
                            <td class="{{ $esperado > 0 ? ($cumple ? 'cumple' : 'no-cumple') : '' }}">
                                ₡{{ number_format($esperado, 2) }}
                            </td>
                            <td class="{{ $esperado > 0 ? ($cumple ? 'cumple' : 'no-cumple') : '' }}">
                                ₡{{ number_format($dado, 2) }}
                            </td>
                        @endforeach
                        @php
                            $granTotalEsperado += $totalPersonaEsperado;
                            $granTotalDado += $totalPersonaDado;
                            $diferencia = $totalPersonaDado - $totalPersonaEsperado;
                            $porcentaje = $totalPersonaEsperado > 0 ? ($totalPersonaDado / $totalPersonaEsperado * 100) : 0;
                        @endphp
                        <td style="font-weight: bold;">₡{{ number_format($totalPersonaEsperado, 2) }}</td>
                        <td style="font-weight: bold;">₡{{ number_format($totalPersonaDado, 2) }}</td>
                        <td class="{{ $diferencia >= 0 ? 'cumple' : 'no-cumple' }}" style="font-weight: bold;">
                            ₡{{ number_format($diferencia, 2) }}
                        </td>
                        <td class="{{ $porcentaje >= 100 ? 'cumple' : ($porcentaje >= 75 ? '' : 'no-cumple') }}" style="font-weight: bold;">
                            {{ number_format($porcentaje, 0) }}%
                        </td>
                    </tr>
                @endforeach

                <!-- Fila de Totales -->
                <tr class="total-row">
                    <td>🎯 TOTALES</td>
                    @foreach($categorias as $categoria)
                        <td>₡{{ number_format($totalesEsperado[$categoria], 2) }}</td>
                        <td>₡{{ number_format($totalesDado[$categoria], 2) }}</td>
                    @endforeach
                    <td>₡{{ number_format($granTotalEsperado, 2) }}</td>
                    <td>₡{{ number_format($granTotalDado, 2) }}</td>
                    @php
                        $diferenciaTotal = $granTotalDado - $granTotalEsperado;
                        $porcentajeTotal = $granTotalEsperado > 0 ? ($granTotalDado / $granTotalEsperado * 100) : 0;
                    @endphp
                    <td>₡{{ number_format($diferenciaTotal, 2) }}</td>
                    <td>{{ number_format($porcentajeTotal, 0) }}%</td>
                </tr>
            </tbody>
        </table>
    </div>
    @else
        <div class="no-data">
            📭 No hay personas con promesas registradas en este período
        </div>
    @endif

    @if($personas->count() > 0)
    <div class="resumen-final">
        <h3>✨ Resumen General del Período</h3>
        
        @php
            // Calcular el total dado desde las promesas_periodo
            $totalDadoReal = 0;
            foreach ($personas as $p) {
                foreach ($p->promesas_periodo as $cat => $datos) {
                    $totalDadoReal += $datos['dado'];
                }
            }
        @endphp
        
        <div class="resumen-grid">
            <div class="resumen-item">
                <label>👥 Personas</label>
                <value>{{ $personas->count() }}</value>
            </div>
            <div class="resumen-item">
                <label>🎯 Total Esperado</label>
                <value>₡{{ number_format($totalPrometidoGeneral, 2) }}</value>
            </div>
            <div class="resumen-item">
                <label>💰 Total Recibido</label>
                <value>₡{{ number_format($totalDadoReal, 2) }}</value>
            </div>
            <div class="resumen-item">
                <label>📈 Cumplimiento Global</label>
                <value>{{ $totalPrometidoGeneral > 0 ? number_format(($totalDadoReal / $totalPrometidoGeneral * 100), 1) : 0 }}%</value>
            </div>
        </div>
    </div>
    @endif

    <div class="footer">
        <p>🕐 Generado el {{ \Carbon\Carbon::now()->locale('es')->translatedFormat('d \d\e F \d\e Y \a \l\a\s h:i A') }}</p>
        <p>IBBSC - Sistema de Administración | Powered by Gemini-Style Design</p>
    </div>
    </div>
</body>
</html>

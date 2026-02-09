<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Asistencia</title>
    <style>
        @page { size: landscape; margin: 15mm; }
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
        .header { display: flex; align-items: center; margin-bottom: 20px; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; }
        .header img { width: 60px; height: 60px; margin-right: 15px; }
        .header-text { flex: 1; }
        .header-text h1 { margin: 0; color: #1f2937; font-size: 18px; }
        .header-text h2 { margin: 5px 0 0 0; color: #3b82f6; font-size: 12px; font-weight: normal; }
        .header-text p { margin: 3px 0 0 0; color: #6b7280; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; font-size: 10px; }
        th { background-color: #3b82f6; color: white; font-weight: bold; text-transform: uppercase; }
        tbody tr:nth-child(even) { background-color: #f9fafb; }
        tbody tr:hover { background-color: #f3f4f6; }
        .total { font-weight: bold; color: #1f2937; background-color: #dbeafe; text-align: center; }
        .total-row { background-color: #dbeafe; font-weight: bold; border-top: 2px solid #3b82f6; }
        .summary-box { margin-top: 20px; padding: 10px; background-color: #f3f4f6; border-radius: 5px; }
        .summary-box h3 { margin: 0 0 10px 0; font-size: 12px; color: #1f2937; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .summary-item { background-color: white; padding: 8px; border-radius: 3px; border-left: 3px solid #3b82f6; }
        .summary-item .label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .summary-item .value { font-size: 14px; font-weight: bold; color: #1f2937; margin-top: 3px; }
        .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/Logo2.png'))) }}" alt="Logo IBBSC">
        <div class="header-text">
            <h1>IBBSC - Iglesia Bíblica Bautista en Santa Cruz</h1>
            <h2>Reporte de Asistencia</h2>
            <p><strong>Fecha de Generación:</strong> {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo Culto</th>
                <th>Total</th>
                <th>Hombres</th>
                <th>Mujeres</th>
                <th>Niños</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalGeneral = 0;
                $totalHombres = 0;
                $totalMujeres = 0;
                $totalNinos = 0;
            @endphp
            @foreach($cultos as $culto)
            @if($culto->asistencia)
            @php
                $hombres = $culto->asistencia->chapel_adultos_hombres + 
                          $culto->asistencia->chapel_adultos_mayores_hombres + 
                          $culto->asistencia->chapel_jovenes_masculinos + 
                          $culto->asistencia->chapel_maestros_hombres +
                          $culto->asistencia->clase_0_1_hombres + 
                          $culto->asistencia->clase_2_6_hombres + 
                          $culto->asistencia->clase_7_8_hombres + 
                          $culto->asistencia->clase_9_11_hombres +
                          $culto->asistencia->clase_0_1_maestros_hombres +
                          $culto->asistencia->clase_2_6_maestros_hombres +
                          $culto->asistencia->clase_7_8_maestros_hombres +
                          $culto->asistencia->clase_9_11_maestros_hombres;
                
                $mujeres = $culto->asistencia->chapel_adultos_mujeres + 
                          $culto->asistencia->chapel_adultos_mayores_mujeres + 
                          $culto->asistencia->chapel_jovenes_femeninas +
                          $culto->asistencia->clase_0_1_mujeres + 
                          $culto->asistencia->clase_2_6_mujeres + 
                          $culto->asistencia->clase_7_8_mujeres + 
                          $culto->asistencia->clase_9_11_mujeres +
                          $culto->asistencia->clase_0_1_maestros_mujeres +
                          $culto->asistencia->clase_2_6_maestros_mujeres +
                          $culto->asistencia->clase_7_8_maestros_mujeres +
                          $culto->asistencia->clase_9_11_maestros_mujeres;
                
                $ninos = $culto->asistencia->clase_0_1_hombres + $culto->asistencia->clase_0_1_mujeres +
                        $culto->asistencia->clase_2_6_hombres + $culto->asistencia->clase_2_6_mujeres +
                        $culto->asistencia->clase_7_8_hombres + $culto->asistencia->clase_7_8_mujeres +
                        $culto->asistencia->clase_9_11_hombres + $culto->asistencia->clase_9_11_mujeres;
                
                $totalGeneral += $culto->asistencia->total_asistencia;
                $totalHombres += $hombres;
                $totalMujeres += $mujeres;
                $totalNinos += $ninos;
            @endphp
            <tr>
                <td>{{ $culto->fecha->locale('es')->isoFormat('dddd D [de] MMMM, YYYY') }}</td>
                <td>{{ ucfirst($culto->tipo_culto) }}</td>
                <td class="total">{{ $culto->asistencia->total_asistencia }}</td>
                <td style="text-align: center;">{{ $hombres }}</td>
                <td style="text-align: center;">{{ $mujeres }}</td>
                <td style="text-align: center;">{{ $ninos }}</td>
            </tr>
            @endif
            @endforeach
            <tr class="total-row">
                <td colspan="2" style="text-align: right;">TOTALES</td>
                <td style="text-align: center;">{{ $totalGeneral }}</td>
                <td style="text-align: center;">{{ $totalHombres }}</td>
                <td style="text-align: center;">{{ $totalMujeres }}</td>
                <td style="text-align: center;">{{ $totalNinos }}</td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Sistema de Administración - IBBSC - Iglesia Bíblica Bautista en Santa Cruz</p>
    </div>
</body>
</html>

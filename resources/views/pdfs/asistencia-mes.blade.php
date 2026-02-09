<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Asistencia - {{ $nombreMes }} {{ $año }}</title>
    <style>
        @page { size: landscape; margin: 15mm; }
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
        .header { display: flex; align-items: center; margin-bottom: 15px; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; }
        .header img { width: 60px; height: 60px; margin-right: 15px; }
        .header-text { flex: 1; }
        .header-text h1 { margin: 0; color: #1f2937; font-size: 18px; }
        .header-text h2 { margin: 5px 0 0 0; color: #3b82f6; font-size: 12px; font-weight: normal; }
        .header-text p { margin: 3px 0 0 0; color: #6b7280; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; font-size: 10px; }
        th { background-color: #3b82f6; color: white; font-weight: bold; }
        tbody tr:nth-child(even) { background-color: #f9fafb; }
        .total-row { font-weight: bold; background-color: #dbeafe; border-top: 2px solid #3b82f6; }
        .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/Logo2.png'))) }}" alt="Logo IBBSC">
        <div class="header-text">
            <h1>IBBSC - Iglesia Bíblica Bautista en Santa Cruz</h1>
            <h2>Reporte de Asistencia - {{ $nombreMes }} {{ $año }}</h2>
            <p><strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Total</th>
                <th>Hombres</th>
                <th>Mujeres</th>
                <th>Niños</th>
                <th>Capilla</th>
                <th>Visitas</th>
                <th>Salvos</th>
                <th>Bautismos</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalMes = 0;
                $totalHombres = 0;
                $totalMujeres = 0;
                $totalNinos = 0;
                $totalCapillaGeneral = 0;
                $totalVisitasGeneral = 0;
                $totalSalvosGeneral = 0;
                $totalBautismosGeneral = 0;
            @endphp
            @foreach($cultos as $culto)
            @if($culto->asistencia)
            @php
                $hombres = ($culto->asistencia->chapel_adultos_hombres ?? 0) + 
                          ($culto->asistencia->chapel_adultos_mayores_hombres ?? 0) + 
                          ($culto->asistencia->chapel_jovenes_masculinos ?? 0) + 
                          ($culto->asistencia->chapel_maestros_hombres ?? 0) +
                          ($culto->asistencia->clase_0_1_hombres ?? 0) + 
                          ($culto->asistencia->clase_2_6_hombres ?? 0) + 
                          ($culto->asistencia->clase_7_8_hombres ?? 0) + 
                          ($culto->asistencia->clase_9_11_hombres ?? 0) +
                          ($culto->asistencia->clase_0_1_maestros_hombres ?? 0) +
                          ($culto->asistencia->clase_2_6_maestros_hombres ?? 0) +
                          ($culto->asistencia->clase_7_8_maestros_hombres ?? 0) +
                          ($culto->asistencia->clase_9_11_maestros_hombres ?? 0);
                
                $mujeres = ($culto->asistencia->chapel_adultos_mujeres ?? 0) + 
                          ($culto->asistencia->chapel_adultos_mayores_mujeres ?? 0) + 
                          ($culto->asistencia->chapel_jovenes_femeninas ?? 0) +
                          ($culto->asistencia->chapel_maestros_mujeres ?? 0) +
                          ($culto->asistencia->clase_0_1_mujeres ?? 0) + 
                          ($culto->asistencia->clase_2_6_mujeres ?? 0) + 
                          ($culto->asistencia->clase_7_8_mujeres ?? 0) + 
                          ($culto->asistencia->clase_9_11_mujeres ?? 0) +
                          ($culto->asistencia->clase_0_1_maestros_mujeres ?? 0) +
                          ($culto->asistencia->clase_2_6_maestros_mujeres ?? 0) +
                          ($culto->asistencia->clase_7_8_maestros_mujeres ?? 0) +
                          ($culto->asistencia->clase_9_11_maestros_mujeres ?? 0);
                
                $ninos = ($culto->asistencia->clase_0_1_hombres ?? 0) + ($culto->asistencia->clase_0_1_mujeres ?? 0) +
                        ($culto->asistencia->clase_2_6_hombres ?? 0) + ($culto->asistencia->clase_2_6_mujeres ?? 0) +
                        ($culto->asistencia->clase_7_8_hombres ?? 0) + ($culto->asistencia->clase_7_8_mujeres ?? 0) +
                        ($culto->asistencia->clase_9_11_hombres ?? 0) + ($culto->asistencia->clase_9_11_mujeres ?? 0);
                
                $totalCapilla = ($culto->asistencia->chapel_adultos_hombres ?? 0) + 
                               ($culto->asistencia->chapel_adultos_mujeres ?? 0) +
                               ($culto->asistencia->chapel_adultos_mayores_hombres ?? 0) + 
                               ($culto->asistencia->chapel_adultos_mayores_mujeres ?? 0) +
                               ($culto->asistencia->chapel_jovenes_masculinos ?? 0) + 
                               ($culto->asistencia->chapel_jovenes_femeninas ?? 0) +
                               ($culto->asistencia->chapel_maestros_hombres ?? 0) +
                               ($culto->asistencia->chapel_maestros_mujeres ?? 0);
                
                $totalVisitas = ($culto->asistencia->visitas_adulto_hombre ?? 0) + 
                               ($culto->asistencia->visitas_adulto_mujer ?? 0) +
                               ($culto->asistencia->visitas_joven_hombre ?? 0) + 
                               ($culto->asistencia->visitas_joven_mujer ?? 0) +
                               ($culto->asistencia->visitas_nino ?? 0) + 
                               ($culto->asistencia->visitas_nina ?? 0);
                
                $totalSalvos = ($culto->asistencia->salvos_adulto_hombre ?? 0) + 
                              ($culto->asistencia->salvos_adulto_mujer ?? 0) +
                              ($culto->asistencia->salvos_joven_hombre ?? 0) + 
                              ($culto->asistencia->salvos_joven_mujer ?? 0) +
                              ($culto->asistencia->salvos_nino ?? 0) + 
                              ($culto->asistencia->salvos_nina ?? 0);
                
                $totalBautismos = ($culto->asistencia->bautismos_adulto_hombre ?? 0) + 
                                 ($culto->asistencia->bautismos_adulto_mujer ?? 0) +
                                 ($culto->asistencia->bautismos_joven_hombre ?? 0) + 
                                 ($culto->asistencia->bautismos_joven_mujer ?? 0) +
                                 ($culto->asistencia->bautismos_nino ?? 0) + 
                                 ($culto->asistencia->bautismos_nina ?? 0);
                
                $totalMes += $culto->asistencia->total_asistencia;
                $totalHombres += $hombres;
                $totalMujeres += $mujeres;
                $totalNinos += $ninos;
                $totalCapillaGeneral += $totalCapilla;
                $totalVisitasGeneral += $totalVisitas;
                $totalSalvosGeneral += $totalSalvos;
                $totalBautismosGeneral += $totalBautismos;
            @endphp
            <tr>
                <td>{{ $culto->fecha->locale('es')->isoFormat('dddd D/M/Y') }}</td>
                <td>{{ ucfirst($culto->tipo_culto) }}</td>
                <td style="font-weight: bold; text-align: center;">{{ $culto->asistencia->total_asistencia }}</td>
                <td style="text-align: center;">{{ $hombres }}</td>
                <td style="text-align: center;">{{ $mujeres }}</td>
                <td style="text-align: center;">{{ $ninos }}</td>
                <td style="text-align: center;">{{ $totalCapilla }}</td>
                <td style="text-align: center;">{{ $totalVisitas }}</td>
                <td style="text-align: center;">{{ $totalSalvos }}</td>
                <td style="text-align: center;">{{ $totalBautismos }}</td>
            </tr>
            @endif
            @endforeach
            <tr class="total-row">
                <td colspan="2">TOTAL DEL MES</td>
                <td style="text-align: center;">{{ $totalMes }}</td>
                <td style="text-align: center;">{{ $totalHombres }}</td>
                <td style="text-align: center;">{{ $totalMujeres }}</td>
                <td style="text-align: center;">{{ $totalNinos }}</td>
                <td style="text-align: center;">{{ $totalCapillaGeneral }}</td>
                <td style="text-align: center;">{{ $totalVisitasGeneral }}</td>
                <td style="text-align: center;">{{ $totalSalvosGeneral }}</td>
                <td style="text-align: center;">{{ $totalBautismosGeneral }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="2">PROMEDIO DEL MES</td>
                <td style="text-align: center;">{{ $cultos->count() > 0 ? round($totalMes / $cultos->count(), 2) : 0 }}</td>
                <td style="text-align: center;">{{ $cultos->count() > 0 ? round($totalHombres / $cultos->count(), 2) : 0 }}</td>
                <td style="text-align: center;">{{ $cultos->count() > 0 ? round($totalMujeres / $cultos->count(), 2) : 0 }}</td>
                <td style="text-align: center;">{{ $cultos->count() > 0 ? round($totalNinos / $cultos->count(), 2) : 0 }}</td>
                <td style="text-align: center;">{{ $cultos->count() > 0 ? round($totalCapillaGeneral / $cultos->count(), 2) : 0 }}</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">-</td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Sistema de Administración - IBBSC - Iglesia Bíblica Bautista en Santa Cruz</p>
    </div>
</body>
</html>

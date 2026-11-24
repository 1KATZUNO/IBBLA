<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Recuento - {{ $culto->fecha->format('d/m/Y') }}</title>
    @php
        // DomPDF no entiende flexbox: la cabecera y las tarjetas van en tablas. La tabla de sobres
        // lleva anchos fijos (en % de la hoja) para que la columna Total nunca se salga del borde
        // (QE-001). Las notas del sobre van debajo del nombre, no en una columna aparte.
        $transferenciasOnly = isset($transferenciasOnly) && $transferenciasOnly === true;
        $nCat = max(count($categories), 1);
        $wNum = $transferenciasOnly ? 0 : 4;
        $wPersona = $transferenciasOnly ? 23 : 20;
        $wMetodo = 7;
        $wComprobante = 9;
        $wTotal = 11;
        $wCat = round((100 - $wNum - $wPersona - $wMetodo - $wComprobante - $wTotal) / $nCat, 2);
        $fuente = $nCat > 6 ? '7.5px' : ($nCat > 4 ? '8px' : '9px');
        $fuenteEncabezado = $nCat > 6 ? '6.5px' : '7.5px';
    @endphp
    <style>
        @page { size: A4 landscape; margin: 12mm 12mm 14mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111827; margin: 0; padding: 0; }
        table { border-collapse: collapse; width: 100%; }
        .cabecera { border-bottom: 3px solid #1e40af; margin-bottom: 8px; }
        .cabecera td { vertical-align: middle; padding: 0 0 6px 0; }
        .cabecera h1 { margin: 0; font-size: 16px; color: #1f2937; }
        .cabecera h2 { margin: 3px 0 0 0; font-size: 11px; font-weight: normal; color: #1e40af; }
        .cabecera .meta { text-align: right; font-size: 8px; color: #6b7280; }
        .tarjetas { table-layout: fixed; margin-bottom: 6px; }
        .tarjetas td { border: 1px solid #e5e7eb; padding: 5px 4px; text-align: center; }
        .tarjetas .etq { font-size: 7.5px; color: #6b7280; text-transform: uppercase; }
        .tarjetas .val { font-size: 12px; font-weight: bold; color: #1f2937; margin-top: 2px; }
        .tarjetas .usd { font-size: 7px; color: #6b7280; }
        h3 { margin: 8px 0 4px 0; font-size: 10px; color: #1f2937; }
        .sobres { table-layout: fixed; font-size: {{ $fuente }}; }
        .sobres th, .sobres td { border: 1px solid #d1d5db; padding: 2px 3px; overflow: hidden; word-wrap: break-word; vertical-align: top; }
        .sobres th { background-color: #1e40af; color: #ffffff; font-size: {{ $fuenteEncabezado }}; text-align: left; }
        .sobres th.num, .sobres td.num { text-align: right; }
        .sobres td.centro { text-align: center; }
        .sobres tr { page-break-inside: avoid; }
        .sobres .nota { font-size: 6.5px; color: #6b7280; }
        .sobres .usd { font-size: 6.5px; color: #92400e; }
        .suelto { background-color: #dcfce7; }
        .egreso { background-color: #fee2e2; }
        .totales td { font-weight: bold; background-color: #dbeafe; }
        .subtotal { font-weight: bold; color: #1e40af; }
        .transferencia { color: #1e40af; }
        .resumen { margin-top: 8px; padding: 6px 8px; background-color: #fef3c7; border-radius: 4px; }
        .resumen b { color: #92400e; }
        .resumen span { display: inline-block; margin: 2px 12px 2px 0; }
        .nota-usd { background-color: #fef3c7; padding: 6px; border-left: 4px solid #f59e0b; margin-top: 8px; font-size: 7.5px; }
        .firmas { margin-top: 18px; page-break-inside: avoid; }
        .firmas h3 { text-align: center; color: #374151; margin-bottom: 10px; }
        .firmas td { text-align: center; vertical-align: bottom; padding: 6px 10px; }
        .firmas .linea { border-top: 1px solid #000; padding-top: 5px; margin-top: 4px; }
        .firmas .nombre { font-size: 9px; font-weight: bold; }
        .firmas .cargo { font-size: 7.5px; color: #6b7280; }
        .pie { margin-top: 12px; text-align: center; font-size: 7.5px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>
    @php
        extract(tenant_pdf_data());
        // Todos los numeros salen de App\Support\ResumenCulto (los mismos de la pantalla del culto):
        // colones convertidos, USD aparte entre parentesis y un solo total (QA-026).
        $usdTotal = $resumen->totalUsdSobres();
        $crc = fn (float $v) => number_format($v, 2);
        $enColones = fn ($item) => $item->moneda === 'USD'
            ? (float) $item->monto * ((float) $item->tipo_cambio_venta ?: $resumen->tipoCambioRespaldo ?: 1)
            : (float) $item->monto;
    @endphp

    <table class="cabecera">
        <tr>
            <td style="width: 60px;">
                <div style="background-color: {{ $tenantColor }}; border-radius: 50%; width: 52px; height: 52px; text-align: center;">
                    <img src="data:image/png;base64,{{ $tenantLogoBase64 }}" style="width: 38px; height: 38px; margin-top: 7px;" alt="">
                </div>
            </td>
            <td>
                <h1>{{ $tenantSiglas }} - {{ $tenantNombre }}</h1>
                <h2>Recuento de sobres{{ $transferenciasOnly ? ' (solo transferencias)' : '' }} - {{ $culto->fecha->format('d/m/Y') }} - {{ ucfirst($culto->tipo_culto) }}</h2>
            </td>
            <td class="meta" style="width: 190px;">
                Generado: {{ now()->format('d/m/Y H:i') }}<br>
                @if($culto->cerrado)
                    Cerrado el {{ $culto->cerrado_at?->format('d/m/Y H:i') }}{{ $culto->cerradoPor ? ' por '.$culto->cerradoPor->name : '' }}
                @else
                    Culto abierto (recuento en curso)
                @endif
            </td>
        </tr>
    </table>

    @if(!$transferenciasOnly)
    <table class="tarjetas">
        <tr>
            <td>
                <div class="etq">Total general (₡)</div>
                <div class="val">{{ $crc($resumen->total()) }}</div>
                @if($usdTotal > 0)<div class="usd">incluye USD ${{ number_format($usdTotal, 2) }}</div>@endif
            </td>
            <td><div class="etq">Sobres</div><div class="val">{{ $resumen->cantidadSobres }}</div></td>
            <td><div class="etq">Efectivo (sobres + suelto − egresos)</div><div class="val">{{ $crc($resumen->efectivo()) }}</div></td>
            <td><div class="etq">Transferencias ({{ $resumen->cantidadTransferencias }})</div><div class="val">{{ $crc($resumen->transferencias()) }}</div></td>
            @foreach($categories->take(2) as $cat)
            <td><div class="etq">{{ $cat->nombre }}</div><div class="val">{{ $crc($resumen->porCategoria[$cat->slug] ?? 0) }}</div></td>
            @endforeach
        </tr>
    </table>
    @else
    <table class="tarjetas">
        <tr>
            <td>
                <div class="etq">Total transferencias (₡)</div>
                <div class="val">{{ $crc($resumen->transferencias()) }}</div>
                @if($usdTotal > 0)<div class="usd">incluye USD ${{ number_format($usdTotal, 2) }}</div>@endif
            </td>
            <td><div class="etq">Transferencias</div><div class="val">{{ $resumen->cantidadTransferencias }}</div></td>
        </tr>
    </table>
    @endif

    <h3>Detalle de sobres{{ $transferenciasOnly ? ' (solo transferencias)' : ' y dinero suelto' }} (₡)</h3>
    <table class="sobres">
        <thead>
            <tr>
                @if(!$transferenciasOnly)<th class="centro" style="width: {{ $wNum }}%;">N°</th>@endif
                <th style="width: {{ $wPersona }}%;">Persona</th>
                <th style="width: {{ $wMetodo }}%;">Método</th>
                <th style="width: {{ $wComprobante }}%;">Comprobante</th>
                @foreach($categories as $cat)
                <th class="num" style="width: {{ $wCat }}%;">{{ $cat->nombre }}</th>
                @endforeach
                <th class="num" style="width: {{ $wTotal }}%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resumen->filas as $fila)
            @php $sobre = $fila['sobre']; $esUsd = $fila['subtotal_usd'] > 0; @endphp
            <tr>
                @if(!$transferenciasOnly)<td class="centro"><b>#{{ $sobre->numero_sobre }}</b></td>@endif
                <td>
                    {{ $sobre->persona ? $sobre->persona->nombre : 'Anónimo' }}
                    @if($sobre->notas)<br><span class="nota">{{ $sobre->notas }}</span>@endif
                </td>
                <td class="{{ $sobre->metodo_pago === 'transferencia' ? 'transferencia' : '' }}">{{ $sobre->metodo_pago === 'transferencia' ? 'Transf.' : ucfirst($sobre->metodo_pago) }}</td>
                <td>{{ $sobre->metodo_pago === 'transferencia' ? ($sobre->comprobante_numero ?? '-') : '-' }}</td>
                @foreach($categories as $cat)
                <td class="num">
                    {{ $crc($fila['crc'][$cat->slug] ?? 0) }}
                    @if($esUsd && ($fila['usd'][$cat->slug] ?? 0) > 0)<br><span class="usd">(${{ number_format($fila['usd'][$cat->slug], 2) }})</span>@endif
                </td>
                @endforeach
                <td class="num subtotal">
                    {{ $crc($fila['subtotal_crc']) }}
                    @if($esUsd)<br><span class="usd">(USD ${{ number_format($fila['subtotal_usd'], 2) }} × {{ number_format($fila['tipo_cambio'], 2) }})</span>@endif
                </td>
            </tr>
            @endforeach

            @if(!$transferenciasOnly)
                @foreach($culto->ofrendasSueltas as $ofrenda)
                <tr class="suelto">
                    <td class="centro">-</td>
                    <td><b>Dinero suelto</b>@if($ofrenda->descripcion)<br><span class="nota">{{ $ofrenda->descripcion }}</span>@endif</td>
                    <td colspan="{{ 2 + $nCat }}">Efectivo</td>
                    <td class="num subtotal">
                        {{ $crc($enColones($ofrenda)) }}
                        @if($ofrenda->moneda === 'USD')<br><span class="usd">(USD ${{ number_format((float) $ofrenda->monto, 2) }})</span>@endif
                    </td>
                </tr>
                @endforeach
                @foreach($culto->egresos as $egreso)
                <tr class="egreso">
                    <td class="centro">-</td>
                    <td><b>Egreso</b>@if($egreso->descripcion)<br><span class="nota">{{ $egreso->descripcion }}</span>@endif</td>
                    <td colspan="{{ 2 + $nCat }}">Efectivo</td>
                    <td class="num subtotal">
                        -{{ $crc($enColones($egreso)) }}
                        @if($egreso->moneda === 'USD')<br><span class="usd">(USD ${{ number_format((float) $egreso->monto, 2) }})</span>@endif
                    </td>
                </tr>
                @endforeach
            @endif

            {{-- Totales: el mismo Total General de la cabecera --}}
            <tr class="totales">
                <td colspan="{{ $transferenciasOnly ? 3 : 4 }}" class="num">TOTALES (₡)</td>
                @foreach($categories as $cat)
                <td class="num">{{ $crc($resumen->porCategoria[$cat->slug] ?? 0) }}</td>
                @endforeach
                <td class="num">{{ $crc($transferenciasOnly ? $resumen->transferencias() : $resumen->total()) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="resumen">
        <b>Resumen por categorías (₡):</b>
        @foreach($categories as $cat)
        <span><b>{{ $cat->nombre }}:</b> {{ $crc($resumen->porCategoria[$cat->slug] ?? 0) }}</span>
        @endforeach
    </div>

    @if($resumen->tieneUsd)
    <div class="nota-usd">
        <b>Nota:</b> todos los montos están en colones. Los sobres en dólares se convirtieron con el tipo de cambio guardado en cada sobre (se indica en la fila);
        si un sobre no lo tenía, con el de venta de la fecha del culto (₡{{ number_format($resumen->tipoCambioRespaldo, 2) }}). El monto en USD va entre paréntesis, solo como referencia.
    </div>
    @endif

    @php
        $pastor = $culto->firma_pastor ?? '';
        $pastorImagen = $culto->firma_pastor_imagen ?? '';
        $tesorerosImagenes = $culto->firmas_tesoreros_imagenes ?? [];
        // Respaldo con los nombres antiguos si no hay imagenes
        if (empty($tesorerosImagenes)) {
            $tesorerosImagenes = array_map(fn ($n) => ['nombre' => $n, 'imagen' => ''], $culto->firmas_tesoreros ?? []);
        }
        $anchoFirma = round(100 / (count($tesorerosImagenes) + 1), 2);
    @endphp
    <div class="firmas">
        <h3>FIRMAS DE AUTORIZACIÓN</h3>
        <table>
            <tr>
                @foreach($tesorerosImagenes as $t)
                <td style="width: {{ $anchoFirma }}%;">
                    @if(!empty($t['imagen']))<img src="{{ $t['imagen'] }}" style="max-height: 45px; max-width: 120px;" alt="">@endif
                    <div class="linea">
                        <div class="nombre">{{ $t['nombre'] ?? 'Tesorero' }}</div>
                        <div class="cargo">Tesorero</div>
                    </div>
                </td>
                @endforeach
                <td style="width: {{ $anchoFirma }}%;">
                    @if(!empty($pastorImagen))<img src="{{ $pastorImagen }}" style="max-height: 45px; max-width: 120px;" alt="">@endif
                    <div class="linea">
                        <div class="nombre">{{ $pastor ?: 'Pastor' }}</div>
                        <div class="cargo">Pastor</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="pie">Sistema de Administración - {{ $tenantSiglas }} - {{ $tenantNombre }}</div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Manual de usuario - {{ $tenantSiglas }}</title>
    <style>
        /* DomPDF: sin flex ni grid. DejaVu Sans trae el simbolo ₡ (colon). */
        @page { margin: 16mm 15mm 18mm 15mm; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 10px; line-height: 1.55; color: #374151; }

        h1, h2, h3, h4, p, ul, ol { margin: 0; padding: 0; }
        p { margin: 0 0 7px 0; }

        /* Portada */
        .portada { text-align: center; padding-top: 60mm; }
        .portada .logo { width: 86px; height: 86px; border-radius: 43px; background: {{ $tenantColor }}; margin: 0 auto 18px auto; text-align: center; }
        .portada .logo img { width: 60px; height: 60px; margin-top: 13px; }
        .portada h1 { font-size: 30px; color: #111827; margin-bottom: 6px; }
        .portada .iglesia { font-size: 14px; color: {{ $tenantColor }}; margin-bottom: 4px; }
        .portada .fecha { font-size: 10px; color: #6b7280; margin-top: 18px; }

        .indice { margin-top: 26px; text-align: left; }
        .indice h2 { font-size: 14px; color: #111827; border-bottom: 2px solid {{ $tenantColor }}; padding-bottom: 5px; margin-bottom: 8px; }
        .indice table { width: 100%; border-collapse: collapse; }
        .indice td { padding: 5px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .indice td.n { width: 22px; color: {{ $tenantColor }}; font-weight: bold; }
        .indice td.t { width: 150px; font-weight: bold; color: #111827; }

        /* Cada modulo */
        .modulo { page-break-before: always; }
        .modulo.primero { page-break-before: auto; }
        .modulo h1 { font-size: 21px; color: #111827; border-bottom: 3px solid {{ $tenantColor }}; padding-bottom: 6px; margin-bottom: 8px; }
        .modulo .resumen { font-size: 11.5px; color: #374151; margin-bottom: 4px; }
        .modulo .roles { font-size: 9px; color: #6b7280; margin-bottom: 14px; }

        .contenido h2 { font-size: 14.5px; color: #111827; margin: 18px 0 7px 0; padding-top: 8px; border-top: 1px solid #d1d5db; }
        .contenido h3 { font-size: 12px; color: #111827; margin: 13px 0 5px 0; }
        .contenido h4 { font-size: 10.5px; color: #111827; margin: 10px 0 4px 0; }
        .contenido ul, .contenido ol { margin: 0 0 8px 0; padding-left: 18px; }
        .contenido li { margin-bottom: 3px; }
        .contenido strong { color: #111827; }
        .contenido a { color: {{ $tenantColor }}; text-decoration: none; }
        .contenido code { background: #f3f4f6; padding: 0 3px; font-size: 9px; }
        .contenido hr { border: 0; border-top: 1px solid #e5e7eb; margin: 12px 0; }

        .contenido figure { display: block; margin: 10px 0 12px 0; text-align: center; page-break-inside: avoid; }
        .contenido figure img { width: 100%; border: 1px solid #d1d5db; }
        .contenido figcaption { font-size: 8.5px; color: #6b7280; margin-top: 3px; }
        .contenido p img { max-width: 100%; }

        .manual-table-wrap { margin: 6px 0 12px 0; }
        .contenido table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        .contenido tr { page-break-inside: avoid; }
        .contenido th { background: #f3f4f6; color: #374151; font-size: 8px; text-align: left; text-transform: uppercase; padding: 5px 6px; border: 1px solid #d1d5db; }
        .contenido td { padding: 5px 6px; border: 1px solid #e5e7eb; vertical-align: top; font-size: 9px; }
        .contenido td:first-child { font-weight: bold; color: #111827; width: 20%; }

        .contenido blockquote { margin: 8px 0 10px 0; padding: 7px 10px; border-left: 4px solid {{ $tenantColor }}; background: #f3f4f6; color: #1f2937; }
        .contenido blockquote p { margin: 0 0 4px 0; }
        .contenido blockquote.manual-cuidado { border-left-color: #d97706; background: #fffbeb; color: #78350f; }
        .contenido blockquote.manual-consejo { border-left-color: #16a34a; background: #f0fdf4; color: #14532d; }
        .contenido .m-n { background: #dc2626; color: #ffffff; font-weight: bold; padding: 0 4px; font-size: 8px; }
    </style>
</head>
<body>
    @if($completo)
        <div class="portada">
            <div class="logo"><img src="data:image/png;base64,{{ $tenantLogoBase64 }}" alt="Logo"></div>
            <h1>Manual de usuario</h1>
            <p class="iglesia">{{ $tenantNombre }}</p>
            <p class="fecha">Versión del {{ $generado->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</p>

            <div class="indice">
                <h2>Contenido</h2>
                <table>
                    @foreach($indice as $k => $p)
                        <tr>
                            <td class="n">{{ $k + 1 }}</td>
                            <td class="t">{{ $p->titulo }}</td>
                            <td>{{ $p->resumen }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    @endif

    @foreach($paginasHtml as $i => $item)
        <div class="modulo {{ (! $completo && $i === 0) ? 'primero' : '' }}">
            <h1>{{ $item['pagina']->titulo }}</h1>
            @if($item['pagina']->resumen)
                <p class="resumen">{{ $item['pagina']->resumen }}</p>
            @endif
            <p class="roles">Lo usan: {{ \App\Support\Manual\Manual::etiquetaRoles($item['pagina']) }}</p>
            <div class="contenido">
                {!! $item['html'] !!}
            </div>
        </div>
    @endforeach

</body>
</html>

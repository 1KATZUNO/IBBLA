<?php

namespace App\Services;

use Carbon\Carbon;
use Dompdf\Adapter\CPDF as CanvasCpdf;
use Dompdf\Cpdf;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Log;

/**
 * PDF del "Reporte General" (dado vs esperado) dibujado directamente sobre el
 * canvas de DomPDF (CPDF), sin pasar por su motor de maquetacion HTML.
 *
 * Por que: con la vista Blade, DomPDF tenia que maquetar una tabla de 26 filas
 * por persona (~36.000 celdas para 140 personas y el anio completo). Su costo
 * crece mas que lineal con el numero de celdas: ~240 s y ~650 MB, y nginx de
 * Hostinger cortaba con "504 Gateway Time-out". Aun quitando todo el CSS,
 * DomPDF tardaba 33 s con solo 40 personas, asi que el arreglo no podia ser
 * "simplificar la vista". Dibujando las mismas celdas con primitivas del mismo
 * motor de salida (misma fuente DejaVu Sans, mismo CPDF) el PDF sale en
 * alrededor de un segundo.
 *
 * El diseno replica el que producia la antigua vista pdfs/reporte-general
 * (medidas en puntos tomadas del PDF que generaba DomPDF; 1px de la vista =
 * 0.75pt): encabezado con logo, periodo, un bloque por persona que no se parte
 * entre paginas, filas Dado (verde) / Esperado (azul) por mes, fila de totales
 * de la persona y el bloque de totales generales al final. Los numeros se
 * formatean igual que en la vista (number_format(x, 0) y signo + en la
 * diferencia), asi que el contenido es identico.
 *
 * Costo: lineal en personas x meses (~1,5 s y ~65 MB para 140 personas y 12
 * meses en local). Si con mas volumen o una CPU lenta no alcanzara, el plan B
 * es generarlo en cola (Job que guarda el PDF en storage y la pantalla ofrece
 * la descarga al terminar; en Hostinger requiere cron de schedule:run o
 * queue:work --stop-when-empty) y cachear el PDF por tenant + periodo + ultima
 * modificacion de sobres/promesas/personas.
 */
class ReporteGeneralPdf
{
    // Pagina carta horizontal (pt). DomPDF no aplicaba el margen de @page de la
    // vista (lo anulaba la regla "* { margin: 0 }"): solo el padding de 5px del
    // contenedor a los lados y arriba de la primera pagina.
    private const ALTO_PAGINA = 612.0;

    private const X0 = 3.75;

    private const X1 = 788.25;

    private const CENTRO_X = 396.0;

    // Tablas
    private const BORDE = 0.75;

    private const PASO_FILA = 14.8165;      // alto de cada fila Dado/Esperado incluido su borde

    private const ALTO_CELDA = 14.0665;     // relleno de una celda de esas filas

    private const ALTO_TH = 17.07;

    private const ALTO_BARRA_NOMBRE = 24.68;

    private const ALTO_BARRA_TOTALES = 28.83;

    private const PASO_LINEA = 8.07;        // interlineado del texto de 7px

    private const MARGEN_ENTRE_BLOQUES = 15.0;   // margin-bottom: 20px de .persona-block

    private const MARGEN_ANTES_TOTALES = 18.75;  // margin-top: 25px de .totales-generales

    private const MARGEN_ANTES_PIE = 11.25;      // margin-top: 15px de .footer

    private const INICIO_BLOQUES_PAGINA_1 = 131.89;

    // Ancho relativo de las columnas (th.col-mes 8%, col-tipo 5%, col-cat 11%,
    // col-total 10%, col-dif 10%); como DomPDF, se escalan para llenar la tabla.
    private const PCT_MES = 8;

    private const PCT_TIPO = 5;

    private const PCT_CATEGORIA = 11;

    private const PCT_TOTAL = 10;

    private const PCT_DIFERENCIA = 10;

    // Tamanos de letra en pt (px de la vista * 0.75)
    private const T_CELDA = 5.25;

    private const T_TIPO = 4.5;

    private const T_NOMBRE = 8.25;

    private const T_TITULO = 12.0;

    private const T_SUBTITULO = 7.5;

    private const T_TOTALES_HEADER = 9.0;

    private const T_GRAN_TOTAL = 6.0;

    private Dompdf $dompdf;

    private CanvasCpdf $canvas;

    private Cpdf $pdf;

    private string $regular;

    private string $negrita;

    private string $italica;

    private bool $subconjunto;

    /** @var array<string, float> ancho de cada texto ya medido (fuente|tamano|texto) */
    private array $anchos = [];

    /** @var array<string, array{0: float, 1: float, 2: float}> colores ya convertidos de #rrggbb */
    private array $rgb = [];

    /**
     * @param  array{reporteData: array, totalesGenerales: array, categoriasConPromesa: array}  $reporte  salida de ReporteGeneralService::generar()
     * @param  array{tenantNombre: string, tenantSiglas: string, tenantColor: string, tenantLogoBase64: string}  $tenant  salida de tenant_pdf_data()
     */
    public function render(array $reporte, int $anio, int $mesHasta, array $tenant, Carbon $generadoEn): string
    {
        $this->iniciar('Reporte General - '.$tenant['tenantSiglas']);

        $categorias = $reporte['categoriasConPromesa'];
        $this->encabezado($tenant, $anio, $mesHasta);
        $y = self::INICIO_BLOQUES_PAGINA_1;

        if (count($reporte['reporteData']) > 0) {
            $anchoNombreTotal = $this->anchoUtilTotalPersona($categorias);
            $primero = true;
            foreach ($reporte['reporteData'] as $persona) {
                $lineasNombre = $this->partirEnLineas('TOTAL '.strtoupper($persona['nombre']), $this->negrita, self::T_CELDA, $anchoNombreTotal);
                $alto = $this->altoBloquePersona($mesHasta, count($lineasNombre));
                $y = $this->ubicarBloque($y, $primero ? 0.0 : self::MARGEN_ENTRE_BLOQUES, $alto);
                $this->bloquePersona($persona, $categorias, $lineasNombre, $y);
                $y += $alto;
                $primero = false;
            }

            $alto = $this->altoBloqueTotales($mesHasta);
            $y = $this->ubicarBloque($y, self::MARGEN_ANTES_TOTALES, $alto);
            $this->bloqueTotales($reporte['totalesGenerales'], $categorias, $mesHasta, $y);
            $y += $alto;
        } else {
            $y = $this->sinDatos($y);
        }

        $this->pie($y, $tenant, $generadoEn);

        return $this->canvas->output();
    }

    // ------------------------------------------------------------------
    // Bloques
    // ------------------------------------------------------------------

    private function encabezado(array $tenant, int $anio, int $mesHasta): void
    {
        // Logo: circulo del color del tenant con la imagen centrada. El color puede ser
        // personalizado por el tenant; si no es un hex valido se usa el azul por defecto.
        $this->color($this->hexValido($tenant['tenantColor'] ?? null) ?? '#3b82f6');
        $this->pdf->filledEllipse(23.625, self::ALTO_PAGINA - 22.5, 19.875, 18.75);
        $this->logo($tenant['tenantLogoBase64'], 9.75, 7.5, 30.0, 30.0);

        $this->texto('Reporte General de Compromisos', self::X0, 56.0, $this->negrita, self::T_TITULO, '#1e40af');
        $this->texto($tenant['tenantSiglas'].' - '.$tenant['tenantNombre'], self::X0, 71.16, $this->regular, self::T_SUBTITULO, '#666666');
        $this->rect(self::X0, 80.965, self::X1 - self::X0, 2.25, '#1e40af');

        // Recuadro del periodo (border 1px #bfdbfe, radius 6px)
        $this->rectRedondeado(self::X0, 94.46, self::X1 - self::X0, 26.18, 4.5, 4.5, 4.5, 4.5, '#bfdbfe');
        $this->rectRedondeado(self::X0 + self::BORDE, 94.46 + self::BORDE, self::X1 - self::X0 - 2 * self::BORDE, 26.18 - 2 * self::BORDE, 3.75, 3.75, 3.75, 3.75, '#eff6ff');
        $periodo = 'Periodo: Enero - '.ReporteGeneralService::MESES[$mesHasta].' '.$anio;
        $this->textoCentrado($periodo, self::CENTRO_X, 111.35, $this->negrita, self::T_NOMBRE, '#1e40af');
    }

    private function bloquePersona(array $persona, array $categorias, array $lineasNombre, float $y): void
    {
        $this->rectRedondeado(self::X0, $y, self::X1 - self::X0, self::ALTO_BARRA_NOMBRE, 4.5, 4.5, 0, 0, '#1e3a8a');
        $this->texto($persona['nombre'], self::X0 + 9.0, $y + 16.14, $this->negrita, self::T_NOMBRE, '#ffffff');

        $bordeExterior = self::BORDE;
        $bordes = $this->bordesColumnas(count($categorias), self::X0 + $bordeExterior / 2, self::X1 - $bordeExterior / 2);
        $tablaTop = $y + self::ALTO_BARRA_NOMBRE;
        $cuerpoTop = $tablaTop + $bordeExterior + self::ALTO_TH + self::BORDE;
        $filas = 2 * count($persona['meses']);
        $finCuerpo = $cuerpoTop + ($filas - 1) * self::PASO_FILA + self::ALTO_CELDA;

        // Encabezado de la tabla (los huecos entre rellenos quedan como bordes).
        $this->rect(self::X0, $tablaTop, self::X1 - self::X0, $cuerpoTop - $tablaTop, '#2563eb');
        $n = count($bordes) - 1;
        $this->rect($bordes[$n - 1] - self::BORDE / 2, $tablaTop, $bordes[$n] - $bordes[$n - 1] + self::BORDE, $cuerpoTop - $tablaTop, '#6d28d9');
        $this->rect($bordes[$n - 2] - self::BORDE / 2, $tablaTop, $bordes[$n - 1] - $bordes[$n - 2] + self::BORDE, $cuerpoTop - $tablaTop, '#15803d');
        $this->filaEncabezado($bordes, $categorias, $tablaTop + $bordeExterior, $bordeExterior, [
            'fondo' => '#3b82f6', 'fondo_total' => '#166534', 'fondo_dif' => '#7c3aed',
        ]);

        // Cuerpo: fondo gris (bordes) y celdas por mes.
        $this->rect(self::X0, $cuerpoTop, self::X1 - self::X0, $finCuerpo - $cuerpoTop, '#e5e7eb');
        foreach ($persona['meses'] as $i => $mesDatos) {
            $this->filasMes($bordes, $categorias, $bordeExterior, $cuerpoTop + 2 * $i * self::PASO_FILA, $mesDatos['nombre'], $mesDatos);
        }
        $this->lineasVerticales($bordes, $cuerpoTop, $finCuerpo);

        // Fila de totales de la persona (border-top 2px #f59e0b, padding 6px 4px).
        $this->rect(self::X0, $finCuerpo, self::X1 - self::X0, 1.5, '#f59e0b');
        $filaTop = $finCuerpo + 1.5;
        $altoFila = 8.99 + count($lineasNombre) * self::PASO_LINEA;
        $this->rect(self::X0, $filaTop, self::X1 - self::X0, $altoFila + self::BORDE, '#e5e7eb');

        $base = $filaTop + 10.95 + (count($lineasNombre) - 1) * self::PASO_LINEA / 2;
        $this->celda($bordes, 0, 2, $filaTop, $altoFila, $bordeExterior, '#fef3c7');
        foreach ($lineasNombre as $k => $linea) {
            $this->texto($linea, $bordes[0] + self::BORDE / 2 + 3.0, $filaTop + 10.95 + $k * self::PASO_LINEA, $this->negrita, self::T_CELDA, '#92400e');
        }
        foreach ($categorias as $c => $cat) {
            $totalCatDado = 0;
            foreach ($persona['meses'] as $m) {
                $totalCatDado += $m['categorias'][$cat]['dado'];
            }
            $this->celda($bordes, 2 + $c, 1, $filaTop, $altoFila, $bordeExterior, '#fef3c7');
            $this->textoDerecha(number_format($totalCatDado, 0), $bordes[3 + $c] - self::BORDE / 2 - 3.0, $base, $this->negrita, self::T_CELDA, '#92400e');
        }
        $this->celda($bordes, $n - 2, 1, $filaTop, $altoFila, $bordeExterior, '#dcfce7');
        $this->textoDerecha(number_format($persona['totales']['dado'], 0), $bordes[$n - 1] - self::BORDE / 2 - 3.0, $base, $this->negrita, self::T_CELDA, '#166534');
        $dif = $persona['totales']['diferencia'];
        $this->celda($bordes, $n - 1, 1, $filaTop, $altoFila, $bordeExterior, $dif >= 0 ? '#dcfce7' : '#fef2f2');
        $this->textoDerecha($this->conSigno($dif), $bordes[$n] - $bordeExterior / 2 - 3.0, $base, $this->negrita, self::T_CELDA, $dif >= 0 ? '#166534' : '#dc2626');
    }

    private function bloqueTotales(array $totales, array $categorias, int $mesHasta, float $y): void
    {
        $this->rectRedondeado(self::X0, $y, self::X1 - self::X0, self::ALTO_BARRA_TOTALES, 4.5, 4.5, 0, 0, '#7c3aed');
        $this->textoCentrado('TOTALES GENERALES - TODAS LAS PERSONAS', self::CENTRO_X, $y + 18.56, $this->negrita, self::T_TOTALES_HEADER, '#ffffff');

        $bordeExterior = 1.5;   // table { border: 2px solid #7c3aed }
        $bordes = $this->bordesColumnas(count($categorias), self::X0 + $bordeExterior / 2, self::X1 - $bordeExterior / 2);
        $n = count($bordes) - 1;
        $tablaTop = $y + self::ALTO_BARRA_TOTALES;
        $cuerpoTop = $tablaTop + $bordeExterior + self::ALTO_TH + self::BORDE;
        $finCuerpo = $cuerpoTop + (2 * $mesHasta - 1) * self::PASO_FILA + self::ALTO_CELDA;
        $granTop = $finCuerpo + self::BORDE;
        $altoGran = 21.22;
        $finTabla = $granTop + $altoGran + $bordeExterior;

        $this->rect(self::X0, $tablaTop, self::X1 - self::X0, $finTabla - $tablaTop, '#7c3aed');
        $this->filaEncabezado($bordes, $categorias, $tablaTop + $bordeExterior, $bordeExterior, [
            'fondo' => '#8b5cf6', 'fondo_total' => '#8b5cf6', 'fondo_dif' => '#8b5cf6',
        ]);
        $this->rect(self::X0 + $bordeExterior, $cuerpoTop, self::X1 - self::X0 - 2 * $bordeExterior, $granTop + $altoGran - $cuerpoTop, '#e5e7eb');

        for ($mes = 1; $mes <= $mesHasta; $mes++) {
            $this->filasMes($bordes, $categorias, $bordeExterior, $cuerpoTop + 2 * ($mes - 1) * self::PASO_FILA, ReporteGeneralService::MESES[$mes], $totales['meses'][$mes]);
        }
        $this->lineasVerticales($bordes, $cuerpoTop, $finCuerpo);

        // GRAN TOTAL (fondo morado, texto blanco 8px, padding 8px 4px)
        $base = $granTop + 13.38;
        $this->celda($bordes, 0, 2, $granTop, $altoGran, $bordeExterior, '#7c3aed');
        $this->texto('GRAN TOTAL', $bordes[0] + $bordeExterior / 2 + 3.0, $base, $this->negrita, self::T_GRAN_TOTAL, '#ffffff');
        foreach ($categorias as $c => $cat) {
            $totalCat = 0;
            for ($m = 1; $m <= $mesHasta; $m++) {
                $totalCat += $totales['meses'][$m]['categorias'][$cat]['dado'];
            }
            $this->celda($bordes, 2 + $c, 1, $granTop, $altoGran, $bordeExterior, '#7c3aed');
            $this->textoDerecha(number_format($totalCat, 0), $bordes[3 + $c] - self::BORDE / 2 - 3.0, $base, $this->negrita, self::T_GRAN_TOTAL, '#ffffff');
        }
        $this->celda($bordes, $n - 2, 1, $granTop, $altoGran, $bordeExterior, '#7c3aed');
        $this->textoDerecha(number_format($totales['total_dado'], 0), $bordes[$n - 1] - self::BORDE / 2 - 3.0, $base, $this->negrita, self::T_GRAN_TOTAL, '#ffffff');
        // En la vista la diferencia final salia en blanco sobre fondo claro (ilegible): aqui va
        // con el color de la diferencia (verde/rojo), igual que en el resto del reporte.
        $dif = $totales['total_diferencia'];
        $this->celda($bordes, $n - 1, 1, $granTop, $altoGran, $bordeExterior, $dif >= 0 ? '#dcfce7' : '#fef2f2');
        $this->textoDerecha($this->conSigno($dif), $bordes[$n] - $bordeExterior / 2 - 3.0, $base, $this->negrita, self::T_GRAN_TOTAL, $dif >= 0 ? '#166534' : '#dc2626');
    }

    /**
     * Filas Dado (verde) y Esperado (azul) de un mes; el nombre del mes, el
     * total y la diferencia ocupan las dos filas (rowspan 2).
     */
    private function filasMes(array $bordes, array $categorias, float $bordeExterior, float $top, string $nombreMes, array $datos): void
    {
        $n = count($bordes) - 1;
        $top2 = $top + self::PASO_FILA;
        $altoDoble = self::PASO_FILA + self::ALTO_CELDA;
        $baseDoble = $top + 16.86;

        $this->celda($bordes, 0, 1, $top, $altoDoble, $bordeExterior, '#f3f4f6');
        $this->texto($nombreMes, $bordes[0] + $bordeExterior / 2 + 2.25, $baseDoble, $this->negrita, self::T_CELDA, '#166534');

        // Tipo + categorias: una franja por fila (las lineas verticales entre
        // esas columnas se pintan una sola vez por tabla en lineasVerticales()).
        $this->celda($bordes, 1, $n - 3, $top, self::ALTO_CELDA, $bordeExterior, '#f0fdf4');
        $this->celda($bordes, 1, $n - 3, $top2, self::ALTO_CELDA, $bordeExterior, '#eff6ff');
        $this->textoCentrado('Dado', ($bordes[1] + $bordes[2]) / 2, $top + 9.11, $this->negrita, self::T_TIPO, '#166534');
        $this->textoCentrado('Esperado', ($bordes[1] + $bordes[2]) / 2, $top2 + 9.11, $this->negrita, self::T_TIPO, '#1d4ed8');

        foreach ($categorias as $c => $cat) {
            $derecha = $bordes[3 + $c] - self::BORDE / 2 - 2.25;
            $this->textoDerecha(number_format($datos['categorias'][$cat]['dado'], 0), $derecha, $top + 9.45, $this->regular, self::T_CELDA, '#166534');
            $this->textoDerecha(number_format($datos['categorias'][$cat]['esperado'], 0), $derecha, $top2 + 9.45, $this->regular, self::T_CELDA, '#1d4ed8');
        }

        $this->celda($bordes, $n - 2, 1, $top, $altoDoble, $bordeExterior, '#dcfce7');
        $this->textoDerecha(number_format($datos['total_dado'], 0), $bordes[$n - 1] - self::BORDE / 2 - 2.25, $baseDoble, $this->negrita, self::T_CELDA, '#166534');

        $dif = $datos['diferencia'];
        $this->celda($bordes, $n - 1, 1, $top, $altoDoble, $bordeExterior, $dif >= 0 ? '#dcfce7' : '#fef2f2');
        $this->textoDerecha($this->conSigno($dif), $bordes[$n] - $bordeExterior / 2 - 2.25, $baseDoble, $this->negrita, self::T_CELDA, $dif >= 0 ? '#166534' : '#dc2626');
    }

    /**
     * Lineas verticales entre la columna tipo y las categorias en todo el
     * cuerpo de la tabla (las franjas Dado/Esperado las tapan al pintarse).
     */
    private function lineasVerticales(array $bordes, float $desde, float $hasta): void
    {
        $n = count($bordes) - 1;
        for ($k = 2; $k <= $n - 3; $k++) {
            $this->rect($bordes[$k] - self::BORDE / 2, $desde, self::BORDE, $hasta - $desde, '#e5e7eb');
        }
    }

    private function filaEncabezado(array $bordes, array $categorias, float $top, float $bordeExterior, array $colores): void
    {
        $n = count($bordes) - 1;
        $base = $top + 10.96;

        $this->celda($bordes, 0, 1, $top, self::ALTO_TH, $bordeExterior, $colores['fondo']);
        $this->texto('MES', $bordes[0] + $bordeExterior / 2 + 3.0, $base, $this->negrita, self::T_CELDA, '#ffffff');
        $this->celda($bordes, 1, 1, $top, self::ALTO_TH, $bordeExterior, $colores['fondo']);

        foreach ($categorias as $c => $cat) {
            $this->celda($bordes, 2 + $c, 1, $top, self::ALTO_TH, $bordeExterior, $colores['fondo']);
            $this->textoCentrado(mb_strtoupper(ucfirst($cat)), ($bordes[2 + $c] + $bordes[3 + $c]) / 2, $base, $this->negrita, self::T_CELDA, '#ffffff');
        }

        $this->celda($bordes, $n - 2, 1, $top, self::ALTO_TH, $bordeExterior, $colores['fondo_total']);
        $this->textoCentrado('TOTAL', ($bordes[$n - 2] + $bordes[$n - 1]) / 2, $base, $this->negrita, self::T_CELDA, '#ffffff');
        $this->celda($bordes, $n - 1, 1, $top, self::ALTO_TH, $bordeExterior, $colores['fondo_dif']);
        $this->textoCentrado('DIFERENCIA', ($bordes[$n - 1] + $bordes[$n]) / 2, $base, $this->negrita, self::T_CELDA, '#ffffff');
    }

    private function sinDatos(float $y): float
    {
        $this->textoCentrado('No hay datos de compromisos para mostrar en este periodo.', self::CENTRO_X, $y + 37.4, $this->italica, 6.0, '#6b7280');

        return $y + 69.2;
    }

    private function pie(float $y, array $tenant, Carbon $generadoEn): void
    {
        $alto = self::BORDE + 6.0 + 2 * self::PASO_LINEA;
        $y = $this->ubicarBloque($y, self::MARGEN_ANTES_PIE, $alto);

        $this->rect(self::X0, $y, self::X1 - self::X0, self::BORDE, '#e5e7eb');
        $generado = 'Generado el '.$generadoEn->copy()->locale('es')->translatedFormat('d \d\e F \d\e Y \a \l\a\s h:i A');
        $this->textoCentrado($generado, self::CENTRO_X, $y + 13.2, $this->regular, self::T_CELDA, '#9ca3af');
        $sistema = 'Sistema de Administracion - '.$tenant['tenantSiglas'].' - '.$tenant['tenantNombre'];
        $this->textoCentrado($sistema, self::CENTRO_X, $y + 13.2 + self::PASO_LINEA, $this->regular, self::T_CELDA, '#9ca3af');
    }

    // ------------------------------------------------------------------
    // Paginacion y medidas
    // ------------------------------------------------------------------

    /**
     * Devuelve la Y donde va el bloque: a continuacion (con su margen) si cabe
     * en la pagina; si no, arriba de una pagina nueva (como page-break-inside:
     * avoid en la vista; el margen superior se pierde al inicio de pagina).
     */
    private function ubicarBloque(float $y, float $margen, float $alto): float
    {
        if ($y + $margen + $alto > self::ALTO_PAGINA) {
            $this->canvas->new_page();

            return 0.0;
        }

        return $y + $margen;
    }

    private function altoBloquePersona(int $meses, int $lineasNombre): float
    {
        return self::ALTO_BARRA_NOMBRE + self::BORDE + self::ALTO_TH + self::BORDE
            + (2 * $meses - 1) * self::PASO_FILA + self::ALTO_CELDA
            + 1.5 + 8.99 + $lineasNombre * self::PASO_LINEA + self::BORDE;
    }

    private function altoBloqueTotales(int $meses): float
    {
        return self::ALTO_BARRA_TOTALES + 1.5 + self::ALTO_TH + self::BORDE
            + (2 * $meses - 1) * self::PASO_FILA + self::ALTO_CELDA
            + self::BORDE + 21.22 + 1.5;
    }

    /**
     * Bordes (centro de la linea) de cada columna: mes, tipo, categorias, total y diferencia.
     *
     * @return array<int, float>
     */
    private function bordesColumnas(int $categorias, float $desde, float $hasta): array
    {
        $pcts = array_merge([self::PCT_MES, self::PCT_TIPO], array_fill(0, $categorias, self::PCT_CATEGORIA), [self::PCT_TOTAL, self::PCT_DIFERENCIA]);
        $suma = array_sum($pcts);
        $bordes = [$desde];
        $x = $desde;
        foreach ($pcts as $pct) {
            $x += ($hasta - $desde) * $pct / $suma;
            $bordes[] = $x;
        }
        $bordes[count($bordes) - 1] = $hasta;

        return $bordes;
    }

    private function anchoUtilTotalPersona(array $categorias): float
    {
        $bordes = $this->bordesColumnas(count($categorias), self::X0 + self::BORDE / 2, self::X1 - self::BORDE / 2);

        // Celda con colspan 2 (mes + tipo) menos borde y padding de 4px a cada lado.
        return ($bordes[2] - self::BORDE / 2 - 3.0) - ($bordes[0] + self::BORDE / 2 + 3.0);
    }

    /**
     * Parte el texto en lineas por espacios (como el navegador/DomPDF); una
     * palabra mas ancha que la celda queda sola en su linea.
     *
     * @return array<int, string>
     */
    private function partirEnLineas(string $texto, string $fuente, float $tam, float $ancho): array
    {
        $lineas = [];
        $actual = '';
        foreach (preg_split('/\s+/u', trim($texto)) as $palabra) {
            $candidata = $actual === '' ? $palabra : $actual.' '.$palabra;
            if ($actual !== '' && $this->anchoTexto($candidata, $fuente, $tam) > $ancho) {
                $lineas[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $candidata;
            }
        }
        $lineas[] = $actual;

        return $lineas;
    }

    private function conSigno(float|int $valor): string
    {
        return ($valor >= 0 ? '+' : '').number_format($valor, 0);
    }

    // ------------------------------------------------------------------
    // Primitivas de dibujo (coordenadas con origen arriba a la izquierda)
    // ------------------------------------------------------------------

    private function iniciar(string $titulo): void
    {
        $this->dompdf = app('dompdf');
        $this->canvas = new CanvasCpdf('letter', 'landscape', $this->dompdf);
        $this->pdf = $this->canvas->get_cpdf();
        $this->pdf->addInfo('Title', $titulo);

        $metricas = $this->dompdf->getFontMetrics();
        $this->regular = $metricas->getFont('DejaVu Sans', 'normal');
        $this->negrita = $metricas->getFont('DejaVu Sans', 'bold');
        $this->italica = $metricas->getFont('DejaVu Sans', 'italic');
        $this->subconjunto = $this->dompdf->getOptions()->getIsFontSubsettingEnabled();
        $this->anchos = [];
    }

    /**
     * Celda que abarca $span columnas a partir de la columna $col: el relleno
     * deja libre medio borde de cada lado (lo que se ve como linea de la tabla).
     */
    private function celda(array $bordes, int $col, int $span, float $top, float $alto, float $bordeExterior, string $color): void
    {
        $ultimo = count($bordes) - 1;
        $izq = $bordes[$col] + ($col === 0 ? $bordeExterior / 2 : self::BORDE / 2);
        $der = $bordes[$col + $span] - ($col + $span === $ultimo ? $bordeExterior / 2 : self::BORDE / 2);
        $this->rect($izq, $top, $der - $izq, $alto, $color);
    }

    private function rect(float $x, float $y, float $ancho, float $alto, string $color): void
    {
        $this->color($color);
        $this->pdf->filledRectangle($x, self::ALTO_PAGINA - $y - $alto, $ancho, $alto);
    }

    private function rectRedondeado(float $x, float $y, float $ancho, float $alto, float $rSupIzq, float $rSupDer, float $rInfDer, float $rInfIzq, string $color): void
    {
        $k = 0.5523; // aproximacion de un cuarto de circulo con una curva de Bezier
        $arriba = self::ALTO_PAGINA - $y;
        $abajo = $arriba - $alto;
        $der = $x + $ancho;

        $this->color($color);
        $pdf = $this->pdf;
        $pdf->moveTo($x + $rSupIzq, $arriba);
        $pdf->lineTo($der - $rSupDer, $arriba);
        if ($rSupDer > 0) {
            $pdf->curveTo($der - $rSupDer * (1 - $k), $arriba, $der, $arriba - $rSupDer * (1 - $k), $der, $arriba - $rSupDer);
        }
        $pdf->lineTo($der, $abajo + $rInfDer);
        if ($rInfDer > 0) {
            $pdf->curveTo($der, $abajo + $rInfDer * (1 - $k), $der - $rInfDer * (1 - $k), $abajo, $der - $rInfDer, $abajo);
        }
        $pdf->lineTo($x + $rInfIzq, $abajo);
        if ($rInfIzq > 0) {
            $pdf->curveTo($x + $rInfIzq * (1 - $k), $abajo, $x, $abajo + $rInfIzq * (1 - $k), $x, $abajo + $rInfIzq);
        }
        $pdf->lineTo($x, $arriba - $rSupIzq);
        if ($rSupIzq > 0) {
            $pdf->curveTo($x, $arriba - $rSupIzq * (1 - $k), $x + $rSupIzq * (1 - $k), $arriba, $x + $rSupIzq, $arriba);
        }
        $pdf->closePath();
        $pdf->fill();
    }

    /** $base es la linea base del texto. */
    private function texto(string $texto, float $x, float $base, string $fuente, float $tam, string $color): void
    {
        $this->color($color);
        $this->pdf->selectFont($fuente, '', true, $this->subconjunto);
        $this->pdf->addText($x, self::ALTO_PAGINA - $base, $tam, $texto);
    }

    private function textoDerecha(string $texto, float $derecha, float $base, string $fuente, float $tam, string $color): void
    {
        $this->texto($texto, $derecha - $this->anchoTexto($texto, $fuente, $tam), $base, $fuente, $tam, $color);
    }

    private function textoCentrado(string $texto, float $centro, float $base, string $fuente, float $tam, string $color): void
    {
        $this->texto($texto, $centro - $this->anchoTexto($texto, $fuente, $tam) / 2, $base, $fuente, $tam, $color);
    }

    private function anchoTexto(string $texto, string $fuente, float $tam): float
    {
        $clave = $fuente.'|'.$tam.'|'.$texto;
        if (! isset($this->anchos[$clave])) {
            $this->pdf->selectFont($fuente, '', true, $this->subconjunto);
            $this->anchos[$clave] = $this->pdf->getTextWidth($tam, $texto);
        }

        return $this->anchos[$clave];
    }

    private function color(string $hex): void
    {
        if (! isset($this->rgb[$hex])) {
            $limpio = ltrim($hex, '#');
            $this->rgb[$hex] = [
                hexdec(substr($limpio, 0, 2)) / 255,
                hexdec(substr($limpio, 2, 2)) / 255,
                hexdec(substr($limpio, 4, 2)) / 255,
            ];
        }
        $this->pdf->setColor($this->rgb[$hex]);
    }

    /** Normaliza #rgb / #rrggbb a #rrggbb; null si no es un color hexadecimal. */
    private function hexValido(?string $color): ?string
    {
        $limpio = ltrim(trim((string) $color), '#');
        if (preg_match('/^[0-9a-f]{3}$/i', $limpio)) {
            $limpio = $limpio[0].$limpio[0].$limpio[1].$limpio[1].$limpio[2].$limpio[2];
        }

        return preg_match('/^[0-9a-f]{6}$/i', $limpio) ? '#'.strtolower($limpio) : null;
    }

    private function logo(string $base64, float $x, float $y, float $ancho, float $alto): void
    {
        $archivo = tempnam($this->dompdf->getOptions()->getTempDir(), 'logo');
        try {
            file_put_contents($archivo, base64_decode($base64));
            $this->canvas->image($archivo, $x, $y, $ancho, $alto);
        } catch (\Throwable $e) {
            // Sin logo el reporte sigue siendo util; no se corta la descarga por eso.
            Log::warning('Reporte General: no se pudo dibujar el logo del tenant: '.$e->getMessage());
        } finally {
            @unlink($archivo);
        }
    }
}

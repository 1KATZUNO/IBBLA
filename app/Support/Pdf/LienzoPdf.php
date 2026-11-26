<?php

namespace App\Support\Pdf;

use Dompdf\Adapter\CPDF as CanvasCpdf;
use Dompdf\Cpdf;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Log;

/**
 * Primitivas para dibujar un PDF directamente sobre el canvas de DomPDF (CPDF), sin su motor de
 * maquetacion HTML: texto, rectangulos, lineas, imagenes y paginas, con coordenadas en puntos y
 * origen arriba a la izquierda (como una pagina web).
 *
 * Por que: DomPDF maquetando una vista Blade crece mas que lineal con las celdas (el Reporte General
 * tardaba ~240 s y Hostinger cortaba con 504). Dibujando las mismas celdas con estas primitivas,
 * con la misma fuente (DejaVu Sans) y el mismo motor de salida, un reporte de 150 personas sale en
 * menos de un segundo. Es la tecnica de ReporteGeneralPdf, generalizada.
 *
 * Para reportes nuevos no se usa directo: se usa DocumentoPdf (la plantilla con encabezado, pie,
 * tablas con salto de pagina, tarjetas de resumen...), que dibuja con esta clase.
 *
 * Modo simulacion: con simular() todo lo que se dibuja se descarta y solo se cuentan las paginas;
 * sirve para saber en que pagina va a caer algo (p. ej. un indice) antes de dibujarlo.
 */
class LienzoPdf
{
    public const REGULAR = 'regular';

    public const NEGRITA = 'negrita';

    public const ITALICA = 'italica';

    public readonly float $ancho;

    public readonly float $alto;

    private Dompdf $dompdf;

    private CanvasCpdf $canvas;

    private Cpdf $pdf;

    /** @var array<string, string> estilo => fuente reducida (ver FuentesPdf), o la completa si no se pudo preparar */
    private array $fuentes;

    /** @var array<string, string> estilo => fuente completa de DomPDF, para textos con caracteres fuera de la reducida */
    private array $completas;

    private bool $subconjunto;

    /** @var array<string, float> ancho de cada texto ya medido (fuente|tamano|texto) */
    private array $anchos = [];

    /** @var array<string, array{0: float, 1: float, 2: float}> */
    private array $rgb = [];

    /** @var array<string, string> imagenes ya preparadas en esta peticion (clave: imagen + tamano + fondo) */
    private static array $preparadas = [];

    private bool $simulando = false;

    /** @var array<string, string> temporales de imagenBase64Repetida() (clave => ruta), se borran al destruir */
    private array $archivosRepetidos = [];

    private int $paginasSimuladas = 0;

    /** En los scripts de pagina el estado de color de CPDF no es el de esa pagina: se fuerza. */
    private bool $forzarColor = false;

    public function __construct(string $orientacion = 'portrait', string $titulo = '', string $autor = '')
    {
        $this->dompdf = app('dompdf');
        $this->canvas = new CanvasCpdf('letter', $orientacion, $this->dompdf);
        $this->pdf = $this->canvas->get_cpdf();
        $this->ancho = (float) $this->canvas->get_width();
        $this->alto = (float) $this->canvas->get_height();

        if ($titulo !== '') {
            $this->pdf->addInfo('Title', $titulo);
        }
        if ($autor !== '') {
            $this->pdf->addInfo('Author', $autor);
        }

        $metricas = $this->dompdf->getFontMetrics();
        $this->completas = [
            self::REGULAR => $metricas->getFont('DejaVu Sans', 'normal'),
            self::NEGRITA => $metricas->getFont('DejaVu Sans', 'bold'),
            self::ITALICA => $metricas->getFont('DejaVu Sans', 'italic'),
        ];
        $this->fuentes = FuentesPdf::reducidas($metricas) ?? $this->completas;
        $this->subconjunto = $this->dompdf->getOptions()->getIsFontSubsettingEnabled();
    }

    // ------------------------------------------------------------------
    // Paginas
    // ------------------------------------------------------------------

    public function nuevaPagina(): void
    {
        if ($this->simulando) {
            $this->paginasSimuladas++;

            return;
        }

        $this->canvas->new_page();
    }

    /** Numero de la pagina en que se esta dibujando (en simulacion, la que seria). */
    public function pagina(): int
    {
        return $this->canvas->get_page_number() + $this->paginasSimuladas;
    }

    public function paginas(): int
    {
        return $this->canvas->get_page_count();
    }

    /**
     * Corre $dibujo descartando lo que dibuje y devuelve cuantas paginas nuevas habria abierto.
     * Quien llama debe guardar y restaurar su propio estado (posicion, etc.).
     */
    public function simular(callable $dibujo): int
    {
        $antes = [$this->simulando, $this->paginasSimuladas];
        $this->simulando = true;
        $this->paginasSimuladas = 0;

        try {
            $dibujo();

            return $this->paginasSimuladas;
        } finally {
            [$this->simulando, $this->paginasSimuladas] = $antes;
        }
    }

    public function simulando(): bool
    {
        return $this->simulando;
    }

    /**
     * Dibuja algo en todas las paginas ya creadas (pie con "Pagina N de M"). $dibujo recibe el numero
     * de pagina y el total. Se llama al final, cuando ya existen todas las paginas.
     *
     * @param  callable(int, int): void  $dibujo
     */
    public function enCadaPagina(callable $dibujo): void
    {
        $this->forzarColor = true;
        try {
            $this->canvas->page_script(function (int $pagina, int $total) use ($dibujo) {
                $dibujo($pagina, $total);
            });
        } finally {
            $this->forzarColor = false;
        }
    }

    public function salida(): string
    {
        return $this->canvas->output();
    }

    // ------------------------------------------------------------------
    // Texto ($base es la linea base del texto)
    // ------------------------------------------------------------------

    public function texto(string $texto, float $x, float $base, string $estilo, float $tam, string $color): void
    {
        if ($this->simulando || $texto === '') {
            return;
        }

        $this->color($color);
        $this->pdf->selectFont($this->fuente($texto, $estilo), '', true, $this->subconjunto);
        $this->pdf->addText($x, $this->alto - $base, $tam, $texto);
    }

    public function textoDerecha(string $texto, float $derecha, float $base, string $estilo, float $tam, string $color): void
    {
        $this->texto($texto, $derecha - $this->anchoTexto($texto, $estilo, $tam), $base, $estilo, $tam, $color);
    }

    public function textoCentrado(string $texto, float $centro, float $base, string $estilo, float $tam, string $color): void
    {
        $this->texto($texto, $centro - $this->anchoTexto($texto, $estilo, $tam) / 2, $base, $estilo, $tam, $color);
    }

    public function anchoTexto(string $texto, string $estilo, float $tam): float
    {
        $clave = $estilo.'|'.$tam.'|'.$texto;
        if (! isset($this->anchos[$clave])) {
            $this->pdf->selectFont($this->fuente($texto, $estilo), '', true, $this->subconjunto);
            $this->anchos[$clave] = $this->pdf->getTextWidth($tam, $texto);
        }

        return $this->anchos[$clave];
    }

    /**
     * Parte el texto en lineas que quepan en $ancho, por espacios como el navegador; una palabra que
     * sola no cabe se corta por letras. Respeta los saltos de linea del texto.
     *
     * @return list<string>
     */
    public function partirEnLineas(string $texto, string $estilo, float $tam, float $ancho): array
    {
        $lineas = [];
        foreach (preg_split('/\R/u', trim($texto)) as $parrafo) {
            $actual = '';
            foreach (preg_split('/\s+/u', trim($parrafo), -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
                $candidata = $actual === '' ? $palabra : $actual.' '.$palabra;
                if ($this->anchoTexto($candidata, $estilo, $tam) <= $ancho) {
                    $actual = $candidata;

                    continue;
                }
                if ($actual !== '') {
                    $lineas[] = $actual;
                }
                // La palabra sola tampoco cabe: se corta por letras.
                while ($this->anchoTexto($palabra, $estilo, $tam) > $ancho && mb_strlen($palabra) > 1) {
                    $corte = mb_strlen($palabra) - 1;
                    while ($corte > 1 && $this->anchoTexto(mb_substr($palabra, 0, $corte), $estilo, $tam) > $ancho) {
                        $corte--;
                    }
                    $lineas[] = mb_substr($palabra, 0, $corte);
                    $palabra = mb_substr($palabra, $corte);
                }
                $actual = $palabra;
            }
            $lineas[] = $actual;
        }

        return $lineas;
    }

    // ------------------------------------------------------------------
    // Figuras
    // ------------------------------------------------------------------

    public function rect(float $x, float $y, float $ancho, float $alto, string $color): void
    {
        if ($this->simulando) {
            return;
        }

        $this->color($color);
        $this->pdf->filledRectangle($x, $this->alto - $y - $alto, $ancho, $alto);
    }

    /** Linea horizontal de $grosor puntos (un rectangulo fino: sale igual en todos los visores). */
    public function lineaHorizontal(float $x1, float $x2, float $y, string $color, float $grosor = 0.5): void
    {
        $this->rect($x1, $y - $grosor / 2, $x2 - $x1, $grosor, $color);
    }

    public function lineaVertical(float $x, float $y1, float $y2, string $color, float $grosor = 0.5): void
    {
        $this->rect($x - $grosor / 2, $y1, $grosor, $y2 - $y1, $color);
    }

    /** Contorno de un rectangulo (cuatro lineas por dentro del borde). */
    public function marco(float $x, float $y, float $ancho, float $alto, string $color, float $grosor = 0.5): void
    {
        $this->rect($x, $y, $ancho, $grosor, $color);
        $this->rect($x, $y + $alto - $grosor, $ancho, $grosor, $color);
        $this->rect($x, $y, $grosor, $alto, $color);
        $this->rect($x + $ancho - $grosor, $y, $grosor, $alto, $color);
    }

    public function rectRedondeado(float $x, float $y, float $ancho, float $alto, float $radio, string $color): void
    {
        if ($this->simulando) {
            return;
        }

        $k = 0.5523; // aproximacion de un cuarto de circulo con una curva de Bezier
        $r = min($radio, $ancho / 2, $alto / 2);
        $arriba = $this->alto - $y;
        $abajo = $arriba - $alto;
        $der = $x + $ancho;

        $this->color($color);
        $pdf = $this->pdf;
        $pdf->moveTo($x + $r, $arriba);
        $pdf->lineTo($der - $r, $arriba);
        $pdf->curveTo($der - $r * (1 - $k), $arriba, $der, $arriba - $r * (1 - $k), $der, $arriba - $r);
        $pdf->lineTo($der, $abajo + $r);
        $pdf->curveTo($der, $abajo + $r * (1 - $k), $der - $r * (1 - $k), $abajo, $der - $r, $abajo);
        $pdf->lineTo($x + $r, $abajo);
        $pdf->curveTo($x + $r * (1 - $k), $abajo, $x, $abajo + $r * (1 - $k), $x, $abajo + $r);
        $pdf->lineTo($x, $arriba - $r);
        $pdf->curveTo($x, $arriba - $r * (1 - $k), $x + $r * (1 - $k), $arriba, $x + $r, $arriba);
        $pdf->closePath();
        $pdf->fill();
    }

    /**
     * Muchos rectangulos del mismo color rellenados como UNA sola figura (los modulos de un QR). Rellenados uno
     * por uno, el visor suaviza cada borde por separado y entre rectangulos vecinos quedan rayas blancas finas;
     * como una sola figura no.
     *
     * @param  iterable<array{0: float, 1: float, 2: float, 3: float}>  $rectangulos  [x, y, ancho, alto]
     */
    public function rectangulos(iterable $rectangulos, string $color): void
    {
        if ($this->simulando) {
            return;
        }

        $this->color($color);
        $pdf = $this->pdf;
        $hay = false;
        foreach ($rectangulos as [$x, $y, $ancho, $alto]) {
            $arriba = $this->alto - $y;
            $pdf->moveTo($x, $arriba);
            $pdf->lineTo($x + $ancho, $arriba);
            $pdf->lineTo($x + $ancho, $arriba - $alto);
            $pdf->lineTo($x, $arriba - $alto);
            $pdf->closePath();
            $hay = true;
        }
        if ($hay) {
            $pdf->fill();
        }
    }

    public function circulo(float $centroX, float $centroY, float $radio, string $color): void
    {
        if ($this->simulando) {
            return;
        }

        $this->color($color);
        $this->pdf->filledEllipse($centroX, $this->alto - $centroY, $radio, $radio);
    }

    /**
     * Imagen (PNG/JPG) desde base64, sobre fondo $fondo. Si no se puede dibujar se sigue sin ella:
     * un logo no vale una descarga fallida.
     *
     * Antes de meterla se achica al tamano en que se ve (3 px por punto) y se aplana sobre el fondo:
     * CPDF separa la transparencia de un PNG pixel por pixel en PHP, y con el logo de 500x500 eso
     * costaba ~0,5 s por PDF (y 50 veces eso en un ZIP de 50 estados de cuenta).
     */
    public function imagenBase64(string $base64, float $x, float $y, float $ancho, float $alto, string $fondo = '#ffffff'): void
    {
        if ($this->simulando || $base64 === '') {
            return;
        }

        $archivo = tempnam($this->dompdf->getOptions()->getTempDir(), 'img');
        try {
            file_put_contents($archivo, self::imagenPreparada($base64, $ancho, $alto, $fondo));
            $this->canvas->image($archivo, $x, $y, $ancho, $alto);
        } catch (\Throwable $e) {
            Log::warning('PDF: no se pudo dibujar una imagen: '.$e->getMessage());
        } finally {
            @unlink($archivo);
        }
    }

    /**
     * Como imagenBase64(), para una imagen que se repite muchas veces en el mismo PDF (el logo de cada tarjeta
     * con QR): se escribe a un archivo temporal UNA vez y se dibuja siempre desde el mismo, asi CPDF la mete en
     * el PDF una sola vez y la reutiliza (cachea por nombre de archivo). Los temporales se borran al destruir
     * el lienzo.
     */
    public function imagenBase64Repetida(string $base64, float $x, float $y, float $ancho, float $alto, string $fondo = '#ffffff'): void
    {
        if ($this->simulando || $base64 === '') {
            return;
        }

        $clave = md5($base64).'|'.$ancho.'|'.$alto.'|'.$fondo;
        try {
            if (! isset($this->archivosRepetidos[$clave])) {
                $archivo = tempnam($this->dompdf->getOptions()->getTempDir(), 'img');
                file_put_contents($archivo, self::imagenPreparada($base64, $ancho, $alto, $fondo));
                $this->archivosRepetidos[$clave] = $archivo;
            }
            $this->canvas->image($this->archivosRepetidos[$clave], $x, $y, $ancho, $alto);
        } catch (\Throwable $e) {
            Log::warning('PDF: no se pudo dibujar una imagen: '.$e->getMessage());
        }
    }

    public function __destruct()
    {
        foreach ($this->archivosRepetidos as $archivo) {
            @unlink($archivo);
        }
    }

    /** PNG sin transparencia del tamano en que se dibuja; si GD no puede leerla, la imagen original. */
    private static function imagenPreparada(string $base64, float $ancho, float $alto, string $fondo): string
    {
        $clave = md5($base64).'|'.$ancho.'|'.$alto.'|'.$fondo;
        if (isset(self::$preparadas[$clave])) {
            return self::$preparadas[$clave];
        }

        $binario = (string) base64_decode($base64);
        $origen = function_exists('imagecreatefromstring') ? @imagecreatefromstring($binario) : false;
        if ($origen === false) {
            return self::$preparadas[$clave] = $binario;
        }

        $w = max(1, (int) round($ancho * 3));
        $h = max(1, (int) round($alto * 3));
        $destino = imagecreatetruecolor($w, $h);
        $hex = ltrim(self::hexValido($fondo) ?? '#ffffff', '#');
        imagefill($destino, 0, 0, imagecolorallocate($destino, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))));
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $w, $h, imagesx($origen), imagesy($origen));

        ob_start();
        imagepng($destino, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($origen);
        imagedestroy($destino);

        return self::$preparadas[$clave] = $png;
    }

    // ------------------------------------------------------------------

    /** Normaliza #rgb / #rrggbb; null si no es un color hexadecimal (p. ej. un color de la iglesia mal guardado). */
    public static function hexValido(?string $color): ?string
    {
        $limpio = ltrim(trim((string) $color), '#');
        if (preg_match('/^[0-9a-f]{3}$/i', $limpio)) {
            $limpio = $limpio[0].$limpio[0].$limpio[1].$limpio[1].$limpio[2].$limpio[2];
        }

        return preg_match('/^[0-9a-f]{6}$/i', $limpio) ? '#'.strtolower($limpio) : null;
    }

    /** La fuente reducida si cubre todos los caracteres del texto; si no, la completa. */
    private function fuente(string $texto, string $estilo): string
    {
        return FuentesPdf::cubre($texto) ? $this->fuentes[$estilo] : $this->completas[$estilo];
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
        $this->pdf->setColor($this->rgb[$hex], $this->forzarColor);
    }
}

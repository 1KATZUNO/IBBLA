<?php

namespace App\Services\Tarjetas;

use App\Support\Pdf\LienzoPdf;
use App\Support\Qr\MatrizQr;
use Illuminate\Support\Collection;

/**
 * PDF de tarjetas con QR de acceso: hoja carta vertical, 8 tarjetas por pagina (2 columnas x 4 filas) de
 * 9,5 x 6,35 cm (270 x 180 pt) con marcas de corte en el margen y una guia gris fina alrededor de cada una.
 *
 * Se dibuja directo sobre el lienzo de DomPDF (LienzoPdf, la tecnica de ReporteGeneralPdf) y el QR se pinta
 * con rectangulos a partir de su matriz (MatrizQr), sin SVG ni imagenes: con DomPDF maquetando HTML y un SVG
 * por tarjeta, 255 tarjetas no entraban en los 10 s de Hostinger; asi salen en ~1 s. El logo de la iglesia se
 * mete una sola vez en el PDF y se reutiliza en todas las tarjetas.
 */
class TarjetasPdf
{
    // Pagina carta (pt) y rejilla
    private const MARGEN_X = 36.0;

    private const MARGEN_Y = 36.0;

    public const ANCHO = 270.0;

    public const ALTO = 180.0;

    public const COLUMNAS = 2;

    public const FILAS = 4;

    public const POR_PAGINA = self::COLUMNAS * self::FILAS;

    // Tarjeta
    private const ALTO_BANDA = 40.0;

    private const PADDING = 16.0;

    private const LADO_QR = 122.0;           // con el margen blanco de 4 modulos incluido

    private const GRIS_GUIA = '#d1d5db';

    private const NEGRO = '#111827';

    private const GRIS = '#6b7280';

    private LienzoPdf $lienzo;

    private array $marca;

    /**
     * @param  Collection<int, array{nombre: string, clase: ?string, url: string}>|array  $tarjetas
     * @param  array{siglas: string, nombre: string, color: string, colorOscuro: string, logoBase64: string}  $marca
     */
    public function render(Collection|array $tarjetas, array $marca, string $titulo = 'Tarjetas de acceso'): string
    {
        $tarjetas = collect($tarjetas)->values();
        $this->marca = $marca;
        MatrizQr::precalcular($tarjetas->pluck('url'));
        $this->lienzo = new LienzoPdf('portrait', $titulo.' - '.$marca['siglas'], $marca['nombre']);

        $paginas = max(1, (int) ceil($tarjetas->count() / self::POR_PAGINA));
        foreach ($tarjetas->chunk(self::POR_PAGINA)->values() as $i => $grupo) {
            if ($i > 0) {
                $this->lienzo->nuevaPagina();
            }
            $this->encabezadoPagina($titulo, $i + 1, $paginas);
            $this->marcasDeCorte();
            foreach ($grupo->values() as $j => $tarjeta) {
                $x = self::MARGEN_X + ($j % self::COLUMNAS) * self::ANCHO;
                $y = self::MARGEN_Y + intdiv($j, self::COLUMNAS) * self::ALTO;
                $this->tarjeta($tarjeta, $x, $y);
            }
        }

        if ($tarjetas->isEmpty()) {
            $this->encabezadoPagina($titulo, 1, 1);
            $this->lienzo->textoCentrado('No hay personas para este filtro.', $this->lienzo->ancho / 2, 120, LienzoPdf::REGULAR, 11, self::GRIS);
        }

        return $this->lienzo->salida();
    }

    private function encabezadoPagina(string $titulo, int $pagina, int $paginas): void
    {
        $texto = $this->marca['siglas'].' · '.$titulo.' · '.now()->format('d/m/Y').' · Página '.$pagina.' de '.$paginas;
        $this->lienzo->texto($texto, self::MARGEN_X + 8, 24, LienzoPdf::REGULAR, 7, self::GRIS);
    }

    /** Marcas de corte en el margen, en la prolongacion de cada linea de la rejilla. */
    private function marcasDeCorte(): void
    {
        $l = $this->lienzo;
        $derecha = self::MARGEN_X + self::COLUMNAS * self::ANCHO;
        $abajo = self::MARGEN_Y + self::FILAS * self::ALTO;

        for ($c = 0; $c <= self::COLUMNAS; $c++) {
            $x = self::MARGEN_X + $c * self::ANCHO;
            $l->lineaVertical($x, self::MARGEN_Y - 22, self::MARGEN_Y - 6, self::NEGRO, 0.5);
            $l->lineaVertical($x, $abajo + 6, $abajo + 22, self::NEGRO, 0.5);
        }
        for ($f = 0; $f <= self::FILAS; $f++) {
            $y = self::MARGEN_Y + $f * self::ALTO;
            $l->lineaHorizontal(self::MARGEN_X - 22, self::MARGEN_X - 6, $y, self::NEGRO, 0.5);
            $l->lineaHorizontal($derecha + 6, $derecha + 22, $y, self::NEGRO, 0.5);
        }
    }

    /** @param  array{nombre: string, clase: ?string, url: string}  $t */
    private function tarjeta(array $t, float $x, float $y): void
    {
        $l = $this->lienzo;
        $m = $this->marca;

        // Guia de corte y banda con la marca de la iglesia
        $l->marco($x, $y, self::ANCHO, self::ALTO, self::GRIS_GUIA, 0.4);
        $l->rect($x, $y, self::ANCHO, self::ALTO_BANDA, $m['colorOscuro']);
        $l->rect($x, $y + self::ALTO_BANDA, self::ANCHO, 2.5, $m['color']);
        $l->imagenBase64Repetida($m['logoBase64'], $x + 10, $y + 5, 30, 30, $m['colorOscuro']);
        $l->texto($m['siglas'], $x + 46, $y + 19, LienzoPdf::NEGRITA, 12, '#ffffff');
        $nombreIglesia = $this->recortar($m['nombre'], LienzoPdf::REGULAR, 6.5, self::ANCHO - 46 - 12);
        $l->texto($nombreIglesia, $x + 46, $y + 30, LienzoPdf::REGULAR, 6.5, '#dbeafe');

        // QR a la derecha (su margen blanco lo separa del texto)
        $qx = $x + self::ANCHO - 8 - self::LADO_QR;
        $qy = $y + self::ALTO_BANDA + 2.5 + (self::ALTO - self::ALTO_BANDA - 2.5 - self::LADO_QR) / 2;
        $this->qr($t['url'], $qx, $qy, self::LADO_QR);

        // Texto a la izquierda
        $izq = $x + self::PADDING;
        $ancho = $qx + 6 - $izq;
        $base = $y + self::ALTO_BANDA + 20;
        $l->texto('TARJETA DE ACCESO', $izq, $base, LienzoPdf::NEGRITA, 6.5, $m['color']);

        [$lineas, $tam] = $this->nombreEnLineas($t['nombre'], $ancho);
        $base += $tam + 6;
        foreach ($lineas as $linea) {
            $l->texto($linea, $izq, $base, LienzoPdf::NEGRITA, $tam, self::NEGRO);
            $base += $tam * 1.18;
        }

        if (! empty($t['clase'])) {
            $base += 4;
            $l->texto('Clase', $izq, $base, LienzoPdf::REGULAR, 6.5, self::GRIS);
            $base += 10.5;
            $l->texto($this->recortar($t['clase'], LienzoPdf::NEGRITA, 9, $ancho), $izq, $base, LienzoPdf::NEGRITA, 9, '#374151');
        }

        // Instruccion al pie, alineada con la parte baja del QR
        $pie = $y + self::ALTO - 26;
        $l->rect($izq, $pie - 10, 2, 22, $m['color']);
        $l->texto('Escanea para entrar', $izq + 7, $pie, LienzoPdf::NEGRITA, 7.5, self::NEGRO);
        $l->texto('a tu cuenta', $izq + 7, $pie + 9.5, LienzoPdf::NEGRITA, 7.5, self::NEGRO);
    }

    /**
     * El nombre en hasta 3 lineas: 15 pt si cabe, si no 13 y 11; lo que no quepa se corta con "…".
     *
     * @return array{0: list<string>, 1: float}
     */
    private function nombreEnLineas(string $nombre, float $ancho): array
    {
        foreach ([15.0, 13.0, 11.0] as $tam) {
            $lineas = $this->lienzo->partirEnLineas($nombre, LienzoPdf::NEGRITA, $tam, $ancho);
            $palabraLarga = collect($lineas)->contains(fn ($l) => $this->lienzo->anchoTexto($l, LienzoPdf::NEGRITA, $tam) > $ancho);
            if (count($lineas) <= ($tam >= 15.0 ? 2 : 3) && ! $palabraLarga) {
                return [$lineas, $tam];
            }
        }

        $lineas = array_slice($lineas, 0, 3);
        $lineas = array_map(fn ($l) => $this->recortar($l, LienzoPdf::NEGRITA, 11.0, $ancho), $lineas);

        return [$lineas, 11.0];
    }

    private function recortar(string $texto, string $estilo, float $tam, float $ancho): string
    {
        if ($this->lienzo->anchoTexto($texto, $estilo, $tam) <= $ancho) {
            return $texto;
        }
        while (mb_strlen($texto) > 1 && $this->lienzo->anchoTexto($texto.'…', $estilo, $tam) > $ancho) {
            $texto = mb_substr($texto, 0, -1);
        }

        return rtrim($texto).'…';
    }

    /** El QR con su margen blanco: un rectangulo por tramo de modulos oscuros, rellenados como una sola figura. */
    private function qr(string $texto, float $x, float $y, float $lado): void
    {
        $matriz = MatrizQr::de($texto);
        $modulo = $lado / (count($matriz) + 2 * MatrizQr::MARGEN);

        $this->lienzo->rect($x, $y, $lado, $lado, '#ffffff');
        $this->lienzo->rectangulos(array_map(fn (array $t) => [
            $x + ($t[0] + MatrizQr::MARGEN) * $modulo,
            $y + ($t[1] + MatrizQr::MARGEN) * $modulo,
            $t[2] * $modulo,
            $modulo,
        ], MatrizQr::tramos($matriz)), self::NEGRO);
    }
}

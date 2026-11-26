<?php

namespace App\Support\Qr;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\Cache;

/**
 * La matriz de modulos de un QR (la libreria bacon/bacon-qr-code que ya trae simple-qrcode) y su dibujo como
 * SVG de un solo trazo. La misma matriz se dibuja en el PDF de tarjetas con rectangulos sobre el lienzo de
 * DomPDF (TarjetasPdf), asi pantalla y papel muestran exactamente el mismo codigo.
 *
 * Correccion de errores M (15 %): la direccion de la tarjeta (~60 caracteres) queda en la version 4, de 33x33
 * modulos, los mas grandes posibles para ese contenido; impreso a 3 cm cada modulo mide ~0,9 mm y lo lee
 * cualquier telefono. Con H serian 41x41 modulos mas chicos y no hay logo encima que lo justifique.
 *
 * Calcular un QR cuesta ~15 ms en PHP (la libreria prueba las 8 mascaras y se queda con la mejor); en el PDF
 * masivo eso era casi todo el tiempo. La matriz de una direccion no cambia nunca, asi que se guarda en la cache
 * de la app (filas de 0 y 1): el PDF siguiente sale en una fraccion de segundo. Si la cache falla, se calcula.
 */
final class MatrizQr
{
    /** Margen blanco obligatorio alrededor del codigo, en modulos (la norma pide 4). */
    public const MARGEN = 4;

    /** Diez anos: en la practica, para siempre (putMany no acepta "forever"). */
    private const SEGUNDOS_CACHE = 315360000;

    /** @var array<string, list<list<bool>>> matrices ya resueltas en esta peticion (clave de cache => matriz) */
    private static array $memoria = [];

    /**
     * @return list<list<bool>> filas de arriba hacia abajo; true = modulo oscuro
     */
    public static function de(string $texto): array
    {
        self::precalcular([$texto]);

        return self::$memoria[self::clave($texto)];
    }

    /**
     * Deja listas las matrices de muchas direcciones con una lectura y una escritura de cache (el PDF masivo lo
     * llama antes de dibujar).
     *
     * @param  iterable<string>  $textos
     */
    public static function precalcular(iterable $textos): void
    {
        $pendientes = [];
        foreach ($textos as $texto) {
            $clave = self::clave($texto);
            if (! isset(self::$memoria[$clave])) {
                $pendientes[$clave] = $texto;
            }
        }
        if ($pendientes === []) {
            return;
        }

        try {
            $guardadas = Cache::many(array_keys($pendientes));
        } catch (\Throwable) {
            $guardadas = [];
        }

        $nuevas = [];
        foreach ($pendientes as $clave => $texto) {
            $matriz = self::desempacar($guardadas[$clave] ?? null);
            if ($matriz === null) {
                $matriz = self::calcular($texto);
                $nuevas[$clave] = self::empacar($matriz);
            }
            self::$memoria[$clave] = $matriz;
        }

        if ($nuevas !== []) {
            try {
                Cache::putMany($nuevas, self::SEGUNDOS_CACHE);
            } catch (\Throwable) {
                // Sin cache el QR igual sale; solo tarda mas la proxima vez.
            }
        }
    }

    /**
     * Tramos horizontales de modulos oscuros seguidos de cada fila: [x, y, largo]. Dibujar un rectangulo por
     * tramo (y no uno por modulo) deja el SVG y el PDF varias veces mas livianos.
     *
     * @param  list<list<bool>>  $matriz
     * @return list<array{0: int, 1: int, 2: int}>
     */
    public static function tramos(array $matriz): array
    {
        $tramos = [];
        foreach ($matriz as $y => $fila) {
            $inicio = null;
            foreach ($fila as $x => $oscuro) {
                if ($oscuro && $inicio === null) {
                    $inicio = $x;
                } elseif (! $oscuro && $inicio !== null) {
                    $tramos[] = [$inicio, $y, $x - $inicio];
                    $inicio = null;
                }
            }
            if ($inicio !== null) {
                $tramos[] = [$inicio, $y, count($fila) - $inicio];
            }
        }

        return $tramos;
    }

    /**
     * SVG cuadrado con el margen blanco incluido; ocupa el ancho de su contenedor sin perder nitidez.
     */
    public static function svg(string $texto, string $color = '#111827', string $titulo = 'Código QR'): string
    {
        $matriz = self::de($texto);
        $lado = count($matriz) + 2 * self::MARGEN;

        $trazo = '';
        foreach (self::tramos($matriz) as [$x, $y, $largo]) {
            $trazo .= 'M'.($x + self::MARGEN).' '.($y + self::MARGEN).'h'.$largo.'v1h-'.$largo.'z';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$lado.' '.$lado.'" width="100%" height="100%" shape-rendering="crispEdges" role="img" aria-label="'.e($titulo).'">'
            .'<rect width="'.$lado.'" height="'.$lado.'" fill="#ffffff"/>'
            .'<path d="'.$trazo.'" fill="'.e($color).'"/>'
            .'</svg>';
    }

    // ------------------------------------------------------------------

    private static function clave(string $texto): string
    {
        return 'qr:matriz:'.sha1($texto);
    }

    /** @return list<list<bool>> */
    private static function calcular(string $texto): array
    {
        $matriz = Encoder::encode($texto, ErrorCorrectionLevel::M())->getMatrix();
        $filas = [];
        for ($y = 0; $y < $matriz->getHeight(); $y++) {
            $fila = [];
            for ($x = 0; $x < $matriz->getWidth(); $x++) {
                $fila[] = $matriz->get($x, $y) === 1;
            }
            $filas[] = $fila;
        }

        return $filas;
    }

    /** @param  list<list<bool>>  $filas */
    private static function empacar(array $filas): string
    {
        return implode("\n", array_map(fn (array $fila) => implode('', array_map(fn (bool $o) => $o ? '1' : '0', $fila)), $filas));
    }

    /** @return list<list<bool>>|null */
    private static function desempacar(mixed $texto): ?array
    {
        if (! is_string($texto) || ! preg_match('/^[01]+(\n[01]+)*$/', $texto)) {
            return null;
        }

        return array_map(fn (string $fila) => array_map(fn (string $c) => $c === '1', str_split($fila)), explode("\n", $texto));
    }
}

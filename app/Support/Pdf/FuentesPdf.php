<?php

namespace App\Support\Pdf;

use Dompdf\FontMetrics;
use FontLib\BinaryStream;
use FontLib\Font;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fuentes de los PDF de reportes: DejaVu Sans reducida a los caracteres que usan los reportes en
 * espanol (latin basico y extendido, puntuacion y simbolos de moneda como ₡).
 *
 * Por que: al cerrar cada PDF, CPDF recorta la fuente completa (DejaVu Sans pesa ~700 KB por
 * estilo) para meter solo las letras usadas, y eso cuesta ~0,35 s por documento. En un PDF suelto no
 * se nota, pero un ZIP con 100 estados de cuenta tardaba mas de un minuto. Con la fuente ya
 * reducida (~10 veces mas chica) ese recorte es casi gratis.
 *
 * Las fuentes reducidas se generan la primera vez en storage/app/fuentes-pdf (no van en git) y se
 * reutilizan. Si algo falla al generarlas se usan las de DomPDF tal cual: mas lento, pero igual.
 * Un texto con algun caracter fuera del juego reducido (un nombre en otro alfabeto) se dibuja con la
 * fuente completa (ver LienzoPdf).
 */
final class FuentesPdf
{
    private const VERSION = 'reportes-v1';

    private const ESTILOS = [
        LienzoPdf::REGULAR => 'normal',
        LienzoPdf::NEGRITA => 'bold',
        LienzoPdf::ITALICA => 'italic',
    ];

    /** Rangos de caracteres de las fuentes reducidas (y los que se pueden dibujar con ellas). */
    public const RANGOS = [
        [0x0020, 0x007E], // latin basico
        [0x00A0, 0x017F], // latin-1 y latin extendido A (tildes, ñ, ü, ç...)
        [0x2010, 0x2027], // guiones, comillas, puntos suspensivos, punto medio
        [0x2030, 0x203A], // por mil, comillas angulares simples
        [0x20A0, 0x20BF], // monedas: ₡ € ...
        [0x2122, 0x2122], // marca registrada
    ];

    /** @var array<string, array<string, string>|null> rutas ya resueltas en esta peticion */
    private static array $cache = [];

    /**
     * Rutas (sin extension, como las usa CPDF) de las fuentes reducidas por estilo, o null si no se
     * pudieron preparar.
     *
     * @return array<string, string>|null
     */
    public static function reducidas(FontMetrics $metricas): ?array
    {
        $dir = storage_path('app/fuentes-pdf/'.self::VERSION);
        if (array_key_exists($dir, self::$cache)) {
            return self::$cache[$dir];
        }

        try {
            $rutas = [];
            foreach (self::ESTILOS as $estilo => $variante) {
                $destino = $dir.'/DejaVuSans-'.$estilo;
                if (! is_file($destino.'.ttf') || ! is_file($destino.'.ufm')) {
                    $original = $metricas->getFont('DejaVu Sans', $variante);
                    if ($original === null || ! is_file($original.'.ttf')) {
                        return self::$cache[$dir] = null;
                    }
                    self::generar($original.'.ttf', $destino);
                }
                $rutas[$estilo] = $destino;
            }

            return self::$cache[$dir] = $rutas;
        } catch (Throwable $e) {
            Log::warning('PDF: no se pudieron preparar las fuentes reducidas, se usan las completas: '.$e->getMessage());

            return self::$cache[$dir] = null;
        }
    }

    /** Si todos los caracteres del texto estan en las fuentes reducidas. */
    public static function cubre(string $texto): bool
    {
        static $patron = null;
        if ($patron === null) {
            $clases = array_map(fn (array $r) => sprintf('\x{%04X}-\x{%04X}', $r[0], $r[1]), self::RANGOS);
            $patron = '/[^'.implode('', $clases).']/u';
        }

        return preg_match($patron, $texto) === 0;
    }

    private static function generar(string $ttf, string $destino): void
    {
        if (! is_dir(dirname($destino))) {
            mkdir(dirname($destino), 0775, true);
        }

        $caracteres = [];
        foreach (self::RANGOS as [$desde, $hasta]) {
            for ($c = $desde; $c <= $hasta; $c++) {
                $caracteres[] = $c;
            }
        }

        // Se escribe a un temporal y se renombra al final: dos peticiones a la vez no dejan un archivo a medias.
        $temporal = $destino.'.'.getmypid().'.'.mt_rand();

        $fuente = Font::load($ttf);
        $fuente->parse();
        $fuente->setSubset($caracteres);
        $fuente->reduce();
        touch($temporal.'.ttf'); // modeReadWrite abre con r+b: el archivo tiene que existir (igual que hace CPDF con tempnam)
        $fuente->open($temporal.'.ttf', BinaryStream::modeReadWrite);
        $fuente->encode(['OS/2']);
        $fuente->close();

        $reducida = Font::load($temporal.'.ttf');
        $reducida->parse();
        $reducida->saveAdobeFontMetrics($temporal.'.ufm');
        $reducida->close();

        rename($temporal.'.ttf', $destino.'.ttf');
        rename($temporal.'.ufm', $destino.'.ufm');
    }
}

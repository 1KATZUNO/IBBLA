<?php

namespace Tests\Unit;

use App\Support\Qr\MatrizQr;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * El QR de las tarjetas: se decodifica con un lector independiente (abajo, sin la libreria que lo genero) y se
 * comprueba la orientacion, el nivel de correccion, el margen blanco y que la cache devuelve la misma matriz.
 */
class MatrizQrTest extends TestCase
{
    private const URL = 'https://ibbla.eu/t/Ab3dEf6hIj9kLmN0pQrStUvWxYz1234567890abc';

    public function test_el_qr_se_decodifica_con_la_direccion_exacta(): void
    {
        $matriz = MatrizQr::de(self::URL);

        [$texto, $nivel] = self::decodificar($matriz);

        $this->assertSame(self::URL, $texto);
        $this->assertSame('M', $nivel, 'Nivel de correccion medio');
        $this->assertCount(33, $matriz, 'Version 4: 33 x 33 modulos');
    }

    public function test_los_tres_patrones_de_esquina_estan_arriba_y_abajo_a_la_izquierda(): void
    {
        $m = MatrizQr::de(self::URL);
        $n = count($m);
        foreach ([[0, 0], [0, $n - 7], [$n - 7, 0]] as [$f, $c]) {
            // Anillo exterior oscuro, anillo blanco y centro 3x3 oscuro
            $this->assertTrue($m[$f][$c] && $m[$f + 6][$c + 6] && $m[$f + 3][$c + 3] && $m[$f + 2][$c + 4]);
            $this->assertFalse($m[$f + 1][$c + 1], 'Anillo blanco del patron de esquina');
        }
    }

    public function test_el_svg_lleva_el_margen_blanco_y_un_solo_trazo(): void
    {
        $svg = MatrizQr::svg(self::URL, '#111827', 'QR de prueba');

        $this->assertStringContainsString('viewBox="0 0 41 41"', $svg, '33 modulos + 4 de margen a cada lado');
        $this->assertStringContainsString('shape-rendering="crispEdges"', $svg);
        $this->assertSame(1, substr_count($svg, '<path'));
        $this->assertStringContainsString('aria-label="QR de prueba"', $svg);
    }

    public function test_los_tramos_reconstruyen_la_matriz(): void
    {
        $m = MatrizQr::de(self::URL);
        $n = count($m);
        $rearmada = array_fill(0, $n, array_fill(0, $n, false));
        foreach (MatrizQr::tramos($m) as [$x, $y, $largo]) {
            for ($i = 0; $i < $largo; $i++) {
                $rearmada[$y][$x + $i] = true;
            }
        }

        $this->assertSame($m, $rearmada);
    }

    public function test_la_matriz_queda_en_cache_y_se_lee_igual(): void
    {
        $url = 'https://ibbla.eu/t/'.str_repeat('Z', 40);
        $calculada = MatrizQr::de($url);

        $this->assertIsString(Cache::get('qr:matriz:'.sha1($url)));

        // Otro proceso (sin la memoria de esta peticion) la toma de la cache
        $memoria = new \ReflectionProperty(MatrizQr::class, 'memoria');
        $memoria->setValue(null, []);
        $this->assertSame($calculada, MatrizQr::de($url));
    }

    // ------------------------------------------------------------------
    // Lector minimo de QR (modo byte, version 4, sin correccion de errores): formato, mascara, zigzag y bloques.
    // ------------------------------------------------------------------

    /** @return array{0: string, 1: string} texto y nivel de correccion */
    private static function decodificar(array $m): array
    {
        $n = count($m);
        $version = intdiv($n - 17, 4);
        self::assertSame(4, $version);

        // Informacion de formato (copia junto a la esquina de arriba a la izquierda), bit 0 primero
        $pos = [[0, 8], [1, 8], [2, 8], [3, 8], [4, 8], [5, 8], [7, 8], [8, 8], [8, 7], [8, 5], [8, 4], [8, 3], [8, 2], [8, 1], [8, 0]];
        $leido = 0;
        foreach ($pos as $k => [$f, $c]) {
            $leido |= ((int) $m[$f][$c]) << $k;
        }
        $datosFormato = null;
        for ($d = 0; $d < 32; $d++) {
            if (self::formato($d) === $leido) {
                $datosFormato = $d;
            }
        }
        self::assertNotNull($datosFormato, 'Informacion de formato valida');
        $nivel = [1 => 'L', 0 => 'M', 3 => 'Q', 2 => 'H'][$datosFormato >> 3];
        $mascara = $datosFormato & 7;

        $esFuncion = fn (int $f, int $c) => ($f < 9 && $c < 9) || ($f < 9 && $c >= $n - 8) || ($f >= $n - 8 && $c < 9)
            || $f === 6 || $c === 6 || ($f >= 24 && $f <= 28 && $c >= 24 && $c <= 28);
        $aplica = fn (int $i, int $j) => match ($mascara) {
            0 => ($i + $j) % 2 === 0,
            1 => $i % 2 === 0,
            2 => $j % 3 === 0,
            3 => ($i + $j) % 3 === 0,
            4 => (intdiv($i, 2) + intdiv($j, 3)) % 2 === 0,
            5 => ($i * $j) % 2 + ($i * $j) % 3 === 0,
            6 => (($i * $j) % 2 + ($i * $j) % 3) % 2 === 0,
            7 => (($i + $j) % 2 + ($i * $j) % 3) % 2 === 0,
        };

        $bits = '';
        for ($derecha = $n - 1; $derecha > 0; $derecha -= 2) {
            $col = $derecha <= 6 ? $derecha - 1 : $derecha;
            $arriba = (($col + 1) & 2) === 0;
            for ($v = 0; $v < $n; $v++) {
                $f = $arriba ? $n - 1 - $v : $v;
                for ($j = 0; $j < 2; $j++) {
                    $c = $col - $j;
                    if (! $esFuncion($f, $c)) {
                        $bits .= ((int) $m[$f][$c]) ^ ((int) $aplica($f, $c));
                    }
                }
            }
        }

        // Version 4: 100 palabras; nivel M = 2 bloques de 32 de datos, entrelazados
        $palabras = array_map(fn ($k) => bindec(substr($bits, $k * 8, 8)), range(0, 99));
        [$bloques, $porBloque] = ['L' => [1, 80], 'M' => [2, 32], 'Q' => [2, 24], 'H' => [4, 9]][$nivel];
        $datos = '';
        for ($b = 0; $b < $bloques; $b++) {
            for ($i = 0; $i < $porBloque; $i++) {
                $datos .= str_pad(decbin($palabras[$i * $bloques + $b]), 8, '0', STR_PAD_LEFT);
            }
        }

        self::assertSame('0100', substr($datos, 0, 4), 'Modo byte');
        $largo = bindec(substr($datos, 4, 8));
        $texto = '';
        for ($k = 0; $k < $largo; $k++) {
            $texto .= chr(bindec(substr($datos, 12 + 8 * $k, 8)));
        }

        return [$texto, $nivel];
    }

    private static function formato(int $datos): int
    {
        $resto = $datos;
        for ($i = 0; $i < 10; $i++) {
            $resto = ($resto << 1) ^ (($resto >> 9) * 0x537);
        }

        return (($datos << 10) | $resto) ^ 0x5412;
    }
}

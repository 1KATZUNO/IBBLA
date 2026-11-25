<?php

namespace App\Services\Promesas\Importacion;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Lee el Excel de promesas de un corte: primera hoja, fila 1 = titulos; columna A Miembro (o Nombre),
 * columna B Clase, columna C Frecuencia (opcional) y de ahi en adelante una columna por categoria.
 * Si C1 dice «Frecuencia», cada fila trae la suya (semanal, quincenal o mensual; vacia = la elegida al subir);
 * si no, el archivo se lee como siempre: las categorias empiezan en la C y vale la frecuencia del selector. No toca la base de datos: devuelve lo
 * leido y, si el archivo no sirve, la lista de errores para que la persona lo corrija y lo vuelva a subir.
 *
 * Resultado:
 *   categorias: list<array{col: string, titulo: string}>
 *   filas:      list<array{fila: int, nombre: string, clase: string, montos: array<string, float>, frecuencia: ?string, frecuencia_invalida: ?string}>
 *               (montos: col => monto > 0; frecuencia null = la del selector; frecuencia_invalida = texto que no se entendio)
 *   con_frecuencia: bool (el archivo trae la columna Frecuencia)
 *   errores:    list<string>
 */
class LectorExcelPromesas
{
    public const MAX_FILAS = 2000;

    /** @return array{categorias: list<array{col: string, titulo: string}>, filas: list<array<string, mixed>>, errores: list<string>} */
    public function leer(string $ruta): array
    {
        try {
            $lector = IOFactory::createReaderForFile($ruta);
            $lector->setReadDataOnly(true);
            $hoja = $lector->load($ruta)->getSheet(0);
        } catch (Throwable) {
            return $this->fallo('No se pudo abrir el archivo. Revisa que sea un Excel (.xlsx o .xls) y que no tenga contraseña.');
        }

        $ultimaFila = $hoja->getHighestDataRow();
        $ultimaCol = Coordinate::columnIndexFromString($hoja->getHighestDataColumn());
        $valor = fn (int $col, int $fila) => $hoja->getCell(Coordinate::stringFromColumnIndex($col).$fila)->getCalculatedValue();

        // Encabezado
        $tituloA = Normalizador::clave((string) $valor(1, 1));
        $tituloB = Normalizador::clave((string) $valor(2, 1));
        $errores = [];
        if (! in_array($tituloA, ['miembro', 'nombre'], true)) {
            $errores[] = 'La celda A1 debe decir «Miembro» o «Nombre». Usa la plantilla: la primera fila es para los títulos.';
        }
        if ($tituloB !== 'clase') {
            $errores[] = 'La celda B1 debe decir «Clase» (la columna puede ir vacía, pero el título tiene que estar).';
        }

        // Columna C «Frecuencia» (opcional): si esta, las categorias empiezan en la D
        $conFrecuencia = Normalizador::clave((string) $valor(3, 1)) === 'frecuencia';
        $primeraCategoria = $conFrecuencia ? 4 : 3;

        $categorias = [];
        $vistos = [];
        for ($c = $primeraCategoria; $c <= $ultimaCol; $c++) {
            $titulo = Normalizador::limpio((string) $valor($c, 1));
            $letra = Coordinate::stringFromColumnIndex($c);
            if ($titulo === '') {
                continue;
            }
            $clave = Normalizador::clave($titulo);
            if (isset($vistos[$clave])) {
                $errores[] = "La categoría «{$titulo}» está repetida (columnas {$vistos[$clave]} y {$letra}). Deja una sola columna por categoría.";

                continue;
            }
            $vistos[$clave] = $letra;
            $categorias[] = ['col' => $letra, 'titulo' => $titulo];
        }
        if ($categorias === [] && $errores === []) {
            $errores[] = 'No hay columnas de categorías: de la columna '.($conFrecuencia ? 'D' : 'C').' en adelante, pon en la fila 1 el nombre de cada categoría (Misiones, Construcción…).';
        }
        if ($errores !== []) {
            return $this->fallo(...$errores);
        }

        // Filas
        $filas = [];
        $malos = [];
        for ($f = 2; $f <= $ultimaFila; $f++) {
            $nombre = Normalizador::limpio((string) $valor(1, $f));
            $montos = [];
            $hayAlgo = $nombre !== '';
            foreach ($categorias as $cat) {
                $celda = $valor(Coordinate::columnIndexFromString($cat['col']), $f);
                $monto = Normalizador::monto($celda);
                if ($monto === false) {
                    $malos[] = "{$cat['col']}{$f} («".mb_substr((string) $celda, 0, 20).'»)';
                    $hayAlgo = true;
                } elseif ($monto !== null) {
                    $hayAlgo = true;
                    if ($monto > 0) {
                        $montos[$cat['col']] = $monto;
                    }
                }
            }
            if (! $hayAlgo) {
                continue; // fila en blanco
            }
            if ($nombre === '') {
                $errores[] = "La fila {$f} tiene montos pero no tiene nombre en la columna A.";

                continue;
            }
            $frecuencia = null;
            $invalida = null;
            if ($conFrecuencia) {
                $texto = Normalizador::limpio((string) $valor(3, $f));
                $frecuencia = Normalizador::frecuencia($texto);
                if ($texto !== '' && $frecuencia === null) {
                    $invalida = mb_substr($texto, 0, 30);
                }
            }
            $filas[] = [
                'fila' => $f,
                'nombre' => $nombre,
                'clase' => Normalizador::limpio((string) $valor(2, $f)),
                'montos' => $montos,
                'frecuencia' => $frecuencia,
                'frecuencia_invalida' => $invalida,
            ];
        }

        if ($malos !== []) {
            $errores[] = 'Hay montos que no son números en '.(count($malos) === 1 ? 'la celda ' : 'las celdas ')
                .implode(', ', array_slice($malos, 0, 10)).(count($malos) > 10 ? ' y '.(count($malos) - 10).' más' : '')
                .'. Escribe solo el número (por ejemplo 5000 o 2500.50), o deja la celda vacía si no hay promesa.';
        }
        if (count($filas) > self::MAX_FILAS) {
            $errores[] = 'El archivo trae '.count($filas).' personas y el máximo es '.self::MAX_FILAS.'. Divídelo en dos archivos.';
        }
        if ($filas === [] && $errores === []) {
            $errores[] = 'El archivo no trae personas: escribe una persona por fila desde la fila 2.';
        }

        return ['categorias' => $categorias, 'filas' => $errores === [] ? $filas : [], 'errores' => $errores, 'con_frecuencia' => $conFrecuencia];
    }

    /** @return array{categorias: list<array{col: string, titulo: string}>, filas: list<array<string, mixed>>, errores: list<string>} */
    private function fallo(string ...$errores): array
    {
        return ['categorias' => [], 'filas' => [], 'errores' => array_values($errores), 'con_frecuencia' => false];
    }
}

<?php

namespace App\Services\Promesas\Importacion;

use Illuminate\Support\Str;

/**
 * Forma comparable de un nombre (de persona, de categoria o de clase): sin tildes, en minusculas y con
 * un solo espacio entre palabras. "  José  María " y "jose maria" dan lo mismo.
 */
final class Normalizador
{
    public static function clave(?string $texto): string
    {
        $texto = Str::ascii((string) $texto);
        $texto = mb_strtolower($texto);
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '';

        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }

    /** Nombre limpio para guardar: espacios de mas fuera, sin cambiar tildes ni mayusculas. */
    public static function limpio(?string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $texto) ?? '');
    }

    /** «Semanal», « QUINCENAL », «mensual»… → semanal | quincenal | mensual; vacio u otro texto → null. */
    public static function frecuencia(?string $texto): ?string
    {
        $clave = self::clave($texto);

        return in_array($clave, ['semanal', 'quincenal', 'mensual'], true) ? $clave : null;
    }

    /**
     * Monto escrito en una celda: numero, vacio (null) o texto no valido (false).
     * Acepta "₡ 1,500.50", "1.500,50", "1500,5" y 230.769230769231 (se redondea a dos decimales).
     */
    public static function monto(mixed $valor): float|null|false
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_int($valor) || is_float($valor)) {
            return $valor < 0 ? false : round((float) $valor, 2);
        }

        $texto = str_replace(['₡', '$', ' ', "\u{00A0}", 'CRC', 'USD'], '', trim((string) $valor));
        if ($texto === '' || $texto === '-') {
            return null;
        }
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $texto)) {
            $texto = str_replace(',', '', $texto);              // 1,500.50
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $texto)) {
            $texto = str_replace(['.', ','], ['', '.'], $texto); // 1.500,50
        } elseif (preg_match('/^\d+,\d+$/', $texto)) {
            $texto = str_replace(',', '.', $texto);             // 1500,5
        }

        if (! is_numeric($texto) || (float) $texto < 0) {
            return false;
        }

        return round((float) $texto, 2);
    }
}

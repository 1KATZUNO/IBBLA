<?php

namespace App\Services\Analisis;

use Illuminate\Support\Carbon;

/**
 * Formato de números, fechas y celdas del informe en Markdown. Los montos van como en el resto de la
 * app (₡1,234.50 y $12.00) para que se puedan cotejar a simple vista con las pantallas y los PDF.
 */
final class Formato
{
    public static function crc(float|int|string|null $monto): string
    {
        $monto = round((float) $monto, 2);

        return ($monto < 0 ? '-' : '').'₡'.number_format(abs($monto), 2);
    }

    public static function usd(float|int|string|null $monto): string
    {
        $monto = round((float) $monto, 2);

        return ($monto < 0 ? '-' : '').'$'.number_format(abs($monto), 2);
    }

    /** Monto con el símbolo de su moneda (CRC por defecto). */
    public static function monto(float|int|string|null $monto, ?string $moneda): string
    {
        return $moneda === 'USD' ? self::usd($monto) : self::crc($monto);
    }

    /** Diferencia con signo explícito: +₡500.00 / -₡500.00. */
    public static function diferencia(float|int|string|null $monto, ?string $moneda = 'CRC'): string
    {
        $monto = round((float) $monto, 2);
        $texto = self::monto(abs($monto), $moneda);

        return ($monto > 0 ? '+' : ($monto < 0 ? '-' : '')).$texto;
    }

    public static function fecha(mixed $fecha): string
    {
        return $fecha === null || $fecha === '' ? '—' : Carbon::parse($fecha)->format('d/m/Y');
    }

    public static function porcentaje(float|int $parte, float|int $total): string
    {
        return $total > 0 ? number_format($parte * 100 / $total, 1).' %' : '—';
    }

    /** Una celda de tabla Markdown: sin barras verticales ni saltos de línea que la rompan. */
    public static function celda(mixed $valor): string
    {
        $texto = $valor === null || $valor === '' ? '—' : trim((string) $valor);

        return str_replace(['|', "\r\n", "\n", "\r"], ['\|', ' ', ' ', ' '], $texto);
    }

    /** Nombre en minúsculas y sin tildes ni espacios de más, para detectar personas repetidas. */
    public static function nombreNormalizado(?string $nombre): string
    {
        $limpio = mb_strtolower(trim((string) $nombre));
        $limpio = strtr($limpio, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return (string) preg_replace('/\s+/u', ' ', $limpio);
    }
}

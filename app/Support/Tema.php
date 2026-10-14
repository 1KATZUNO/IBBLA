<?php

namespace App\Support;

/**
 * Convierte los colores del tema de la iglesia (Tenant::colors, en hexadecimal) en variables CSS.
 *
 * El layout escribe --t-50 ... --t-900 y --t-50-rgb ... --t-900-rgb. Tailwind usa las variables
 * RGB para que funcione la opacidad (bg-brand-600/50). Ver tailwind.config.js y docs/UI.md.
 */
class Tema
{
    private const RESPALDO = '37 99 235';

    /** "#2563eb" o "#26e" -> "37 99 235". Si el valor no es un hexadecimal valido devuelve el azul por defecto. */
    public static function rgb(?string $hex): string
    {
        $hex = ltrim(trim((string) $hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return self::RESPALDO;
        }

        return hexdec(substr($hex, 0, 2)).' '.hexdec(substr($hex, 2, 2)).' '.hexdec(substr($hex, 4, 2));
    }

    /**
     * Bloque ":root { ... }" con la escala de la iglesia.
     *
     * @param  array<string, string>  $colores  claves '50'..'900'
     */
    public static function variablesCss(array $colores): string
    {
        $css = '';

        foreach ([50, 100, 200, 300, 400, 500, 600, 700, 800, 900] as $n) {
            $hex = (string) ($colores[(string) $n] ?? '');
            $limpio = preg_match('/^#[0-9a-fA-F]{3,6}$/', $hex) ? $hex : '#'.str_replace(' ', '', self::hexDesdeRgb(self::rgb($hex)));
            $css .= "--t-{$n}:{$limpio};--t-{$n}-rgb:".self::rgb($hex).';';
        }

        return ':root{'.$css.'}';
    }

    private static function hexDesdeRgb(string $rgb): string
    {
        return implode('', array_map(fn ($c) => str_pad(dechex((int) $c), 2, '0', STR_PAD_LEFT), explode(' ', $rgb)));
    }

    /** Iniciales para el avatar: "Paula Araya Zuniga" -> "PA"; "Administrador" -> "A". */
    public static function iniciales(?string $nombre): string
    {
        $palabras = preg_split('/\s+/u', trim((string) $nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($palabras === []) {
            return '?';
        }

        $letras = mb_substr($palabras[0], 0, 1);
        if (isset($palabras[1])) {
            $letras .= mb_substr($palabras[1], 0, 1);
        }

        return mb_strtoupper($letras, 'UTF-8');
    }
}

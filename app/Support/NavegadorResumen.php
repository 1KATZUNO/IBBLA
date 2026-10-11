<?php

namespace App\Support;

/**
 * Resume un User-Agent en algo legible para el historial de ingresos
 * ("Chrome 118 · Windows") sin depender de una librería externa.
 *
 * No pretende ser exhaustivo: cubre los navegadores y sistemas habituales, la app móvil
 * de la iglesia y las herramientas de línea de comandos (curl, Postman...), y todo lo
 * demás cae en "Otro navegador". El User-Agent completo se sigue guardando en
 * audit_logs.user_agent por si hace falta revisarlo.
 */
final class NavegadorResumen
{
    /**
     * @return array{navegador: string, sistema: string, etiqueta: string}
     */
    public static function resumir(?string $userAgent): array
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return ['navegador' => 'Desconocido', 'sistema' => '', 'etiqueta' => 'Desconocido'];
        }

        $navegador = self::navegador($ua);
        $sistema = self::sistema($ua);

        return [
            'navegador' => $navegador,
            'sistema' => $sistema,
            'etiqueta' => $sistema !== '' ? $navegador.' · '.$sistema : $navegador,
        ];
    }

    public static function etiqueta(?string $userAgent): string
    {
        return self::resumir($userAgent)['etiqueta'];
    }

    private static function navegador(string $ua): string
    {
        // La app móvil (React Native) se identifica sola; ver DetectMobileApp.
        if (str_contains($ua, 'IBBSCApp')) {
            return 'App móvil';
        }

        // Herramientas que no son navegadores: se muestran tal cual, sin pasarlas por Chrome.
        if (preg_match('/^(curl|Wget|python-requests|python-urllib|PostmanRuntime|insomnia|Go-http-client|axios|node-fetch|okhttp)[\/ ]?([\d.]*)/i', $ua, $m)) {
            return trim($m[1].' '.($m[2] !== '' ? strtok($m[2], '.') : ''));
        }

        // El orden importa: Edge, Opera y Samsung Internet también dicen "Chrome" y "Safari".
        $reglas = [
            ['/\b(?:Edg|EdgA|EdgiOS|Edge)\/(\d+)/', 'Edge'],
            ['/\b(?:OPR|Opera)\/(\d+)/', 'Opera'],
            ['/SamsungBrowser\/(\d+)/', 'Samsung Internet'],
            ['/\b(?:Firefox|FxiOS)\/(\d+)/', 'Firefox'],
            ['/\b(?:Chrome|CriOS)\/(\d+)/', 'Chrome'],
            ['/Version\/(\d+)[\d.]* (?:Mobile\/\S+ )?Safari\//', 'Safari'],
            ['/(?:MSIE |Trident\/.*rv:)(\d+)/', 'Internet Explorer'],
        ];

        foreach ($reglas as [$patron, $nombre]) {
            if (preg_match($patron, $ua, $m)) {
                return $nombre.' '.$m[1];
            }
        }

        if (preg_match('/bot|crawl|spider|slurp/i', $ua)) {
            return 'Robot';
        }

        return str_contains($ua, 'Safari/') ? 'Safari' : 'Otro navegador';
    }

    private static function sistema(string $ua): string
    {
        $reglas = [
            ['/Windows Phone/i', 'Windows Phone'],
            ['/Windows NT/i', 'Windows'],
            ['/Android/i', 'Android'],
            ['/iPhone|iPad|iPod/i', 'iOS'],
            ['/Mac OS X|Macintosh/i', 'macOS'],
            ['/CrOS/i', 'ChromeOS'],
            ['/Linux|X11/i', 'Linux'],
        ];

        foreach ($reglas as [$patron, $nombre]) {
            if (preg_match($patron, $ua)) {
                return $nombre;
            }
        }

        return '';
    }
}

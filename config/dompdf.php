<?php

/**
 * Configuración mínima de DomPDF para que el símbolo ₡ y otros caracteres
 * Unicode (acentos, ñ, emoji básico) se rendericen correctamente.
 *
 * Sin esto, DomPDF usa Helvetica como default y caracteres fuera de Latin-1
 * salen como '?'. DejaVu Sans viene incluida con la librería y soporta
 * todo el rango Unicode que usamos (₡, $, €, ñ, tildes).
 *
 * Las demás claves se dejan en su default (las llena el ServiceProvider).
 */

return [
    'show_warnings' => false,
    'public_path' => null,

    'defines' => [
        'font_dir' => storage_path('fonts'),
        'font_cache' => storage_path('fonts'),
        'temp_dir' => sys_get_temp_dir(),
        'chroot' => realpath(base_path()),
        'default_media_type' => 'screen',
        'default_paper_size' => 'a4',
        'default_paper_orientation' => 'portrait',

        // Lo importante: fuente Unicode por defecto
        'default_font' => 'DejaVu Sans',

        'dpi' => 96,
        'enable_php' => false,
        'enable_javascript' => true,
        'enable_remote' => true,
        'font_height_ratio' => 1.1,
        'enable_html5_parser' => true,
    ],
];

<?php

namespace App\Support\Manual;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Manual de usuario dentro de la app.
 *
 * El contenido vive en resources/views/manual/paginas/*.md (una pagina por modulo). Cada archivo
 * lleva un encabezado con sus datos y el cuerpo en Markdown:
 *
 *   ---
 *   titulo: Personas y compromisos
 *   orden: 40
 *   icono: personas            (nombre de App\Support\Iconos)
 *   resumen: Una linea que se ve en el indice y en el buscador.
 *   roles: admin               (todos, admin, tesorero, asistente, servidor, miembro, invitado)
 *   rutas: personas.*, compromisos.*   (pantallas cuyo boton "?" abre esta pagina; admite *)
 *   ancla: seccion-inicial     (opcional: el "?" abre directo esa seccion)
 *   palabras: miembros, hermanos       (sinonimos para el buscador)
 *   ---
 *
 * En el cuerpo: ![texto](img:archivo.jpg "pie de foto") toma la imagen de public/images/manual/
 * (si el archivo no existe la imagen se omite); [texto](route:personas.index) enlaza una pantalla.
 * Ver docs/UI.md para la plantilla recomendada.
 */
class Manual
{
    public const ROLES = [
        'todos' => 'Todos los roles',
        'admin' => 'Administrador',
        'tesorero' => 'Tesorero',
        'asistente' => 'Asistente',
        'servidor' => 'Servidor',
        'miembro' => 'Miembro',
        'invitado' => 'Invitado',
    ];

    private const PALABRAS_VACIAS = ['de', 'la', 'el', 'los', 'las', 'un', 'una', 'en', 'y', 'a', 'que', 'del', 'al', 'por', 'para', 'con', 'se', 'mi', 'es', 'lo', 'su'];

    /** @var list<Pagina>|null */
    private static ?array $paginas = null;

    /** @var array<string, list<array<string, mixed>>> */
    private static array $secciones = [];

    public static function directorio(): string
    {
        return resource_path('views/manual/paginas');
    }

    public static function directorioImagenes(): string
    {
        return public_path('images/manual');
    }

    /** Descarta lo leido en esta peticion (las pruebas cambian archivos). */
    public static function olvidar(): void
    {
        self::$paginas = null;
        self::$secciones = [];
    }

    /**
     * Todas las paginas, en orden.
     *
     * @return list<Pagina>
     */
    public static function paginas(): array
    {
        if (self::$paginas !== null) {
            return self::$paginas;
        }

        $paginas = [];
        foreach (glob(self::directorio().'/*.md') ?: [] as $archivo) {
            if (str_starts_with(basename($archivo), '_')) {
                continue;
            }
            if ($pagina = self::leer($archivo)) {
                $paginas[] = $pagina;
            }
        }

        usort($paginas, fn (Pagina $a, Pagina $b) => [$a->orden, $a->titulo] <=> [$b->orden, $b->titulo]);

        return self::$paginas = $paginas;
    }

    public static function pagina(string $slug): ?Pagina
    {
        foreach (self::paginas() as $pagina) {
            if ($pagina->slug === $slug) {
                return $pagina;
            }
        }

        return null;
    }

    /**
     * Pagina (y seccion) que explica una pantalla, segun las rutas declaradas en cada pagina.
     * Gana la declaracion mas especifica: un nombre exacto antes que un comodin, y el comodin mas largo.
     *
     * @return array{pagina: Pagina, ancla: ?string}|null
     */
    public static function paginaParaRuta(?string $nombreRuta): ?array
    {
        if (! $nombreRuta) {
            return null;
        }

        $mejor = null;
        $mejorPuntos = -1;

        foreach (self::paginas() as $pagina) {
            foreach ($pagina->rutas as $patron) {
                if (! Str::is($patron, $nombreRuta)) {
                    continue;
                }

                $puntos = strlen(str_replace('*', '', $patron)) + (str_contains($patron, '*') ? 0 : 1000);
                if ($puntos > $mejorPuntos) {
                    $mejorPuntos = $puntos;
                    $mejor = ['pagina' => $pagina, 'ancla' => $pagina->ancla];
                }
            }
        }

        return $mejor;
    }

    public static function url(Pagina $pagina, ?string $ancla = null): string
    {
        $url = route('manual.pagina', ['slug' => $pagina->slug], false);

        return $ancla ? $url.'#'.$ancla : $url;
    }

    /** URL del boton "?" de la pantalla actual, o null si ninguna pagina la cubre. */
    public static function enlaceAyuda(?string $nombreRuta): ?string
    {
        if (! Route::has('manual.pagina')) {
            return null;
        }

        $hallada = self::paginaParaRuta($nombreRuta);

        return $hallada ? self::url($hallada['pagina'], $hallada['ancla']) : null;
    }

    /**
     * Claves de rol que tiene el usuario (para marcar "para tu rol" en el manual).
     *
     * @return list<string>
     */
    public static function rolesDe(User $u): array
    {
        $roles = [];

        if ($u->canAccessAdmin()) {
            $roles[] = 'admin';
        }
        if ($u->canAccessRecuento()) {
            $roles[] = 'tesorero';
        }
        if ($u->canAccessAsistencia()) {
            $roles[] = 'asistente';
        }
        if ($u->hasPermission('marcar_asistencia')) {
            $roles[] = 'servidor';
        }
        if ($u->isMiembro() || $u->canAccessMiPerfil()) {
            $roles[] = 'miembro';
        }
        if ($u->isInvitado()) {
            $roles[] = 'invitado';
        }

        return $roles;
    }

    public static function esParaRol(Pagina $pagina, User $u): bool
    {
        if (in_array('todos', $pagina->roles, true) || $pagina->roles === []) {
            return true;
        }

        return array_intersect($pagina->roles, self::rolesDe($u)) !== [];
    }

    /** "admin, tesorero" -> "Administrador, Tesorero". */
    public static function etiquetaRoles(Pagina $pagina): string
    {
        if ($pagina->roles === [] || in_array('todos', $pagina->roles, true)) {
            return self::ROLES['todos'];
        }

        return implode(', ', array_map(fn ($r) => self::ROLES[$r] ?? ucfirst($r), $pagina->roles));
    }

    /**
     * HTML de una pagina y su lista de secciones (para el indice lateral).
     *
     * @return array{html: string, secciones: list<array{id: string, titulo: string, nivel: int}>}
     */
    public static function render(Pagina $pagina, bool $paraPdf = false): array
    {
        $markdown = self::preprocesar($pagina->cuerpo, $paraPdf);

        $entorno = new Environment(['html_input' => 'allow', 'allow_unsafe_links' => false]);
        $entorno->addExtension(new CommonMarkCoreExtension);
        $entorno->addExtension(new GithubFlavoredMarkdownExtension);
        $html = (string) (new MarkdownConverter($entorno))->convert($markdown);

        $secciones = [];
        $usados = [];

        // Identificadores de secciones (sin tildes, sin repetir) y enlace "#" al lado del titulo
        $html = preg_replace_callback('#<h([2-3])>(.*?)</h\1>#s', function (array $m) use (&$secciones, &$usados, $paraPdf) {
            $titulo = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $base = Str::slug($titulo) ?: 'seccion';
            $id = $base;
            for ($i = 2; isset($usados[$id]); $i++) {
                $id = $base.'-'.$i;
            }
            $usados[$id] = true;
            $secciones[] = ['id' => $id, 'titulo' => $titulo, 'nivel' => (int) $m[1]];

            $ancla = $paraPdf ? '' : ' <a href="#'.$id.'" class="manual-anchor" aria-label="Enlace a esta seccion">#</a>';

            return '<h'.$m[1].' id="'.$id.'">'.$m[2].$ancla.'</h'.$m[1].'>';
        }, $html) ?? $html;

        // Capturas: un parrafo con solo una imagen se vuelve figura con pie de foto
        $html = preg_replace_callback('#<p>\s*(<img\b[^>]*>)\s*</p>#', function (array $m) use ($paraPdf) {
            $img = $m[1];
            $pie = '';
            if (preg_match('/\btitle="([^"]*)"/', $img, $t) && $t[1] !== '') {
                $pie = $t[1];
            } elseif (preg_match('/\balt="([^"]*)"/', $img, $a)) {
                $pie = $a[1];
            }
            if (! $paraPdf) {
                $img = preg_replace('#<img\b#', '<img loading="lazy"', $img, 1) ?? $img;
            }

            return '<figure>'.$img.($pie !== '' ? '<figcaption>'.$pie.'</figcaption>' : '').'</figure>';
        }, $html) ?? $html;

        // Tablas con desplazamiento propio (nunca ensanchan la pagina)
        $html = str_replace(['<table>', '</table>'], ['<div class="manual-table-wrap"><table>', '</table></div>'], $html);

        // Avisos: > **Cuidado:** ...  /  > **Consejo:** ...
        $html = preg_replace('#<blockquote>(\s*<p><strong>(?:Cuidado|Importante|Atenci(?:ó|&oacute;)n|Ojo)\b)#u', '<blockquote class="manual-cuidado">$1', $html) ?? $html;
        $html = preg_replace('#<blockquote>(\s*<p><strong>(?:Consejo|Truco|Sugerencia)\b)#u', '<blockquote class="manual-consejo">$1', $html) ?? $html;

        if ($paraPdf) {
            // DomPDF necesita la ruta del archivo, no la URL
            $html = preg_replace_callback('#src="/images/manual/([^"]+)"#', function (array $m) {
                return 'src="'.str_replace('\\', '/', self::directorioImagenes().'/'.$m[1]).'"';
            }, $html) ?? $html;
        }

        return ['html' => $html, 'secciones' => $secciones];
    }

    /**
     * Busca por palabras en todo el manual (titulos, secciones y sinonimos). Todas las palabras deben aparecer.
     *
     * @return list<array{tipo: string, titulo: string, detalle: string, url: string, icono: string, puntos: int}>
     */
    public static function buscar(string $consulta, int $limite = 8): array
    {
        $tokens = self::tokens($consulta);
        if ($tokens === []) {
            return [];
        }

        $hallazgos = [];

        foreach (self::paginas() as $pagina) {
            $meta = self::normalizar($pagina->titulo.' '.$pagina->resumen.' '.implode(' ', $pagina->palabras));
            $tituloPagina = self::normalizar($pagina->titulo);

            // La pagina completa: todas las palabras estan en su titulo, resumen o sinonimos
            $puntos = 0;
            $todas = true;
            foreach ($tokens as $t) {
                if (self::contiene($tituloPagina, $t)) {
                    $puntos += 10;
                } elseif (self::contiene($meta, $t)) {
                    $puntos += 5;
                } else {
                    $todas = false;
                    break;
                }
            }
            if ($todas) {
                $hallazgos[] = [
                    'tipo' => 'pagina',
                    'titulo' => $pagina->titulo,
                    'detalle' => $pagina->resumen,
                    'url' => self::url($pagina),
                    'icono' => $pagina->icono,
                    'puntos' => $puntos + 20,
                ];
            }

            foreach (self::seccionesDe($pagina) as $sec) {
                $puntos = 0;
                $todas = true;
                foreach ($tokens as $t) {
                    $enTitulo = self::contiene($sec['titulo_norm'], $t);
                    $enTexto = self::contiene($sec['texto_norm'], $t);
                    if (! $enTitulo && ! $enTexto) {
                        $todas = false;
                        break;
                    }
                    $puntos += ($enTitulo ? 8 : 0) + ($enTexto ? 2 : 0) + (self::contiene($meta, $t) ? 1 : 0);
                }
                if (! $todas) {
                    continue;
                }

                $hallazgos[] = [
                    'tipo' => 'seccion',
                    'titulo' => $sec['titulo'] !== '' ? $pagina->titulo.' › '.$sec['titulo'] : $pagina->titulo,
                    'detalle' => self::extracto($sec['frases'], $tokens) ?: $pagina->resumen,
                    'url' => self::url($pagina, $sec['id']),
                    'icono' => $pagina->icono,
                    'puntos' => $puntos,
                ];
            }
        }

        usort($hallazgos, fn ($a, $b) => $b['puntos'] <=> $a['puntos']);

        // Una pagina y sus secciones no deben llenar toda la lista: maximo 3 resultados por pagina
        $porPagina = [];
        $salida = [];
        foreach ($hallazgos as $h) {
            $clave = strtok($h['url'], '#');
            $porPagina[$clave] = ($porPagina[$clave] ?? 0) + 1;
            if ($porPagina[$clave] <= 3) {
                $salida[] = $h;
            }
            if (count($salida) >= $limite) {
                break;
            }
        }

        return $salida;
    }

    /** Minusculas y sin tildes: "Cuántos Sobres" -> "cuantos sobres". */
    public static function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii($texto));
    }

    /**
     * Palabras de una busqueda, normalizadas y sin palabras vacias ("de", "la"...).
     *
     * @return list<string>
     */
    public static function tokens(string $consulta): array
    {
        $palabras = preg_split('/[^a-z0-9]+/', self::normalizar($consulta), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $palabras = array_values(array_unique(array_filter($palabras, fn ($p) => strlen($p) >= 2)));
        $utiles = array_values(array_filter($palabras, fn ($p) => ! in_array($p, self::PALABRAS_VACIAS, true)));

        return $utiles !== [] ? $utiles : $palabras;
    }

    /** ¿El texto normalizado contiene la palabra (o su singular: "sobres" encuentra "sobre")? */
    public static function contiene(string $textoNormalizado, string $token): bool
    {
        if (str_contains($textoNormalizado, $token)) {
            return true;
        }

        if (strlen($token) > 4 && str_ends_with($token, 'es') && str_contains($textoNormalizado, substr($token, 0, -2))) {
            return true;
        }

        return strlen($token) > 3 && str_ends_with($token, 's') && str_contains($textoNormalizado, substr($token, 0, -1));
    }

    // ------------------------------------------------------------------------------------------

    private static function leer(string $archivo): ?Pagina
    {
        $texto = file_get_contents($archivo);
        if ($texto === false) {
            return null;
        }

        $texto = str_replace(["\r\n", "\r"], "\n", ltrim($texto, "\xEF\xBB\xBF"));
        $meta = [];
        $cuerpo = $texto;

        if (preg_match('/\A---\n(.*?)\n---[ \t]*(?:\n(.*))?\z/s', $texto, $m)) {
            foreach (explode("\n", $m[1]) as $linea) {
                if (preg_match('/^([A-Za-z_]+)\s*:\s*(.*)$/', trim($linea), $kv)) {
                    $meta[strtolower($kv[1])] = trim($kv[2], " \t\"'");
                }
            }
            $cuerpo = $m[2] ?? '';
        }

        $nombre = pathinfo($archivo, PATHINFO_FILENAME);
        $prefijo = preg_match('/^(\d+)-/', $nombre, $n) ? (int) $n[1] : 999;
        $slug = preg_replace('/^\d+-/', '', $nombre);
        $lista = fn (?string $v) => array_values(array_filter(array_map('trim', explode(',', (string) $v)), fn ($x) => $x !== ''));

        return new Pagina(
            slug: $slug,
            titulo: $meta['titulo'] ?? Str::headline($slug),
            orden: isset($meta['orden']) ? (int) $meta['orden'] : $prefijo,
            icono: $meta['icono'] ?? 'libro',
            resumen: $meta['resumen'] ?? '',
            roles: array_map('strtolower', $lista($meta['roles'] ?? 'todos')),
            rutas: $lista($meta['rutas'] ?? ''),
            palabras: $lista($meta['palabras'] ?? ''),
            ancla: ($meta['ancla'] ?? '') !== '' ? $meta['ancla'] : null,
            archivo: $archivo,
            cuerpo: $cuerpo,
        );
    }

    /** Sustituye imagenes y enlaces abreviados del Markdown. */
    private static function preprocesar(string $markdown, bool $paraPdf): string
    {
        // ![texto](img:archivo.jpg "pie") -> imagen de public/images/manual (o nada si el archivo no existe)
        $markdown = preg_replace_callback('/!\[([^\]]*)\]\(img:([^\s)"]+)((?:\s+"[^"]*")?)\)/', function (array $m) {
            $ruta = self::directorioImagenes().'/'.$m[2];
            if (! is_file($ruta)) {
                return '';
            }

            return '!['.$m[1].'](/images/manual/'.$m[2].$m[3].')';
        }, $markdown) ?? $markdown;

        // [texto](route:nombre) -> enlace a la pantalla (si la ruta no existe queda como texto)
        return preg_replace_callback('/\[([^\]]+)\]\(route:([A-Za-z0-9_.\-]+)\)/', function (array $m) use ($paraPdf) {
            if (! Route::has($m[2])) {
                return $m[1];
            }

            return '['.$m[1].']('.route($m[2], [], $paraPdf).')';
        }, $markdown) ?? $markdown;
    }

    /**
     * Secciones de una pagina listas para buscar (introduccion + cada "##").
     *
     * @return list<array{id: ?string, titulo: string, titulo_norm: string, texto_norm: string, frases: list<string>}>
     */
    private static function seccionesDe(Pagina $pagina): array
    {
        if (isset(self::$secciones[$pagina->slug])) {
            return self::$secciones[$pagina->slug];
        }

        $trozos = [];
        $actual = ['titulo' => '', 'lineas' => []];
        $dentroCodigo = false;
        $usados = [];

        foreach (explode("\n", $pagina->cuerpo) as $linea) {
            if (str_starts_with(ltrim($linea), '```')) {
                $dentroCodigo = ! $dentroCodigo;
            }
            if (! $dentroCodigo && preg_match('/^(#{2,3})\s+(.+?)\s*#*\s*$/', $linea, $h)) {
                $trozos[] = $actual;
                $actual = ['titulo' => trim($h[2]), 'lineas' => []];

                continue;
            }
            $actual['lineas'][] = $linea;
        }
        $trozos[] = $actual;

        $salida = [];
        foreach ($trozos as $t) {
            $titulo = self::textoPlano($t['titulo']);
            $plano = self::textoPlano(implode("\n", $t['lineas']));
            if ($titulo === '' && trim($plano) === '') {
                continue;
            }

            // Mismo identificador que genera render()
            $id = null;
            if ($titulo !== '') {
                $base = Str::slug($titulo) ?: 'seccion';
                $id = $base;
                for ($i = 2; isset($usados[$id]); $i++) {
                    $id = $base.'-'.$i;
                }
                $usados[$id] = true;
            }

            $frases = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+|\n+/u', $plano) ?: []), fn ($f) => $f !== ''));
            $salida[] = [
                'id' => $id,
                'titulo' => $titulo,
                'titulo_norm' => self::normalizar($titulo),
                'texto_norm' => self::normalizar($plano),
                'frases' => $frases,
            ];
        }

        return self::$secciones[$pagina->slug] = $salida;
    }

    /** Quita la sintaxis de Markdown y de HTML para buscar y mostrar extractos. */
    private static function textoPlano(string $markdown): string
    {
        $t = preg_replace('/```.*?```/s', ' ', $markdown) ?? $markdown;
        $t = preg_replace('/!\[([^\]]*)\]\([^)]*\)/', '', $t) ?? $t;
        $t = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $t) ?? $t;
        $t = preg_replace('/<[^>]+>/', ' ', $t) ?? $t;
        $t = preg_replace('/^\s*(?:[>#\-*+]+|\d+\.)\s+/m', '', $t) ?? $t;
        $t = preg_replace('/^\s*\|?[\s:\-|]+\|?\s*$/m', ' ', $t) ?? $t;
        $t = str_replace(['|', '**', '__', '`', '*'], ' ', $t);

        return trim(preg_replace('/[ \t]+/', ' ', $t) ?? $t);
    }

    /**
     * La frase que mejor muestra la busqueda (la primera con todas las palabras; si no, la primera con alguna).
     *
     * @param  list<string>  $frases
     * @param  list<string>  $tokens
     */
    private static function extracto(array $frases, array $tokens): string
    {
        $primera = null;
        foreach ($frases as $frase) {
            $n = self::normalizar($frase);
            $coinciden = count(array_filter($tokens, fn ($t) => self::contiene($n, $t)));
            if ($coinciden === count($tokens)) {
                return Str::limit($frase, 150);
            }
            if ($coinciden > 0 && $primera === null) {
                $primera = $frase;
            }
        }

        return $primera !== null ? Str::limit($primera, 150) : '';
    }
}

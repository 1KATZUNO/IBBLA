<?php

namespace App\Services\WhatsApp;

/**
 * Lo que la persona pega en su perfil: el enlace que le responde el bot de CallMeBot al activarlo,
 *
 *   https://api.callmebot.com/whatsapp.php?phone=+50688887777&text=This+is+a+test&apikey=1234567
 *
 * De ahí se toman el número (phone) y la clave (apikey); el texto de ejemplo se ignora. También se
 * acepta el mensaje completo del bot (trae el enlace entre otras líneas) y, si no hay ningún enlace,
 * la frase «Your APIKEY is 1234567», en cuyo caso no hay número que comparar.
 *
 * Nunca se llama a la dirección pegada: solo se leen sus datos. Y solo vale si es de callmebot.com,
 * para que nadie pegue el enlace de otro sitio.
 */
final class EnlaceCallMeBot
{
    /** Clave que se acepta: letras, números, guion y guion bajo (las de CallMeBot son numéricas). */
    private const PATRON_CLAVE = '/^[A-Za-z0-9_-]{3,64}$/';

    public const VACIO = 'vacio';

    public const SIN_ENLACE = 'sin_enlace';

    public const OTRO_SITIO = 'otro_sitio';

    public const SIN_CLAVE = 'sin_clave';

    /**
     * @param  ?string  $telefono  número del enlace normalizado (E.164 sin «+»); null si el enlace no trae phone= o no sirve
     * @param  bool  $traeTelefono  el enlace traía phone= (aunque el número no sirviera)
     */
    private function __construct(
        public readonly string $apikey,
        public readonly ?string $telefono,
        public readonly bool $traeTelefono,
    ) {
    }

    /** Los datos del enlace, o null si lo pegado no es un enlace utilizable de CallMeBot (ver diagnostico()). */
    public static function analizar(?string $texto): ?self
    {
        $texto = self::limpiar($texto);

        if ($texto === '') {
            return null;
        }

        $urls = self::urls($texto);

        foreach ($urls as $url) {
            $partes = parse_url($url);
            $host = strtolower((string) ($partes['host'] ?? ''));

            if (! self::esDeCallMeBot($host)) {
                continue;
            }

            parse_str((string) ($partes['query'] ?? ''), $consulta);
            $clave = trim((string) ($consulta['apikey'] ?? ''));

            if (! preg_match(self::PATRON_CLAVE, $clave)) {
                continue;
            }

            // CallMeBot manda el «+» del teléfono tal cual y parse_str lo vuelve un espacio inicial.
            $crudo = (string) ($consulta['phone'] ?? '');
            $crudo = str_starts_with($crudo, ' ') ? '+'.ltrim($crudo) : trim($crudo);

            return new self($clave, $crudo === '' ? null : WhatsAppService::normalizarTelefono($crudo), $crudo !== '');
        }

        // Sin ningún enlace: la frase del bot («Your APIKEY is 1234567»). Si pegó un enlace de otro
        // sitio no se rebusca en él: sería aceptar cualquier cosa que diga apikey=.
        if (! str_contains($texto, '://') && preg_match('/api\s*key\s*(?:is|es|:)\s*([A-Za-z0-9_-]{3,64})/i', $texto, $m)) {
            return new self($m[1], null, false);
        }

        return null;
    }

    /** Por qué no sirve lo pegado, como código para armar el mensaje a la persona. */
    public static function diagnostico(?string $texto): string
    {
        $texto = self::limpiar($texto);

        if ($texto === '') {
            return self::VACIO;
        }

        $urls = self::urls($texto);

        if ($urls === []) {
            return str_contains($texto, '://') ? self::OTRO_SITIO : self::SIN_ENLACE;
        }

        foreach ($urls as $url) {
            if (self::esDeCallMeBot(strtolower((string) parse_url($url, PHP_URL_HOST)))) {
                return self::SIN_CLAVE;
            }
        }

        return self::OTRO_SITIO;
    }

    /** Texto limpio: sin espacios de más ni caracteres invisibles, y con los &amp; de HTML vueltos &. */
    private static function limpiar(?string $texto): string
    {
        $texto = (string) preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', (string) $texto);

        return trim(html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Las direcciones http(s) del texto, sin la puntuación que se pega al final al copiar un mensaje.
     *
     * @return list<string>
     */
    private static function urls(string $texto): array
    {
        preg_match_all('~https?://[^\s<>"\x{00AB}\x{00BB}]+~iu', $texto, $coincidencias);

        return array_values(array_filter(array_map(
            fn (string $url) => rtrim($url, ".,;:!?)]}*~'\""),
            $coincidencias[0],
        )));
    }

    /** callmebot.com o un subdominio suyo; «evilcallmebot.com» o «callmebot.com.malo.net» no cuentan. */
    private static function esDeCallMeBot(string $host): bool
    {
        return $host === 'callmebot.com' || str_ends_with($host, '.callmebot.com');
    }
}

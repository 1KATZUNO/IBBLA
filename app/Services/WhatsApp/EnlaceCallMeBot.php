<?php

namespace App\Services\WhatsApp;

/**
 * Lo que la persona pega en su perfil: el enlace que le responde el bot de CallMeBot al activarlo,
 *
 *   https://api.callmebot.com/whatsapp.php?phone=+50688887777&text=This+is+a+test&apikey=1234567
 *
 * De ahí se toman el número (phone) y la clave (apikey); el texto de ejemplo se ignora. Se acepta lo que
 * el bot contesta en cualquiera de sus dos formas (la primera vez «API Activated for your phone number.
 * Your APIKEY is 1234567» y, si el número ya estaba activado, «You already have an apikey. Your apikey is
 * 1234567 You can send a test messages using the url https://api.callmebot.com/whatsapp.php?phone=506...»),
 * el enlace solo, la frase «Your APIKEY is 1234567» o la clave sola. Sin enlace no hay número que comparar.
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

    /** Pegó solo un número que es un teléfono (8 dígitos): la clave no es el teléfono. */
    public const PARECE_TELEFONO = 'parece_telefono';

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

        // Sin ninguna dirección: la frase del bot («Your APIKEY is 1234567») o la clave sola. Si pegó un
        // enlace de otro sitio no se rebusca en él: sería aceptar cualquier cosa que diga apikey=.
        if (! str_contains($texto, '://')) {
            if (preg_match('/api\s*key\s*(?:is|es|:)\s*([A-Za-z0-9_-]{3,64})/i', $texto, $m)) {
                return new self($m[1], null, false);
            }

            if (preg_match(self::PATRON_CLAVE, $texto) && ! self::pareceTelefono($texto)) {
                return new self($texto, null, false);
            }
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
            if (str_contains($texto, '://')) {
                return self::OTRO_SITIO;
            }

            return self::pareceTelefono($texto) ? self::PARECE_TELEFONO : self::SIN_ENLACE;
        }

        foreach ($urls as $url) {
            if (self::esDeCallMeBot(strtolower((string) parse_url($url, PHP_URL_HOST)))) {
                return self::SIN_CLAVE;
            }
        }

        return self::OTRO_SITIO;
    }

    /**
     * ¿Es un número suelto que se lee como teléfono (8 dígitos de Costa Rica, o 506 + 8 dígitos)? Las claves
     * de CallMeBot son de 6 o 7 cifras: un 8888-7777 pegado solo es el teléfono, no la clave.
     */
    private static function pareceTelefono(string $texto): bool
    {
        return preg_match('/^[\d\s().+-]+$/', $texto) === 1 && WhatsAppService::normalizarTelefono($texto) !== null;
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

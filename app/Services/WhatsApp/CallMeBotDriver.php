<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Envío por CallMeBot (https://www.callmebot.com/blog/free-api-whatsapp-messages/): servicio gratuito
 * de un tercero, sin garantía, que solo escribe al número que activó el bot con su propia clave.
 *
 *   GET https://api.callmebot.com/whatsapp.php?phone=+50688887777&text=...&apikey=123456
 *
 * Dos particularidades que vienen de ARCA, donde ya se usa en producción:
 *  - CallMeBot responde HTTP 200 aunque falle («APIKey is invalid...»). El único indicador de éxito
 *    es «Message queued» en el cuerpo, y se busca en positivo porque el cuerpo repite el texto enviado.
 *  - Los errores de conexión de cURL traen la URL completa, es decir, la clave. Nada de lo que sale de
 *    aquí (excepciones, logs, mensajes en pantalla) lleva la clave ni el texto del mensaje, que puede
 *    contener un enlace firmado al informe de la persona.
 */
class CallMeBotDriver implements WhatsAppDriverConClave
{
    private const URL_POR_DEFECTO = 'https://api.callmebot.com/whatsapp.php';

    /**
     * @param  array{url?: ?string, connect_timeout?: int, timeout?: int}  $config
     */
    public function __construct(private array $config = [])
    {
    }

    public function nombre(): string
    {
        return 'callmebot';
    }

    /** La clave es de cada receptor: sin saber a quién se le escribe no se puede enviar «a secas». */
    public function puedeEnviar(): bool
    {
        return false;
    }

    /** El enlace wa.me sigue siendo el respaldo manual para quien no activó el bot. */
    public function enlace(string $telefono, string $mensaje): string
    {
        return LinkDriver::construirUrl($telefono, $mensaje);
    }

    public function enviar(string $telefono, string $mensaje): void
    {
        throw WhatsAppNoConfiguradoException::callmebotSinClave();
    }

    public function enviarConClave(string $telefono, string $mensaje, string $apikey): void
    {
        $apikey = trim($apikey);

        if ($apikey === '' || trim($telefono) === '' || trim($mensaje) === '') {
            throw WhatsAppNoConfiguradoException::callmebotSinClave();
        }

        try {
            $respuesta = Http::connectTimeout(max(1, (int) ($this->config['connect_timeout'] ?? 3)))
                ->timeout(max(1, (int) ($this->config['timeout'] ?? 10)))
                ->get($this->url(), [
                    'phone' => '+'.ltrim($telefono, '+'),
                    'text' => $mensaje,
                    'apikey' => $apikey,
                ]);
        } catch (ConnectionException $e) {
            // Sin encadenar $e: su mensaje (y el de su traza) repite la URL con la clave.
            throw new WhatsAppEnvioException(
                'No se pudo contactar a CallMeBot: '.self::limpiar($e->getMessage(), $apikey, $mensaje)
            );
        }

        // Se busca en positivo: CallMeBot contesta 200 con el motivo del fallo en el cuerpo.
        if (! $respuesta->successful() || stripos((string) $respuesta->body(), 'message queued') === false) {
            throw new WhatsAppEnvioException($this->describirError($respuesta, $apikey, $mensaje));
        }
    }

    private function url(): string
    {
        return (string) ($this->config['url'] ?? '') ?: self::URL_POR_DEFECTO;
    }

    /** Lo que CallMeBot contestó, sin la clave, sin el mensaje enviado y sin enlaces: cabe en un aviso en pantalla. */
    private function describirError(Response $respuesta, string $apikey, string $mensaje): string
    {
        $detalle = self::limpiar(trim(strip_tags((string) $respuesta->body())), $apikey, $mensaje);

        return 'CallMeBot respondió '.$respuesta->status().($detalle !== '' ? ': '.$detalle : ' sin detalle').'.';
    }

    /**
     * Quita del texto la clave, el mensaje enviado (tal cual y codificado) y cualquier enlace,
     * junta los espacios y lo corta: es lo que acaba en logs, en la base y en pantalla.
     */
    private static function limpiar(string $texto, string $apikey, string $mensaje): string
    {
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach (array_filter([$apikey, $mensaje, rawurlencode($mensaje), urlencode($mensaje)]) as $secreto) {
            $texto = str_replace($secreto, $secreto === $apikey ? '***' : '[mensaje]', $texto);
        }

        $texto = (string) preg_replace('~https?://\S+~i', '[enlace]', $texto);
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));

        return mb_strlen($texto) > 200 ? rtrim(mb_substr($texto, 0, 200)).'…' : $texto;
    }
}

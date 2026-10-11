<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Envío real por la WhatsApp Cloud API de Meta (POST /{version}/{phone_number_id}/messages).
 *
 * Hoy NO se usa: la iglesia no pudo verificar su cuenta de Facebook y por eso el driver
 * por defecto es «link». Queda listo para cuando haya credenciales: con
 * WHATSAPP_DRIVER=meta, WHATSAPP_META_TOKEN y WHATSAPP_META_PHONE_NUMBER_ID el resto
 * del sistema (avisos y botón de informe) pasa a enviar solo. Si faltan las
 * credenciales, enviar() lanza una excepción clara en lugar de fallar en silencio.
 */
class MetaCloudDriver implements WhatsAppDriver
{
    /**
     * @param  array{token?: ?string, phone_number_id?: ?string, version?: ?string, base_url?: ?string, plantilla?: ?string, idioma_plantilla?: ?string, timeout?: int}  $config
     */
    public function __construct(private array $config = [])
    {
    }

    public function nombre(): string
    {
        return 'meta';
    }

    public function puedeEnviar(): bool
    {
        return $this->token() !== '' && $this->phoneNumberId() !== '';
    }

    /** El enlace wa.me sigue sirviendo de respaldo manual (p. ej. un aviso que la API no pudo entregar). */
    public function enlace(string $telefono, string $mensaje): string
    {
        return LinkDriver::construirUrl($telefono, $mensaje);
    }

    public function enviar(string $telefono, string $mensaje): void
    {
        if (! $this->puedeEnviar()) {
            throw WhatsAppNoConfiguradoException::meta();
        }

        try {
            $respuesta = Http::withToken($this->token())
                ->acceptJson()
                ->timeout((int) ($this->config['timeout'] ?? 15))
                ->post($this->urlMensajes(), $this->cuerpo($telefono, $mensaje));
        } catch (ConnectionException $e) {
            throw new WhatsAppEnvioException('No se pudo contactar la Cloud API de WhatsApp: '.$e->getMessage(), 0, $e);
        }

        if ($respuesta->failed()) {
            throw new WhatsAppEnvioException($this->describirError($respuesta));
        }
    }

    private function urlMensajes(): string
    {
        $base = rtrim((string) ($this->config['base_url'] ?? 'https://graph.facebook.com'), '/');
        $version = trim((string) ($this->config['version'] ?? 'v21.0'), '/');

        return "{$base}/{$version}/{$this->phoneNumberId()}/messages";
    }

    /**
     * Texto libre, o plantilla aprobada si hay una configurada: fuera de la ventana de
     * 24 horas Meta solo entrega plantillas. El mensaje completo viaja como {{1}}.
     */
    private function cuerpo(string $telefono, string $mensaje): array
    {
        $base = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $telefono,
        ];

        $plantilla = trim((string) ($this->config['plantilla'] ?? ''));

        if ($plantilla === '') {
            return $base + [
                'type' => 'text',
                'text' => ['preview_url' => true, 'body' => $mensaje],
            ];
        }

        return $base + [
            'type' => 'template',
            'template' => [
                'name' => $plantilla,
                'language' => ['code' => (string) ($this->config['idioma_plantilla'] ?? 'es')],
                'components' => [[
                    'type' => 'body',
                    // Los parámetros de plantilla no admiten saltos de línea ni tabulaciones.
                    'parameters' => [['type' => 'text', 'text' => trim((string) preg_replace('/\s+/u', ' ', $mensaje))]],
                ]],
            ],
        ];
    }

    /** Mensaje de error de Meta sin datos sensibles (el token viaja en la cabecera, no aquí). */
    private function describirError(Response $respuesta): string
    {
        $error = $respuesta->json('error');
        $detalle = is_array($error) ? ($error['message'] ?? 'sin detalle') : 'sin detalle';
        $codigo = is_array($error) && isset($error['code']) ? " (código {$error['code']})" : '';

        return "La Cloud API de WhatsApp respondió {$respuesta->status()}: {$detalle}{$codigo}";
    }

    private function token(): string
    {
        return trim((string) ($this->config['token'] ?? ''));
    }

    private function phoneNumberId(): string
    {
        return trim((string) ($this->config['phone_number_id'] ?? ''));
    }
}

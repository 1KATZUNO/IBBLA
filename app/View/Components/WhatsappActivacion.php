<?php

namespace App\View\Components;

use App\Models\ActivacionWhatsApp;
use App\Models\Persona;
use App\Models\User;
use App\Services\WhatsApp\ActivacionWhatsAppService;
use App\Services\WhatsApp\LinkDriver;
use App\Services\WhatsApp\MensajesWhatsApp;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Tarjeta «Avisos por WhatsApp» (CallMeBot): los tres pasos con imágenes, el campo donde se pega el
 * enlace que responde el bot, el consentimiento, la prueba y desactivar.
 *
 *     <x-whatsapp-activacion :receptor="$persona" tipo="persona" />   (Mi perfil)
 *     <x-whatsapp-activacion :receptor="$user" tipo="usuario" />      (perfil de un usuario del sistema)
 *
 * Solo se dibuja con WHATSAPP_DRIVER=callmebot: con los otros drivers no hay nada que activar.
 * La clave nunca llega a la vista: solo se dice si hay una guardada.
 */
class WhatsappActivacion extends Component
{
    public bool $visible;

    public string $situacion = ActivacionWhatsAppService::SIN_ACTIVAR;

    public ?ActivacionWhatsApp $activacion = null;

    /** Teléfono registrado hoy, normalizado; null si no hay uno que sirva. */
    public ?string $telefono = null;

    public string $telefonoFormateado = '';

    public string $consentimiento = '';

    public string $botNumero;

    public string $botFrase;

    public string $botEnlace;

    public string $ayudaUrl;

    /** @var array{guardar: string, probar: string, desactivar: string} */
    public array $rutas;

    public function __construct(public Persona|User $receptor, public string $tipo = ActivacionWhatsAppService::PERSONA)
    {
        $whatsapp = app(WhatsAppService::class);
        $this->visible = $whatsapp->usaClavePorReceptor();

        $base = $tipo === ActivacionWhatsAppService::PERSONA ? 'mi-perfil.whatsapp' : 'profile.whatsapp';
        $this->rutas = ['guardar' => "{$base}.guardar", 'probar' => "{$base}.probar", 'desactivar' => "{$base}.desactivar"];

        $this->botNumero = (string) config('whatsapp.callmebot.bot_numero', '+34 621 34 22 27');
        $this->botFrase = (string) config('whatsapp.callmebot.frase_activacion', 'I allow callmebot to send me messages');
        $this->ayudaUrl = (string) config('whatsapp.callmebot.ayuda_url', 'https://www.callmebot.com/blog/free-api-whatsapp-messages/');
        $this->botEnlace = LinkDriver::construirUrl((string) preg_replace('/\D+/', '', $this->botNumero), $this->botFrase);

        if (! $this->visible) {
            return;
        }

        $this->activacion = $receptor->activacionWhatsApp;
        $this->telefono = WhatsAppService::normalizarTelefono($receptor->telefono);
        $this->telefonoFormateado = WhatsAppService::formatearTelefono($this->telefono);
        $this->situacion = app(ActivacionWhatsAppService::class)->situacion($receptor);
        $this->consentimiento = MensajesWhatsApp::consentimiento($tipo, $this->telefonoFormateado, $receptor->tenant);
    }

    /** Hay una clave utilizable hoy: lo único en lo que la persona ya no tiene que hacer nada más que probar. */
    public function estaActiva(): bool
    {
        return $this->situacion === ActivacionWhatsApp::ESTADO_ACTIVA;
    }

    public function render(): View
    {
        return view('components.whatsapp-activacion');
    }
}

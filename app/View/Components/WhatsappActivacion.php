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
 *     <x-whatsapp-activacion :receptor="$persona ?? null" volver="mi-perfil" />   (Mi perfil)
 *     <x-whatsapp-activacion :receptor="$user" tipo="usuario" />                  (perfil de un usuario del sistema)
 *
 * En Mi perfil la persona puede faltar (tesorero y asistente suelen no tener una ligada a su cuenta): sin
 * persona la tarjeta es la del propio usuario del sistema, con su clave guardada en el usuario, y vuelve a
 * Mi perfil tras cada acción. Un usuario que ni tiene persona ni es del sistema no ve nada, y nada rompe.
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

    /**
     * @param  string  $volver  «mi-perfil» para que, tras guardar, probar o desactivar, se vuelva a Mi perfil en vez de al perfil de usuario
     */
    public function __construct(public Persona|User|null $receptor = null, public ?string $tipo = null, public string $volver = '')
    {
        $whatsapp = app(WhatsAppService::class);
        $this->visible = $whatsapp->usaClavePorReceptor();

        // Sin persona (usuarios del sistema que no tienen una ligada) la tarjeta es la del propio usuario.
        if ($this->receptor === null) {
            $usuario = auth()->user();
            $this->visible = $this->visible && $usuario !== null && self::esDelSistema($usuario);
            $this->receptor = $usuario;
            $this->tipo = ActivacionWhatsAppService::USUARIO;
        }

        $this->tipo ??= $this->receptor instanceof Persona ? ActivacionWhatsAppService::PERSONA : ActivacionWhatsAppService::USUARIO;
        $this->volver = $this->volver === 'mi-perfil' ? 'mi-perfil' : '';

        $base = $this->tipo === ActivacionWhatsAppService::PERSONA ? 'mi-perfil.whatsapp' : 'profile.whatsapp';
        $this->rutas = ['guardar' => "{$base}.guardar", 'probar' => "{$base}.probar", 'desactivar' => "{$base}.desactivar"];

        $this->botNumero = (string) config('whatsapp.callmebot.bot_numero', '+34 644 91 07 79');
        $this->botFrase = (string) config('whatsapp.callmebot.frase_activacion', 'I allow callmebot to send me messages');
        $this->ayudaUrl = (string) config('whatsapp.callmebot.ayuda_url', 'https://www.callmebot.com/blog/free-api-whatsapp-messages/');
        $this->botEnlace = LinkDriver::construirUrl((string) preg_replace('/\D+/', '', $this->botNumero), $this->botFrase);

        if (! $this->visible) {
            return;
        }

        $this->activacion = $this->receptor->activacionWhatsApp;
        $this->telefono = WhatsAppService::normalizarTelefono($this->receptor->telefono);
        $this->telefonoFormateado = WhatsAppService::formatearTelefono($this->telefono);
        $this->situacion = app(ActivacionWhatsAppService::class)->situacion($this->receptor);
        $this->consentimiento = MensajesWhatsApp::consentimiento($this->tipo, $this->telefonoFormateado, $this->receptor->tenant);
    }

    /** Admin, tesorero o asistente: quienes reciben los avisos del sistema y tienen perfil de usuario con la tarjeta. */
    private static function esDelSistema(User $usuario): bool
    {
        return $usuario->canAccessAdmin() || $usuario->canAccessRecuento() || $usuario->canAccessAsistencia();
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

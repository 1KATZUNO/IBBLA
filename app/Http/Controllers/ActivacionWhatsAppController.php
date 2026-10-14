<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\User;
use App\Services\WhatsApp\ActivacionNoValidaException;
use App\Services\WhatsApp\ActivacionWhatsAppService;
use App\Services\WhatsApp\AuditoriaWhatsApp;
use App\Services\WhatsApp\WhatsAppEnvioException;
use App\Services\WhatsApp\WhatsAppNoConfiguradoException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Activar, probar y desactivar WhatsApp (CallMeBot) desde la tarjeta «Avisos por WhatsApp».
 *
 * Las mismas tres acciones sirven a dos pantallas, según el parámetro por defecto «tipo» de la ruta:
 *  - persona: Mi perfil. El receptor es la persona ligada a la cuenta de quien navega (nunca un id).
 *  - usuario: perfil de un usuario del sistema (admin, tesorero, asistente), para los avisos de rol.
 *
 * Solo existen con WHATSAPP_DRIVER=callmebot. La clave pegada no se devuelve a la pantalla ni se
 * guarda en los datos de formulario que Laravel recuerda tras un error.
 */
class ActivacionWhatsAppController extends Controller
{
    public function guardar(Request $request, ActivacionWhatsAppService $servicio): RedirectResponse
    {
        $receptor = $this->receptor($request);

        if ($receptor instanceof RedirectResponse) {
            return $receptor;
        }

        $request->validate(['enlace' => ['nullable', 'string', 'max:2000'], 'acepta' => ['nullable', 'boolean']]);

        try {
            $activacion = $servicio->activar($receptor, $request->input('enlace'), $request->boolean('acepta'), $request);
        } catch (ActivacionNoValidaException $e) {
            // Sin el campo del enlace: lleva la clave y no debe quedar en la sesión.
            return redirect()->to($this->destino($request))
                ->withErrors($e->errores(), 'whatsapp')
                ->withInput($request->except('enlace'));
        }

        AuditoriaWhatsApp::registrar($request, 'Activar WhatsApp (CallMeBot)', $request->route()->getName(), $this->detalles($receptor, [
            'telefono' => $activacion->telefono,
            'consentimiento_version' => $activacion->consentimiento_version,
        ]));

        return $this->volver($request, true, 'Listo, WhatsApp quedó activado. Ahora pulse «Enviar mensaje de prueba» para confirmar que le llega.');
    }

    public function probar(Request $request, ActivacionWhatsAppService $servicio): RedirectResponse
    {
        $receptor = $this->receptor($request);

        if ($receptor instanceof RedirectResponse) {
            return $receptor;
        }

        try {
            $servicio->enviarPrueba($receptor);
        } catch (WhatsAppEnvioException $e) {
            return $this->volver($request, false, 'No se pudo enviar el mensaje de prueba. '.$e->getMessage()
                .' Revise que pegó el enlace completo que le respondió el bot, o péguelo de nuevo.');
        } catch (WhatsAppNoConfiguradoException|InvalidArgumentException $e) {
            return $this->volver($request, false, 'No se pudo enviar el mensaje de prueba. '.$e->getMessage());
        }

        return $this->volver($request, true, 'Le enviamos un mensaje de prueba. Revise su WhatsApp: puede tardar unos segundos en llegar.');
    }

    public function desactivar(Request $request, ActivacionWhatsAppService $servicio): RedirectResponse
    {
        $receptor = $this->receptor($request);

        if ($receptor instanceof RedirectResponse) {
            return $receptor;
        }

        $activacion = $servicio->desactivar($receptor);

        if ($activacion === null) {
            return $this->volver($request, false, 'No tenía WhatsApp activado.');
        }

        AuditoriaWhatsApp::registrar($request, 'Desactivar WhatsApp (CallMeBot)', $request->route()->getName(), $this->detalles($receptor, [
            'telefono' => $activacion->telefono,
        ]));

        return $this->volver($request, true, 'Listo, desactivamos sus avisos por WhatsApp y borramos su clave. Puede volver a activarlos cuando quiera.');
    }

    /**
     * La persona o el usuario a quien le toca la tarjeta, o una redirección si no se puede seguir:
     * con otro driver no hay nada que activar (404) y una cuenta sin persona ligada vuelve a Principal.
     */
    private function receptor(Request $request): Persona|User|RedirectResponse
    {
        abort_unless(app(WhatsAppService::class)->usaClavePorReceptor(), 404);

        $usuario = $request->user();

        if ($request->route('tipo') === ActivacionWhatsAppService::PERSONA) {
            return $usuario->persona
                ?? redirect()->route('principal')->with('error', 'No se encontró información de persona asociada.');
        }

        return $usuario;
    }

    private function destino(Request $request): string
    {
        $ruta = $request->route('tipo') === ActivacionWhatsAppService::PERSONA ? route('mi-perfil.index') : route('profile.edit');

        return $ruta.'#whatsapp';
    }

    /** Vuelve a la tarjeta con el aviso que ella misma muestra (no depende de cómo pinte el layout los mensajes). */
    private function volver(Request $request, bool $exito, string $texto): RedirectResponse
    {
        return redirect()->to($this->destino($request))->with('whatsapp_aviso', ['exito' => $exito, 'texto' => $texto]);
    }

    /**
     * Detalles para la auditoría: quién y qué número, jamás la clave.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function detalles(Persona|User $receptor, array $extra): array
    {
        return [
            'tipo' => ActivacionWhatsAppService::tipoDe($receptor),
            'receptor_id' => $receptor->id,
            'receptor' => $receptor instanceof Persona ? $receptor->nombre : $receptor->name,
        ] + $extra;
    }
}

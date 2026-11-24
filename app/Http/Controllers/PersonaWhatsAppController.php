<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Services\InformePersonaService;
use App\Services\WhatsApp\AuditoriaWhatsApp;
use App\Services\WhatsApp\MensajesWhatsApp;
use App\Services\WhatsApp\WhatsAppEnvioException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «Enviar por WhatsApp» el informe de una persona (admin y tesorero).
 *
 * Genera el enlace firmado al PDF, arma el mensaje con la plantilla de config/whatsapp.php,
 * deja constancia en la auditoría y abre el chat de WhatsApp con el mensaje ya escrito
 * (driver «link»): quien lo pulsa solo toca «enviar». Con el driver «meta» y credenciales
 * el mensaje sale solo y se vuelve a la pantalla con el resultado.
 *
 * Con el driver «callmebot» la clave es de cada persona: a quien activó WhatsApp en su perfil el
 * informe se le manda solo (POST, enviarAutomatico); a quien no, el botón sigue abriendo wa.me como siempre.
 */
class PersonaWhatsAppController extends Controller
{
    public function __invoke(
        Request $request,
        Persona $persona,
        WhatsAppService $whatsapp,
        InformePersonaService $informe,
    ): RedirectResponse {
        $telefono = WhatsAppService::normalizarTelefono($persona->telefono);

        if ($telefono === null) {
            return $this->volver()->with('error', $this->telefonoNoValido($persona));
        }

        $vence = $informe->vence();
        $mensaje = MensajesWhatsApp::informe($persona, $informe->urlFirmada($persona, $vence), $vence);

        $resultado = 'enlace';
        $error = null;

        if ($whatsapp->puedeEnviar()) {
            try {
                $whatsapp->enviar($telefono, $mensaje);
                $resultado = 'enviado';
            } catch (WhatsAppEnvioException $e) {
                report($e);
                $resultado = 'error';
                $error = $e->getMessage();
            }
        }

        AuditoriaWhatsApp::registrar($request, 'Enviar informe por WhatsApp', 'personas.whatsapp', array_filter([
            'persona_id' => $persona->id,
            'persona' => $persona->nombre,
            'telefono' => $telefono,
            'driver' => $whatsapp->driver()->nombre(),
            'resultado' => $resultado,
            'error' => $error,
            'vence' => $vence->toDateTimeString(),
        ], fn ($valor) => $valor !== null));

        if ($resultado === 'enviado') {
            return $this->volver()->with(
                'success',
                'Informe enviado por WhatsApp a '.$persona->nombre.' ('.WhatsAppService::formatearTelefono($telefono).').'
            );
        }

        if ($resultado === 'error') {
            return $this->volver()->with(
                'error',
                'No se pudo enviar el informe de '.$persona->nombre.' por WhatsApp: '.$error
            );
        }

        return redirect()->away($whatsapp->enlace($telefono, $mensaje));
    }

    /**
     * Envía el informe solo, con la clave de CallMeBot que la persona activó en su perfil. Es un POST porque
     * escribe un mensaje de verdad (un GET podría dispararlo un enlace o una vista previa) y vuelve a la
     * pantalla de donde salió con el resultado. Si la persona ya no tiene WhatsApp automático (desactivó, cambió
     * de teléfono) no manda nada: el botón de wa.me sigue disponible para mandarlo a mano.
     */
    public function enviarAutomatico(
        Request $request,
        Persona $persona,
        WhatsAppService $whatsapp,
        InformePersonaService $informe,
    ): RedirectResponse {
        abort_unless($whatsapp->usaClavePorReceptor(), 404);

        $destinatario = $whatsapp->destinatario($persona);

        if ($destinatario->telefono === null) {
            return $this->volver()->with('error', $this->telefonoNoValido($persona));
        }

        if (! $destinatario->tieneClave()) {
            return $this->volver()->with('error', "{$persona->nombre} no tiene WhatsApp automático activado (no lo activó, lo desactivó o cambió de teléfono). "
                .'Use «Abrir WhatsApp» para mandarle el informe a mano.');
        }

        $vence = $informe->vence();
        $mensaje = MensajesWhatsApp::informe($persona, $informe->urlFirmada($persona, $vence), $vence);
        $error = null;

        try {
            $whatsapp->enviarA($destinatario, $mensaje);
        } catch (WhatsAppEnvioException $e) {
            $error = $e->getMessage();
        }

        AuditoriaWhatsApp::registrar($request, 'Enviar informe por WhatsApp', 'personas.whatsapp.enviar', array_filter([
            'persona_id' => $persona->id,
            'persona' => $persona->nombre,
            'telefono' => $destinatario->telefono,
            'driver' => $whatsapp->driver()->nombre(),
            'resultado' => $error === null ? 'enviado' : 'error',
            'error' => $error,
            'vence' => $vence->toDateTimeString(),
        ], fn ($valor) => $valor !== null));

        if ($error !== null) {
            return $this->volver()->with(
                'error',
                "No se pudo enviar el informe de {$persona->nombre} por WhatsApp: {$error} Puede mandarlo a mano con «Abrir WhatsApp»."
            );
        }

        return $this->volver()->with(
            'success',
            'Informe enviado por WhatsApp a '.$persona->nombre.' ('.WhatsAppService::formatearTelefono($destinatario->telefono).').'
        );
    }

    /**
     * Vuelve a la pantalla desde la que se pulsó el botón (Personas, Compromisos...). Sin Referer
     * Laravel usa la última página guardada en la sesión, que puede ser el botón de otra persona:
     * en ese caso se va a la lista de personas en vez de repetir el redirect a WhatsApp.
     */
    private function volver(): RedirectResponse
    {
        $destino = url()->previous(route('personas.index'));

        if (preg_match('#/personas/\d+/whatsapp$#', (string) parse_url($destino, PHP_URL_PATH))) {
            $destino = route('personas.index');
        }

        return redirect()->to($destino);
    }

    /** Dice cuál es el problema y cómo arreglarlo, porque quien pulsa el botón no ve el teléfono guardado. */
    private function telefonoNoValido(Persona $persona): string
    {
        $comoCorregirlo = 'Entre a «Editar» de la persona y escriba un número de 8 dígitos, por ejemplo 8888-7777 '
            .'(también se acepta con +506). Si tiene dos números, deje uno solo.';

        if (trim((string) $persona->telefono) === '') {
            return "No se puede enviar el informe: {$persona->nombre} no tiene teléfono registrado. {$comoCorregirlo}";
        }

        return "No se puede enviar el informe: el teléfono de {$persona->nombre} («{$persona->telefono}») no es válido para WhatsApp. {$comoCorregirlo}";
    }
}

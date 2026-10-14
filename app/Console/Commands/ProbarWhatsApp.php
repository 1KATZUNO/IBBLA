<?php

namespace App\Console\Commands;

use App\Models\ActivacionWhatsApp;
use App\Models\Persona;
use App\Models\User;
use App\Services\WhatsApp\MensajesWhatsApp;
use App\Services\WhatsApp\WhatsAppEnvioException;
use App\Services\WhatsApp\WhatsAppNoConfiguradoException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Manda un mensaje de prueba por CallMeBot a un receptor que ya activó WhatsApp (persona o usuario), para
 * comprobar desde el servidor que su clave funciona sin entrar a su cuenta.
 *
 *   php artisan whatsapp:probar 8888-7777
 *   php artisan whatsapp:probar +50688887777 --mensaje="Hola, es una prueba"
 *   php artisan whatsapp:probar 8888-7777 --simular     (no llama a CallMeBot: muestra lo que mandaría)
 *
 * Solo manda a quien activó el bot y está al día (clave legible y del número que tiene registrado hoy).
 * Nunca imprime la clave. Si una persona y un usuario comparten número y clave se manda una sola vez.
 */
class ProbarWhatsApp extends Command
{
    protected $signature = 'whatsapp:probar
        {telefono : Teléfono del receptor que ya activó WhatsApp (8888-7777 o +506 8888-7777)}
        {--mensaje= : Texto a enviar (por defecto, el mensaje de prueba de la iglesia)}
        {--simular : No llama a CallMeBot: muestra a quién se lo mandaría y con qué texto}';

    protected $description = 'Manda un mensaje de prueba por CallMeBot a un receptor que ya activó WhatsApp';

    public function handle(WhatsAppService $whatsapp): int
    {
        if (! $whatsapp->usaClavePorReceptor()) {
            $this->error('WHATSAPP_DRIVER es «'.$whatsapp->driver()->nombre().'»: para probar CallMeBot debe ser «callmebot».');

            return self::FAILURE;
        }

        $telefono = WhatsAppService::normalizarTelefono((string) $this->argument('telefono'));

        if ($telefono === null) {
            $this->error('El teléfono «'.$this->argument('telefono').'» no es válido. Escriba 8 dígitos (8888-7777) o con prefijo (+506 8888-7777).');

            return self::FAILURE;
        }

        $activaciones = ActivacionWhatsApp::withoutGlobalScopes()->activas()->where('telefono', $telefono)->get();

        if ($activaciones->isEmpty()) {
            $this->error('No hay ningún receptor con WhatsApp activado en el '.WhatsAppService::formatearTelefono($telefono).'.');
            $this->line('La persona (en Mi perfil) o el usuario (en su perfil) tiene que activarlo primero: ver «Avisos por WhatsApp».');

            return self::FAILURE;
        }

        $fallos = 0;
        $vistos = [];

        foreach ($activaciones as $activacion) {
            $receptor = $activacion->persona_id ? Persona::withoutGlobalScopes()->find($activacion->persona_id) : User::find($activacion->user_id);

            if ($receptor === null) {
                continue;
            }

            // Persona y usuario con el mismo número y la misma clave: un solo mensaje.
            $huella = $activacion->telefono.'|'.sha1((string) $activacion->clave());
            if (isset($vistos[$huella])) {
                $this->line('Ya se atendió ese número (también es de '.$this->nombre($receptor).').');

                continue;
            }
            $vistos[$huella] = true;

            $fallos += $this->probar($whatsapp, $receptor) ? 0 : 1;
        }

        return $fallos === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** true si salió bien (o, con --simular, si se habría mandado). */
    private function probar(WhatsAppService $whatsapp, Persona|User $receptor): bool
    {
        $destinatario = $whatsapp->destinatario($receptor);
        $quien = $this->nombre($receptor).' ('.($receptor instanceof Persona ? 'persona' : 'usuario').', '.WhatsAppService::formatearTelefono($destinatario->telefono).')';

        if (! $destinatario->tieneClave()) {
            $this->warn("No se le puede mandar a {$quien}: su clave no está al día (cambió de teléfono o no se puede leer). Tiene que activarlo de nuevo.");

            return false;
        }

        $mensaje = (string) ($this->option('mensaje') ?: MensajesWhatsApp::prueba($destinatario->nombre, $receptor->tenant));

        if ($this->option('simular')) {
            $this->info("[simulación] Se le mandaría a {$quien}:");
            $this->line($mensaje);

            return true;
        }

        try {
            $whatsapp->enviarA($destinatario, $mensaje, true);
        } catch (WhatsAppEnvioException|WhatsAppNoConfiguradoException|InvalidArgumentException $e) {
            $this->error("No se pudo mandar a {$quien}: ".$e->getMessage());

            return false;
        }

        $this->info("Mensaje enviado a {$quien}. Revise su WhatsApp.");

        return true;
    }

    private function nombre(Persona|User $receptor): string
    {
        return $receptor instanceof Persona ? (string) $receptor->nombre : (string) $receptor->name;
    }
}

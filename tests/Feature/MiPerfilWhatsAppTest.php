<?php

namespace Tests\Feature;

use App\Models\ActivacionWhatsApp;
use App\Models\AuditLog;
use App\Models\Persona;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * Tarjeta «Avisos por WhatsApp» de Mi perfil (CallMeBot): la persona pega el enlace que le responde el
 * bot, acepta, hace la prueba y puede desactivarlo. Todo con Http::fake; la clave nunca se ve en pantalla.
 */
class MiPerfilWhatsAppTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    private const ENLACE = 'https://api.callmebot.com/whatsapp.php?phone=+50688887777&text=This+is+a+test&apikey=1234567';

    private const EXITO = '<p>Message queued. You will receive it in a few seconds.</p>';

    private User $miembro;

    private Persona $ana;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 10:00:00');
        Http::preventStrayRequests();
        // Los tests no dependen del .env de quien los corre (un .env local puede apuntar a un servidor falso).
        config(['whatsapp.driver' => 'callmebot', 'whatsapp.callmebot.url' => 'https://api.callmebot.com/whatsapp.php']);
        $this->crearIglesiaConAdmin();
        $this->miembro = $this->crearUsuarioConRol('miembro');
        $this->ana = $this->crearPersona('Ana Mora', '8888-7777');
        $this->ana->update(['user_id' => $this->miembro->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function pagina(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->miembro)->get(route('mi-perfil.index'));
    }

    private function activar(string $enlace = self::ENLACE, bool $acepta = true): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->miembro)->post(route('mi-perfil.whatsapp.guardar'), ['enlace' => $enlace] + ($acepta ? ['acepta' => '1'] : []));
    }

    // ---------------------------------------------------------------- la tarjeta

    public function test_la_tarjeta_muestra_los_tres_pasos_con_el_numero_del_bot_y_el_mensaje_listo(): void
    {
        $this->pagina()->assertOk()
            ->assertSee('Avisos por WhatsApp')
            ->assertSee('Sin activar')
            ->assertSee('Paso 1. Guarde el contacto del bot')
            ->assertSee('Paso 2. Mándele este mensaje')
            ->assertSee('Paso 3. Copie la respuesta del bot')
            ->assertSee('+34 644 91 07 79')
            ->assertSee('I allow callmebot to send me messages')
            ->assertSee('href="https://wa.me/34644910779?text=I%20allow%20callmebot%20to%20send%20me%20messages"', false)
            ->assertSee('images/manual/whatsapp/paso-1-contacto.png', false)
            ->assertSee('images/manual/whatsapp/paso-2-mensaje.png', false)
            ->assertSee('images/manual/whatsapp/paso-3-enlace.png', false)
            ->assertSee('Pegue aquí el mensaje que le respondió el bot')
            ->assertSee('+506 8888-7777')
            ->assertSee('Autorizo a Iglesia de Prueba a enviarme por WhatsApp')
            ->assertSee('Activar WhatsApp');
    }

    public function test_el_numero_del_bot_se_cambia_por_configuracion(): void
    {
        config(['whatsapp.callmebot.bot_numero' => '+34 600 11 22 33', 'whatsapp.callmebot.frase_activacion' => 'I allow x']);

        $this->pagina()->assertSee('+34 600 11 22 33')->assertSee('href="https://wa.me/34600112233?text=I%20allow%20x"', false);
    }

    public function test_con_otro_driver_no_hay_tarjeta_ni_rutas(): void
    {
        foreach (['link', 'meta'] as $driver) {
            config(['whatsapp.driver' => $driver]);

            $this->pagina()->assertOk()->assertDontSee('Avisos por WhatsApp')->assertDontSee('Activar WhatsApp');
            $this->actingAs($this->miembro)->post(route('mi-perfil.whatsapp.guardar'), ['enlace' => self::ENLACE, 'acepta' => '1'])->assertNotFound();
            $this->actingAs($this->miembro)->post(route('mi-perfil.whatsapp.probar'))->assertNotFound();
            $this->actingAs($this->miembro)->delete(route('mi-perfil.whatsapp.desactivar'))->assertNotFound();
        }

        $this->assertSame(0, ActivacionWhatsApp::withoutGlobalScopes()->count());
    }

    public function test_sin_telefono_registrado_avisa_y_no_ofrece_el_formulario(): void
    {
        $this->ana->update(['telefono' => null]);

        $this->pagina()->assertOk()
            ->assertSee('Paso 1. Guarde el contacto del bot')
            ->assertSee('No tenemos un teléfono válido registrado a su nombre')
            ->assertDontSee('Activar WhatsApp');
    }

    public function test_solo_la_ven_quienes_entran_a_mi_perfil(): void
    {
        $invitado = $this->crearUsuarioConRol('invitado');

        $this->actingAs($invitado)->get(route('mi-perfil.index'))->assertForbidden();
        $this->actingAs($invitado)->post(route('mi-perfil.whatsapp.guardar'))->assertForbidden();

        auth()->logout();
        $this->post(route('mi-perfil.whatsapp.guardar'))->assertRedirect(route('login'));
    }

    public function test_una_cuenta_sin_persona_ligada_vuelve_a_principal(): void
    {
        $this->ana->update(['user_id' => null]);

        $this->activar()->assertRedirect(route('principal'))->assertSessionHas('error');
        $this->actingAs($this->miembro)->post(route('mi-perfil.whatsapp.probar'))->assertRedirect(route('principal'));
        $this->assertSame(0, ActivacionWhatsApp::withoutGlobalScopes()->count());
    }

    // ---------------------------------------------------------------- activar

    public function test_activar_guarda_todo_y_la_pantalla_dice_que_falta_la_prueba(): void
    {
        $this->activar()
            ->assertRedirect(route('mi-perfil.index').'#whatsapp')
            ->assertSessionHas('whatsapp_aviso', fn (array $aviso) => $aviso['exito'] === true && str_contains($aviso['texto'], 'WhatsApp quedó activado'));

        $activacion = ActivacionWhatsApp::sole();
        $this->assertSame($this->ana->id, $activacion->persona_id);
        $this->assertSame('50688887777', $activacion->telefono);
        $this->assertSame('1234567', $activacion->clave());
        $this->assertTrue($activacion->consentimiento_at->equalTo(now()));

        $this->get(route('mi-perfil.index'))
            ->assertSee('Activado, falta la prueba')
            ->assertSee('Su clave está guardada y no se vuelve a mostrar')
            ->assertSee('Enviar mensaje de prueba')
            ->assertSee('Desactivar')
            ->assertSee('images/manual/whatsapp/paso-4-prueba.png', false);
    }

    public function test_la_clave_nunca_aparece_en_pantalla_ni_en_la_sesion_ni_en_la_auditoria(): void
    {
        $respuesta = $this->activar();
        $html = $this->get(route('mi-perfil.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('1234567', $html);
        $this->assertStringNotContainsString('apikey=1', $html);
        $this->assertStringNotContainsString('1234567', json_encode(session()->all()));
        $this->assertStringNotContainsString('1234567', DB::table('audit_logs')->pluck('details')->implode(' '));
        $this->assertStringNotContainsString('1234567', DB::table('sessions')->pluck('payload')->implode(' '));
        $respuesta->assertSessionHasNoErrors();
    }

    public function test_activar_deja_constancia_en_la_auditoria_sin_la_clave(): void
    {
        $this->activar();

        $registro = AuditLog::where('action', 'Activar WhatsApp (CallMeBot)')->sole();
        $this->assertSame($this->miembro->id, $registro->user_id);
        $this->assertSame('mi-perfil.whatsapp.guardar', $registro->route);
        $detalles = json_decode($registro->details, true);
        $this->assertSame($this->ana->id, $detalles['receptor_id']);
        $this->assertSame('Ana Mora', $detalles['receptor']);
        $this->assertSame('50688887777', $detalles['telefono']);
        $this->assertSame('v1', $detalles['consentimiento_version']);
    }

    public function test_un_enlace_de_otro_numero_se_rechaza_con_un_mensaje_claro_y_no_guarda_nada(): void
    {
        $this->activar(str_replace('50688887777', '50689991111', self::ENLACE))
            ->assertRedirect(route('mi-perfil.index').'#whatsapp')
            ->assertSessionHasErrors('enlace', null, 'whatsapp');

        $this->assertSame(0, ActivacionWhatsApp::withoutGlobalScopes()->count());
        $this->get(route('mi-perfil.index'))
            ->assertSee('Ese enlace es del número +506 8999-1111, pero el número que la iglesia tiene registrado para usted es +506 8888-7777')
            ->assertDontSee('1234567');
    }

    public function test_sin_aceptar_no_se_guarda_y_el_enlace_no_se_recuerda_en_el_formulario(): void
    {
        $this->activar(self::ENLACE, false)->assertSessionHasErrors('acepta', null, 'whatsapp');

        $this->assertSame(0, ActivacionWhatsApp::withoutGlobalScopes()->count());
        $this->assertNull(session()->getOldInput('enlace'), 'El campo del enlace lleva la clave: no debe quedar en la sesión');
    }

    public function test_cada_enlace_malo_dice_que_hacer(): void
    {
        $this->activar('ya activé el bot')->assertSessionHasErrors('enlace', null, 'whatsapp');
        $this->get(route('mi-perfil.index'))->assertSee('No encontramos el enlace del bot');

        $this->activar('https://www.ejemplo.com/x?apikey=1234567')->assertSessionHasErrors('enlace', null, 'whatsapp');
        $this->get(route('mi-perfil.index'))->assertSee('Ese enlace no es de CallMeBot');

        $this->actingAs($this->miembro)->post(route('mi-perfil.whatsapp.guardar'), ['acepta' => '1'])->assertSessionHasErrors('enlace', null, 'whatsapp');
        $this->assertSame(0, ActivacionWhatsApp::withoutGlobalScopes()->count());
    }

    public function test_un_pegado_enorme_se_rechaza(): void
    {
        $this->activar(self::ENLACE.str_repeat('x', 2500))->assertSessionHasErrors('enlace');

        $this->assertSame(0, ActivacionWhatsApp::withoutGlobalScopes()->count());
    }

    public function test_cada_persona_solo_toca_su_propia_activacion(): void
    {
        $beto = $this->crearPersona('Beto Rojas', '7777-6666');
        $ajena = $this->activarWhatsApp($beto, '999888');

        $this->activar();
        $this->actingAs($this->miembro)->delete(route('mi-perfil.whatsapp.desactivar'));

        $this->assertTrue($ajena->fresh()->activo, 'La activación de otra persona no se toca');
        $this->assertSame('999888', $ajena->fresh()->clave());
        $this->assertFalse($this->ana->fresh()->activacionWhatsApp->activo);
    }

    // ---------------------------------------------------------------- probar

    public function test_la_prueba_sale_por_callmebot_y_la_pantalla_la_da_por_hecha(): void
    {
        $this->activar();
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);

        $this->actingAs($this->miembro)->post(route('mi-perfil.whatsapp.probar'))
            ->assertRedirect(route('mi-perfil.index').'#whatsapp')
            ->assertSessionHas('whatsapp_aviso', fn (array $a) => $a['exito'] === true && str_contains($a['texto'], 'mensaje de prueba'));

        Http::assertSentCount(1);
        $this->assertNotNull($this->ana->fresh()->activacionWhatsApp->verificado_at);
        $this->get(route('mi-perfil.index'))->assertSee('Prueba hecha el 13/10/2026')->assertDontSee('Todavía falta la prueba');
    }

    public function test_si_la_prueba_falla_dice_por_que_sin_la_clave_y_queda_para_reintentar(): void
    {
        $this->activar();
        Http::fake(['*' => Http::response('ERROR: APIKey 1234567 is invalid or phone number not registered', 200)]);

        $this->actingAs($this->miembro)->post(route('mi-perfil.whatsapp.probar'))
            ->assertSessionHas('whatsapp_aviso', fn (array $a) => $a['exito'] === false
                && str_contains($a['texto'], 'No se pudo enviar el mensaje de prueba')
                && str_contains($a['texto'], 'APIKey *** is invalid')
                && ! str_contains($a['texto'], '1234567'));

        $html = $this->get(route('mi-perfil.index'))->assertSee('El último envío falló')->getContent();
        $this->assertStringNotContainsString('1234567', $html);
        $this->assertNull($this->ana->fresh()->activacionWhatsApp->verificado_at);
    }

    public function test_sin_activar_no_hay_prueba(): void
    {
        Http::fake();

        $this->actingAs($this->miembro)->post(route('mi-perfil.whatsapp.probar'))
            ->assertSessionHas('whatsapp_aviso', fn (array $a) => $a['exito'] === false && str_contains($a['texto'], 'no tiene activado WhatsApp'));

        Http::assertNothingSent();
    }

    public function test_la_prueba_tiene_limite_de_seis_por_minuto(): void
    {
        $this->activar();
        Http::fake(['*' => Http::response(self::EXITO, 200)]);

        foreach (range(1, 6) as $n) {
            $this->actingAs($this->miembro)->post(route('mi-perfil.whatsapp.probar'))->assertRedirect();
        }

        $this->actingAs($this->miembro)->post(route('mi-perfil.whatsapp.probar'))->assertStatus(429);
        Http::assertSentCount(6);
    }

    // ---------------------------------------------------------------- desactivar y estados

    public function test_desactivar_borra_la_clave_y_lo_deja_en_la_auditoria(): void
    {
        $this->activar();

        $this->actingAs($this->miembro)->delete(route('mi-perfil.whatsapp.desactivar'))
            ->assertRedirect(route('mi-perfil.index').'#whatsapp')
            ->assertSessionHas('whatsapp_aviso', fn (array $a) => $a['exito'] === true && str_contains($a['texto'], 'borramos su clave'));

        $this->assertNull(DB::table('activaciones_whatsapp')->value('apikey'));
        $this->assertSame(1, AuditLog::where('action', 'Desactivar WhatsApp (CallMeBot)')->count());
        $this->get(route('mi-perfil.index'))->assertSee('Desactivado')->assertSee('Desactivó los avisos por WhatsApp el 13/10/2026')->assertSee('Activar WhatsApp');
    }

    public function test_desactivar_sin_nada_activado_lo_dice(): void
    {
        $this->actingAs($this->miembro)->delete(route('mi-perfil.whatsapp.desactivar'))
            ->assertSessionHas('whatsapp_aviso', fn (array $a) => $a['exito'] === false && $a['texto'] === 'No tenía WhatsApp activado.');

        $this->assertSame(0, AuditLog::where('action', 'Desactivar WhatsApp (CallMeBot)')->count());
    }

    public function test_si_cambio_su_telefono_la_tarjeta_pide_activar_de_nuevo(): void
    {
        $this->activar();
        $this->pagina(); // gasta el aviso de «quedó activado», que también habla de la prueba
        $this->ana->update(['telefono' => '7000-1111']);
        $this->miembro = $this->miembro->fresh(); // actingAs reutiliza el mismo objeto: sin esto conservaría la persona vieja

        $this->pagina()->assertSee('Cambió su número')
            ->assertSee('Su teléfono registrado cambió después de activar WhatsApp (ahora es +506 7000-1111)')
            ->assertSee('Activar WhatsApp')
            ->assertDontSee('Enviar mensaje de prueba');
    }

    public function test_una_clave_que_no_se_puede_leer_pide_pegar_el_enlace_otra_vez(): void
    {
        $this->activar();
        DB::table('activaciones_whatsapp')->update(['apikey' => 'no-es-un-payload-cifrado']);

        $this->pagina()->assertOk()->assertSee('Hay que activarlo de nuevo')->assertSee('No pudimos leer la clave que tenía guardada');
    }

    public function test_el_resto_de_mi_perfil_sigue_funcionando(): void
    {
        $this->pagina()->assertOk()->assertSee('Mi estado de cuenta (PDF)')->assertSee('Promesas Activas');
    }
}

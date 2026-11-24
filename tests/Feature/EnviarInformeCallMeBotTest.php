<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Persona;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * «Enviar informe por WhatsApp» con el driver callmebot: a la persona que activó WhatsApp se le manda solo
 * (mensaje con el enlace firmado al PDF) y queda en la auditoría; a quien no, el botón abre wa.me como hoy.
 */
class EnviarInformeCallMeBotTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    private const EXITO = '<p>Message queued. You will receive it in a few seconds.</p>';

    private Persona $ana;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 10:00:00');
        Http::preventStrayRequests();
        config(['whatsapp.driver' => 'callmebot', 'whatsapp.callmebot.url' => 'https://api.callmebot.com/whatsapp.php']);
        $this->crearIglesiaConAdmin();
        $this->crearCategoriasPorDefecto();
        $this->ana = $this->crearPersona('Ana Mora', '8888-7777');
        $this->crearSobre($this->crearCulto('2026-03-01'), $this->ana, ['diezmo' => 5000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function enviar(?Persona $persona = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->from('/personas')->post(route('personas.whatsapp.enviar', $persona ?? $this->ana));
    }

    public function test_a_quien_activo_whatsapp_el_informe_se_le_manda_solo_con_el_enlace_firmado(): void
    {
        $this->activarWhatsApp($this->ana, 'CLAVEANA');
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);

        $this->enviar()->assertRedirect('/personas')
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Ana Mora') && str_contains($m, '+506 8888-7777'));

        Http::assertSentCount(1);
        Http::assertSent(function (ClientRequest $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return $q['phone'] === '+50688887777'
                && $q['apikey'] === 'CLAVEANA'
                && str_contains($q['text'], 'Hola Ana Mora')
                && str_contains($q['text'], 'Iglesia de Prueba')
                && str_contains($q['text'], 'vence el 20 de octubre de 2026')
                && preg_match('#/informe/'.$this->ana->id.'\?expires=\d+&signature=\w+#', $q['text']) === 1;
        });
    }

    public function test_el_enlace_del_mensaje_abre_el_pdf_sin_sesion(): void
    {
        $this->activarWhatsApp($this->ana);
        $texto = '';
        Http::fake(function (ClientRequest $r) use (&$texto) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
            $texto = $q['text'];

            return Http::response(self::EXITO, 200);
        });

        $this->enviar();
        preg_match('#https?://[^\s]+/informe/\d+\?[^\s]+#', $texto, $enlace);
        auth()->logout();
        $this->flushSession();

        $this->get($enlace[0])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_deja_constancia_en_la_auditoria_sin_la_clave_ni_la_firma(): void
    {
        $this->activarWhatsApp($this->ana, 'CLAVEANA');
        Http::fake(['*' => Http::response(self::EXITO, 200)]);

        $this->enviar();

        $registro = AuditLog::where('action', 'Enviar informe por WhatsApp')->sole();
        $this->assertSame($this->admin->id, $registro->user_id);
        $this->assertSame('personas.whatsapp.enviar', $registro->route);
        $this->assertSame('POST', $registro->method);
        $detalles = json_decode($registro->details, true);
        $this->assertSame($this->ana->id, $detalles['persona_id']);
        $this->assertSame('50688887777', $detalles['telefono']);
        $this->assertSame('callmebot', $detalles['driver']);
        $this->assertSame('enviado', $detalles['resultado']);
        $this->assertStringNotContainsString('CLAVEANA', $registro->details);
        $this->assertStringNotContainsString('signature', $registro->details);
    }

    public function test_si_callmebot_falla_vuelve_con_el_motivo_sin_la_clave_y_lo_deja_en_la_auditoria(): void
    {
        $this->activarWhatsApp($this->ana, 'CLAVEANA');
        Http::fake(['*' => Http::response('ERROR: APIKey CLAVEANA is invalid', 200)]);

        $this->enviar()->assertRedirect('/personas')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'No se pudo enviar el informe de Ana Mora')
                && str_contains($m, 'APIKey *** is invalid')
                && str_contains($m, 'Abrir WhatsApp')
                && ! str_contains($m, 'CLAVEANA'));

        $registro = AuditLog::where('action', 'Enviar informe por WhatsApp')->sole();
        $detalles = json_decode($registro->details, true);
        $this->assertSame('error', $detalles['resultado']);
        $this->assertStringNotContainsString('CLAVEANA', $registro->details);
    }

    public function test_sin_whatsapp_automatico_no_manda_nada_y_explica_que_hacer(): void
    {
        Http::fake();

        $this->enviar()->assertRedirect('/personas')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Ana Mora no tiene WhatsApp automático activado') && str_contains($m, 'Abrir WhatsApp'));

        Http::assertNothingSent();
        $this->assertSame(0, AuditLog::where('action', 'Enviar informe por WhatsApp')->count());
    }

    public function test_si_la_persona_cambio_de_telefono_no_se_usa_la_clave_vieja(): void
    {
        $this->activarWhatsApp($this->ana);
        $this->ana->update(['telefono' => '7000-1111']);
        Http::fake();

        $this->enviar()->assertSessionHas('error', fn ($m) => str_contains($m, 'cambió de teléfono'));

        Http::assertNothingSent();
    }

    public function test_con_un_telefono_que_no_sirve_dice_como_arreglarlo(): void
    {
        $dosNumeros = $this->crearPersona('Rosa Solís', '8888-7777 / 2222-3333');
        Http::fake();

        $this->enviar($dosNumeros)->assertSessionHas('error', fn ($m) => str_contains($m, '8888-7777 / 2222-3333') && str_contains($m, 'no es válido'));

        Http::assertNothingSent();
    }

    public function test_el_enlace_manual_sigue_funcionando_aunque_la_persona_tenga_clave(): void
    {
        $this->activarWhatsApp($this->ana);
        Http::fake();

        $this->actingAs($this->admin)->get(route('personas.whatsapp', $this->ana))
            ->assertRedirectContains('https://wa.me/50688887777?text=');

        Http::assertNothingSent();
    }

    public function test_con_otro_driver_la_ruta_automatica_no_existe(): void
    {
        config(['whatsapp.driver' => 'link']);
        $this->activarWhatsApp($this->ana);
        Http::fake();

        $this->enviar()->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_solo_admin_y_tesorero_y_solo_de_su_iglesia(): void
    {
        $this->activarWhatsApp($this->ana);
        Http::fake(['*' => Http::response(self::EXITO, 200)]);

        foreach (['asistente', 'miembro', 'servidor', 'invitado'] as $rol) {
            $this->actingAs($this->crearUsuarioConRol($rol))->post(route('personas.whatsapp.enviar', $this->ana))->assertForbidden();
        }
        Http::assertNothingSent();

        $ajena = $this->crearPersona('Persona Ajena', '8000-1111', $this->crearOtraIglesia()->id);
        $this->enviar($ajena)->assertNotFound();

        $this->actingAs($this->crearUsuarioConRol('tesorero'))->post(route('personas.whatsapp.enviar', $this->ana))->assertRedirect();
        Http::assertSentCount(1);

        auth()->logout();
        $this->post(route('personas.whatsapp.enviar', $this->ana))->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------- el botón

    public function test_la_lista_ofrece_el_envio_solo_a_quien_activo_y_el_enlace_a_los_demas(): void
    {
        $beto = $this->crearPersona('Beto Rojas', '7777-6666');
        $this->activarWhatsApp($this->ana);

        $html = $this->actingAs($this->admin)->get(route('personas.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'action="'.route('personas.whatsapp.enviar', $this->ana).'"'), 'Ana: formulario POST');
        $this->assertStringNotContainsString('href="'.route('personas.whatsapp', $this->ana).'"', $html);
        $this->assertSame(1, substr_count($html, 'href="'.route('personas.whatsapp', $beto).'"'), 'Beto: el enlace de siempre');
        $this->assertStringNotContainsString('action="'.route('personas.whatsapp.enviar', $beto).'"', $html);
        $this->assertStringContainsString('tiene WhatsApp automático', $html);
    }

    public function test_la_ficha_y_los_compromisos_agregan_el_enlace_manual_junto_al_envio_solo(): void
    {
        $this->activarWhatsApp($this->ana);

        foreach ([route('personas.show', $this->ana), route('compromisos.show', $this->ana)] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk()
                ->assertSee('action="'.route('personas.whatsapp.enviar', $this->ana).'"', false)
                ->assertSee('href="'.route('personas.whatsapp', $this->ana).'"', false)
                ->assertSee('o abrirlo a mano');
        }
    }

    public function test_sin_clave_o_con_otro_driver_el_boton_es_el_de_siempre(): void
    {
        $this->actingAs($this->admin)->get(route('personas.index'))
            ->assertSee('href="'.route('personas.whatsapp', $this->ana).'"', false)
            ->assertDontSee('personas/'.$this->ana->id.'/whatsapp" class="inline-block"', false);

        config(['whatsapp.driver' => 'link']);
        $this->activarWhatsApp($this->ana);
        $this->actingAs($this->admin)->get(route('personas.index'))
            ->assertSee('href="'.route('personas.whatsapp', $this->ana).'"', false)
            ->assertDontSee('tiene WhatsApp automático');
    }

    // ---------------------------------------------------------------- la ficha

    public function test_la_ficha_dice_si_la_persona_tiene_whatsapp_automatico(): void
    {
        $url = route('personas.show', $this->ana);

        $this->actingAs($this->admin)->get($url)->assertSee('WhatsApp automático')->assertSee('Sin activar')
            ->assertSee('Esta persona no tiene cuenta para entrar al sistema');

        $this->ana->update(['user_id' => $this->crearUsuarioConRol('miembro')->id]);
        $this->get($url)->assertSee('La persona lo activa desde su perfil (Mi perfil)');

        $activacion = $this->activarWhatsApp($this->ana->fresh(), 'CLAVEANA');
        $html = $this->get($url)->assertSee('Activado')->assertSee('desde el 13/10/2026 (+506 8888-7777)')->assertSee('todavía sin probar')->getContent();
        $this->assertStringNotContainsString('CLAVEANA', $html);

        $activacion->update(['verificado_at' => now(), 'ultimo_error' => 'CallMeBot respondió 503.', 'ultimo_envio_at' => now()]);
        $this->get($url)->assertSee('probado el 13/10/2026')->assertSee('El último envío falló')->assertSee('CallMeBot respondió 503.');

        $this->ana->update(['telefono' => '7000-1111']);
        $this->get($url)->assertSee('Hay que activarlo de nuevo')->assertSee('Cambió de teléfono (+506 7000-1111)');

        $this->ana->update(['telefono' => '8888-7777']);
        $activacion->forceFill(['activo' => false, 'apikey' => null, 'revocado_at' => now()])->save();
        $this->get($url)->assertSee('Desactivado')->assertSee('La persona lo desactivó el 13/10/2026');
    }

    public function test_con_otro_driver_la_ficha_no_dice_nada_de_whatsapp_automatico(): void
    {
        config(['whatsapp.driver' => 'link']);

        $this->actingAs($this->admin)->get(route('personas.show', $this->ana))->assertOk()->assertDontSee('WhatsApp automático');
    }
}

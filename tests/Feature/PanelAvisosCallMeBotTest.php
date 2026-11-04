<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AvisoWhatsApp;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * Panel /avisos con el driver callmebot: lo que ya salió solo no estorba, lo pendiente de quien no tiene
 * WhatsApp automático sigue con «Abrir WhatsApp», y a quien sí lo tiene se le puede reintentar con «Enviar ahora».
 */
class PanelAvisosCallMeBotTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    private const EXITO = '<p>Message queued. You will receive it in a few seconds.</p>';

    private User $tesorero;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 10:00:00');
        Http::preventStrayRequests();
        config(['whatsapp.driver' => 'callmebot', 'whatsapp.callmebot.url' => 'https://api.callmebot.com/whatsapp.php']);
        $this->crearIglesiaConAdmin();
        $this->admin->update(['telefono' => '8888-1001']);
        $this->tesorero = $this->crearLider('tesorero', '8888-1002', 'Teresa Tesorera');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function avisoPara(User $usuario, array $extra = [], ?Tenant $iglesia = null): AvisoWhatsApp
    {
        return AvisoWhatsApp::create($extra + [
            'tenant_id' => ($iglesia ?? $this->iglesia)->id,
            'evento' => AvisoWhatsApp::EVENTO_CULTO_CREADO,
            'titulo' => 'Culto creado: Domingo AM del 18/10/2026',
            'mensaje' => "Hola {$usuario->name}. Se creó un culto.\n\nRecuento: https://x.test/recuento?culto_id=1",
            'destinatario_nombre' => $usuario->name,
            'destinatario_telefono' => \App\Services\WhatsApp\WhatsAppService::normalizarTelefono($usuario->telefono),
            'user_id' => $usuario->id,
            'estado' => AvisoWhatsApp::PENDIENTE,
        ]);
    }

    // ---------------------------------------------------------------- panel

    public function test_el_panel_explica_que_quien_activo_whatsapp_recibe_solo(): void
    {
        $this->actingAs($this->admin)->get(route('avisos.index'))->assertOk()
            ->assertSee('Quien activó WhatsApp en su perfil recibe sus avisos solo')
            ->assertSee('Active WhatsApp en su perfil')
            ->assertSee('href="'.route('profile.edit').'#whatsapp"', false);
    }

    public function test_quien_ya_activo_no_ve_la_invitacion_a_activar(): void
    {
        $this->activarWhatsApp($this->admin);

        $this->actingAs($this->admin)->get(route('avisos.index'))->assertOk()
            ->assertSee('Quien activó WhatsApp en su perfil recibe sus avisos solo')
            ->assertDontSee('Active WhatsApp en su perfil');
    }

    public function test_el_pendiente_de_quien_no_tiene_clave_sigue_siendo_manual(): void
    {
        $this->avisoPara($this->tesorero);

        $this->actingAs($this->admin)->get(route('avisos.index'))->assertOk()
            ->assertSee('No tiene WhatsApp automático: se manda con «Abrir WhatsApp».')
            ->assertSee('Abrir WhatsApp')
            ->assertSee('Marcar enviado')
            ->assertDontSee('Enviar ahora');
    }

    public function test_el_pendiente_de_quien_tiene_clave_ofrece_reintentar(): void
    {
        $this->activarWhatsApp($this->tesorero);
        $aviso = $this->avisoPara($this->tesorero);

        $this->actingAs($this->admin)->get(route('avisos.index'))->assertOk()
            ->assertSee('Tiene WhatsApp automático, pero este aviso no salió')
            ->assertSee('Enviar ahora')
            ->assertSee('action="'.route('avisos.enviar-ahora', $aviso).'"', false)
            ->assertSee('Abrir WhatsApp');
    }

    public function test_con_otro_driver_el_panel_es_el_de_siempre(): void
    {
        config(['whatsapp.driver' => 'link']);
        $this->activarWhatsApp($this->tesorero);
        $this->avisoPara($this->tesorero);

        $this->actingAs($this->admin)->get(route('avisos.index'))->assertOk()
            ->assertSee('Abrir WhatsApp')
            ->assertDontSee('Enviar ahora')
            ->assertDontSee('Quien activó WhatsApp en su perfil')
            ->assertDontSee('No tiene WhatsApp automático');
    }

    // ---------------------------------------------------------------- enviar ahora

    public function test_enviar_ahora_manda_el_aviso_con_la_clave_y_lo_marca_enviado_por_quien_lo_pidio(): void
    {
        $this->activarWhatsApp($this->tesorero, 'CLAVETES');
        $aviso = $this->avisoPara($this->tesorero);
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);

        $this->actingAs($this->admin)->post(route('avisos.enviar-ahora', $aviso))
            ->assertRedirect(route('avisos.index'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Teresa Tesorera'));

        $aviso->refresh();
        $this->assertSame(AvisoWhatsApp::ENVIADO, $aviso->estado);
        $this->assertSame($this->admin->id, $aviso->enviado_por);
        Http::assertSent(function ($r) use ($aviso) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return $q['phone'] === '+50688881002' && $q['apikey'] === 'CLAVETES' && $q['text'] === $aviso->mensaje;
        });

        $registro = AuditLog::where('action', 'Enviar aviso de WhatsApp (CallMeBot)')->sole();
        $this->assertSame($aviso->id, json_decode($registro->details, true)['aviso_id']);
        $this->assertStringNotContainsString('CLAVETES', $registro->details);
    }

    public function test_si_falla_dice_por_que_y_el_aviso_sigue_pendiente(): void
    {
        $this->activarWhatsApp($this->tesorero, 'CLAVETES');
        $aviso = $this->avisoPara($this->tesorero);
        Http::fake(['*' => Http::response('ERROR: APIKey CLAVETES is invalid', 200)]);

        $this->actingAs($this->admin)->post(route('avisos.enviar-ahora', $aviso))
            ->assertRedirect(route('avisos.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'APIKey *** is invalid') && str_contains($m, 'Abrir WhatsApp') && ! str_contains($m, 'CLAVETES'));

        $this->assertSame(AvisoWhatsApp::PENDIENTE, $aviso->fresh()->estado);
    }

    public function test_sin_clave_no_hay_envio_automatico_ni_llamada_a_la_red(): void
    {
        $aviso = $this->avisoPara($this->tesorero);
        Http::fake();

        $this->actingAs($this->admin)->post(route('avisos.enviar-ahora', $aviso))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'no tiene WhatsApp automático activado'));

        Http::assertNothingSent();
        $this->assertSame(AvisoWhatsApp::PENDIENTE, $aviso->fresh()->estado);
    }

    public function test_con_otro_driver_la_ruta_no_existe(): void
    {
        config(['whatsapp.driver' => 'link']);
        $aviso = $this->avisoPara($this->tesorero);

        $this->actingAs($this->admin)->post(route('avisos.enviar-ahora', $aviso))->assertNotFound();
    }

    public function test_un_aviso_ya_atendido_no_se_envia(): void
    {
        $this->activarWhatsApp($this->tesorero);
        $aviso = $this->avisoPara($this->tesorero, ['estado' => AvisoWhatsApp::DESCARTADO]);
        Http::fake();

        $this->actingAs($this->admin)->post(route('avisos.enviar-ahora', $aviso))->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_no_se_puede_enviar_un_aviso_de_otra_iglesia(): void
    {
        $otra = $this->crearOtraIglesia();
        $ajeno = $this->crearLider('admin', '8888-1500', 'Admin Ajeno', $otra);
        $aviso = $this->avisoPara($ajeno, [], $otra);
        Http::fake();

        $this->actingAs($this->admin)->post(route('avisos.enviar-ahora', $aviso))->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_el_tesorero_puede_y_los_demas_no(): void
    {
        $this->activarWhatsApp($this->tesorero);
        $aviso = $this->avisoPara($this->tesorero);
        Http::fake(['*' => Http::response(self::EXITO, 200)]);

        $this->actingAs($this->crearUsuarioConRol('asistente'))->post(route('avisos.enviar-ahora', $aviso))->assertForbidden();
        $this->actingAs($this->crearUsuarioConRol('miembro'))->post(route('avisos.enviar-ahora', $aviso))->assertForbidden();
        Http::assertNothingSent();

        $this->actingAs($this->tesorero)->post(route('avisos.enviar-ahora', $aviso))->assertRedirect(route('avisos.index'));
        Http::assertSentCount(1);

        auth()->logout();
        $this->post(route('avisos.enviar-ahora', $aviso))->assertRedirect(route('login'));
    }
}

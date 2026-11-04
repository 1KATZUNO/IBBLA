<?php

namespace Tests\Feature;

use App\Models\AvisoWhatsApp;
use App\Models\Culto;
use App\Models\User;
use App\Services\WhatsApp\AvisosWhatsAppService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Http;
use ReflectionProperty;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * Avisos a los líderes con el driver callmebot: quien activó WhatsApp en su perfil recibe el aviso solo
 * (y queda enviado por el sistema); quien no, lo tiene pendiente en el panel con el toque manual (wa.me).
 * Todo con Http::fake: nunca se llama a CallMeBot de verdad.
 */
class AvisosWhatsAppCallMeBotTest extends TestCase
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

    private function crearCultoPorHttp(): void
    {
        $this->actingAs($this->admin)->post(route('cultos.store'), ['fecha' => '2026-10-18', 'hora' => '09:00', 'tipo_culto' => 'domingo'])
            ->assertRedirect(route('cultos.index'));
    }

    private function aviso(User $usuario): AvisoWhatsApp
    {
        return AvisoWhatsApp::withoutGlobalScopes()->where('user_id', $usuario->id)->sole();
    }

    // ---------------------------------------------------------------- envío automático

    public function test_quien_activo_whatsapp_recibe_el_aviso_solo_y_el_otro_queda_pendiente(): void
    {
        $this->activarWhatsApp($this->admin, 'CLAVEADMIN');
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);

        $this->crearCultoPorHttp();

        $delAdmin = $this->aviso($this->admin);
        $this->assertSame(AvisoWhatsApp::ENVIADO, $delAdmin->estado);
        $this->assertNotNull($delAdmin->enviado_at);
        $this->assertNull($delAdmin->enviado_por, 'Lo envió el sistema');
        $this->assertSame(AvisoWhatsApp::PENDIENTE, $this->aviso($this->tesorero)->estado, 'Sin clave: sigue el toque manual');

        Http::assertSentCount(1);
        Http::assertSent(function (ClientRequest $r) use ($delAdmin) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return $q['phone'] === '+50688881001' && $q['apikey'] === 'CLAVEADMIN' && $q['text'] === $delAdmin->mensaje;
        });
    }

    public function test_si_nadie_tiene_clave_no_se_llama_a_callmebot_y_todo_queda_pendiente(): void
    {
        Http::fake();

        $this->crearCultoPorHttp();

        Http::assertNothingSent();
        $this->assertSame(2, AvisoWhatsApp::withoutGlobalScopes()->where('estado', AvisoWhatsApp::PENDIENTE)->count());
    }

    public function test_si_callmebot_falla_el_aviso_sigue_pendiente_y_el_culto_se_crea(): void
    {
        $this->activarWhatsApp($this->admin, 'CLAVEADMIN');
        Http::fake(['*' => Http::response('ERROR: APIKey is invalid', 200)]);

        $this->crearCultoPorHttp();

        $this->assertSame(1, Culto::count());
        $this->assertSame(AvisoWhatsApp::PENDIENTE, $this->aviso($this->admin)->estado);
        $this->assertStringContainsString('APIKey is invalid', (string) $this->admin->fresh()->activacionWhatsApp->ultimo_error);
    }

    public function test_el_aviso_de_sobres_alcanzados_tambien_sale_solo(): void
    {
        config(['whatsapp.avisos.sobres_umbral' => 2]);
        $this->activarWhatsApp($this->tesorero, 'CLAVETES');
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);
        $culto = $this->crearCulto('2026-10-18');
        $servicio = app(AvisosWhatsAppService::class);

        foreach ([1, 2] as $n) {
            $this->crearSobre($culto, null, ['diezmo' => 1000]);
            $servicio->sobreRegistrado($culto);
        }

        $delTesorero = AvisoWhatsApp::withoutGlobalScopes()->where('evento', AvisoWhatsApp::EVENTO_SOBRES_ALCANZADOS)->where('user_id', $this->tesorero->id)->sole();
        $this->assertSame(AvisoWhatsApp::ENVIADO, $delTesorero->estado);
        Http::assertSentCount(1);
    }

    public function test_si_el_lider_cambio_de_telefono_la_clave_vieja_no_se_usa(): void
    {
        $this->activarWhatsApp($this->admin, 'CLAVEADMIN');
        $this->admin->update(['telefono' => '8888-1999']);
        Http::fake();

        $this->crearCultoPorHttp();

        Http::assertNothingSent();
        $this->assertSame(AvisoWhatsApp::PENDIENTE, $this->aviso($this->admin)->estado);
    }

    public function test_con_el_driver_link_la_activacion_no_dispara_ningun_envio(): void
    {
        $this->activarWhatsApp($this->admin, 'CLAVEADMIN');
        config(['whatsapp.driver' => 'link']);
        Http::fake();

        $this->crearCultoPorHttp();

        Http::assertNothingSent();
        $this->assertSame(AvisoWhatsApp::PENDIENTE, $this->aviso($this->admin)->estado);
    }

    public function test_en_una_peticion_web_el_envio_se_difiere_hasta_despues_de_la_respuesta(): void
    {
        $this->activarWhatsApp($this->admin, 'CLAVEADMIN');
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);
        // En consola (y en los tests) el envío va en línea; aquí se simula una petición web.
        (new ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);

        $culto = $this->crearCulto('2026-10-18');
        app(AvisosWhatsAppService::class)->cultoCreado($culto);

        Http::assertNothingSent();
        $this->assertSame(AvisoWhatsApp::PENDIENTE, $this->aviso($this->admin)->estado, 'Todavía no salió: se difirió');
        $this->assertCount(1, app(DeferredCallbackCollection::class));

        app(DeferredCallbackCollection::class)->invoke();

        Http::assertSentCount(1);
        $this->assertSame(AvisoWhatsApp::ENVIADO, $this->aviso($this->admin)->estado);
    }

    public function test_un_aviso_atendido_a_mano_mientras_tanto_no_se_envia(): void
    {
        $this->activarWhatsApp($this->admin, 'CLAVEADMIN');
        $aviso = AvisoWhatsApp::create([
            'tenant_id' => $this->iglesia->id, 'evento' => AvisoWhatsApp::EVENTO_CULTO_CREADO, 'titulo' => 'Culto creado',
            'mensaje' => 'Hola', 'destinatario_nombre' => 'Admin', 'destinatario_telefono' => '50688881001',
            'user_id' => $this->admin->id, 'estado' => AvisoWhatsApp::DESCARTADO,
        ]);
        Http::fake();

        $motivo = app(AvisosWhatsAppService::class)->enviarAviso($aviso);

        $this->assertSame('El aviso ya no estaba pendiente.', $motivo);
        Http::assertNothingSent();
    }
}

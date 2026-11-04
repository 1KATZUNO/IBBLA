<?php

namespace Tests\Feature;

use App\Models\ActivacionWhatsApp;
use App\Models\Persona;
use App\Services\WhatsApp\WhatsAppEnvioException;
use App\Services\WhatsApp\WhatsAppNoConfiguradoException;
use App\Services\WhatsApp\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use LogicException;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * La capa de proveedor con el driver callmebot: la clave es de cada receptor (persona o usuario), así
 * que el servicio resuelve a quién se le puede enviar solo y a quién se le sigue ofreciendo wa.me.
 * Todo con Http::fake: nunca se llama a CallMeBot de verdad.
 */
class ServicioWhatsAppPorReceptorTest extends TestCase
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
        config(['whatsapp.driver' => 'callmebot']);
        $this->crearIglesiaConAdmin();
        $this->ana = $this->crearPersona('Ana Mora', '8888-7777');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function servicio(): WhatsAppService
    {
        return new WhatsAppService();
    }

    // ---------------------------------------------------------------- quién puede recibir solo

    public function test_sin_activacion_no_se_le_puede_enviar_solo_y_queda_el_enlace_manual(): void
    {
        $servicio = $this->servicio();

        $this->assertFalse($servicio->puedeEnviarA($this->ana));
        $this->assertNull($servicio->destinatario($this->ana)->activacion);
        $this->assertSame('50688887777', $servicio->destinatario($this->ana)->telefono);
        $this->assertStringStartsWith('https://wa.me/50688887777?text=', (string) $servicio->enlace($this->ana->telefono, 'Hola'));
    }

    public function test_con_activacion_se_le_puede_enviar_solo(): void
    {
        $this->activarWhatsApp($this->ana);

        $destinatario = $this->servicio()->destinatario($this->ana->fresh());

        $this->assertTrue($destinatario->tieneClave());
        $this->assertSame('Ana Mora', $destinatario->nombre);
        $this->assertTrue($this->servicio()->puedeEnviarA($this->ana->fresh()));
    }

    public function test_la_activacion_de_un_usuario_funciona_igual(): void
    {
        $tesorero = $this->crearLider('tesorero', '8888-1002', 'Teresa Tesorera');
        $this->activarWhatsApp($tesorero, '654321');

        $destinatario = $this->servicio()->destinatario($tesorero->fresh());

        $this->assertTrue($destinatario->tieneClave());
        $this->assertSame('Teresa Tesorera', $destinatario->nombre);
        $this->assertTrue($this->servicio()->puedeEnviarA($tesorero->fresh()));
    }

    public function test_una_activacion_desactivada_no_sirve(): void
    {
        $this->activarWhatsApp($this->ana, '123456', null, ['activo' => false, 'apikey' => null, 'revocado_at' => now()]);

        $this->assertFalse($this->servicio()->puedeEnviarA($this->ana->fresh()));
    }

    public function test_si_la_persona_cambio_de_telefono_la_clave_vieja_ya_no_sirve(): void
    {
        $this->activarWhatsApp($this->ana);
        $this->ana->update(['telefono' => '7000-1111']);
        Http::fake();

        $this->assertFalse($this->servicio()->puedeEnviarA($this->ana->fresh()));
        $this->assertSame(ActivacionWhatsApp::ESTADO_NUMERO_CAMBIO, $this->ana->fresh()->activacionWhatsApp->estadoFrente('50670001111'));

        try {
            $this->servicio()->enviarA($this->ana->fresh(), 'Hola');
            $this->fail('Debió lanzar WhatsAppNoConfiguradoException');
        } catch (WhatsAppNoConfiguradoException) {
            Http::assertNothingSent();
        }
    }

    public function test_si_el_telefono_registrado_dejo_de_servir_tampoco_se_envia(): void
    {
        $this->activarWhatsApp($this->ana);
        $this->ana->update(['telefono' => 'no tiene']);
        Http::fake();

        $this->assertFalse($this->servicio()->puedeEnviarA($this->ana->fresh()));

        $this->expectException(InvalidArgumentException::class);
        $this->servicio()->enviarA($this->ana->fresh(), 'Hola');
    }

    public function test_una_clave_que_no_se_puede_descifrar_no_rompe_nada(): void
    {
        $this->activarWhatsApp($this->ana);
        // Simula una APP_KEY distinta: el valor guardado ya no se puede descifrar.
        DB::table('activaciones_whatsapp')->update(['apikey' => 'esto-no-es-un-payload-cifrado']);
        Http::fake();

        $activacion = $this->ana->fresh()->activacionWhatsApp;
        $this->assertNull($activacion->clave());
        $this->assertSame(ActivacionWhatsApp::ESTADO_ILEGIBLE, $activacion->estadoFrente('50688887777'));
        $this->assertFalse($this->servicio()->puedeEnviarA($this->ana->fresh()));
        Http::assertNothingSent();
    }

    public function test_con_el_driver_link_la_activacion_no_cuenta_y_con_meta_manda_el_driver(): void
    {
        $this->activarWhatsApp($this->ana);

        config(['whatsapp.driver' => 'link']);
        $this->assertFalse($this->servicio()->usaClavePorReceptor());
        $this->assertFalse($this->servicio()->puedeEnviarA($this->ana->fresh()));

        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => 'T', 'whatsapp.meta.phone_number_id' => '555']);
        $this->assertTrue($this->servicio()->puedeEnviarA($this->ana->fresh()), 'Con meta el envío es global, no por clave');
        $sinClave = $this->crearPersona('Beto Rojas', '7777-6666');
        $this->assertTrue($this->servicio()->puedeEnviarA($sinClave));
    }

    // ---------------------------------------------------------------- envío

    public function test_el_envio_sale_con_la_clave_y_el_numero_de_la_activacion(): void
    {
        $this->activarWhatsApp($this->ana, '987654');
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);

        $this->servicio()->enviarA($this->ana->fresh(), "Hola Ana\nSu informe");

        Http::assertSentCount(1);
        Http::assertSent(function (Request $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return $q['phone'] === '+50688887777' && $q['apikey'] === '987654' && $q['text'] === "Hola Ana\nSu informe";
        });

        $activacion = $this->ana->fresh()->activacionWhatsApp;
        $this->assertTrue($activacion->ultimo_envio_at->equalTo(now()));
        $this->assertNull($activacion->ultimo_error);
        $this->assertNull($activacion->verificado_at, 'Un envío normal no cuenta como prueba');
    }

    public function test_una_prueba_exitosa_deja_la_activacion_verificada(): void
    {
        $this->activarWhatsApp($this->ana);
        Http::fake(['*' => Http::response(self::EXITO, 200)]);

        $this->servicio()->enviarA($this->ana->fresh(), 'Prueba', true);

        $this->assertTrue($this->ana->fresh()->activacionWhatsApp->verificado_at->equalTo(now()));
    }

    public function test_si_falla_el_error_queda_guardado_sin_la_clave_y_se_vuelve_a_lanzar(): void
    {
        $this->activarWhatsApp($this->ana, '987654');
        Http::fake(['*' => Http::response('ERROR: APIKey 987654 is invalid', 200)]);

        try {
            $this->servicio()->enviarA($this->ana->fresh(), 'Hola', true);
            $this->fail('Debió lanzar WhatsAppEnvioException');
        } catch (WhatsAppEnvioException $e) {
            $this->assertStringContainsString('APIKey *** is invalid', $e->getMessage());
        }

        $activacion = $this->ana->fresh()->activacionWhatsApp;
        $this->assertStringContainsString('APIKey *** is invalid', (string) $activacion->ultimo_error);
        $this->assertStringNotContainsString('987654', (string) $activacion->ultimo_error);
        $this->assertNull($activacion->verificado_at);
        $this->assertTrue($activacion->ultimo_envio_at->equalTo(now()));
    }

    public function test_un_envio_exitoso_borra_el_error_anterior(): void
    {
        $this->activarWhatsApp($this->ana, '123456', null, ['ultimo_error' => 'CallMeBot respondió 503.']);
        Http::fake(['*' => Http::response(self::EXITO, 200)]);

        $this->servicio()->enviarA($this->ana->fresh(), 'Hola');

        $this->assertNull($this->ana->fresh()->activacionWhatsApp->ultimo_error);
    }

    public function test_sin_activacion_enviar_lanza_una_excepcion_clara_y_no_toca_la_red(): void
    {
        Http::fake();

        try {
            $this->servicio()->enviarA($this->ana, 'Hola');
            $this->fail('Debió lanzar WhatsAppNoConfiguradoException');
        } catch (WhatsAppNoConfiguradoException $e) {
            $this->assertStringContainsString('Ana Mora no tiene activado WhatsApp automático', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_con_meta_enviar_a_manda_por_la_api_al_telefono_del_receptor(): void
    {
        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => 'T', 'whatsapp.meta.phone_number_id' => '555']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $this->servicio()->enviarA($this->ana, 'Hola Ana');

        Http::assertSent(fn (Request $r) => $r['to'] === '50688887777' && $r['text']['body'] === 'Hola Ana');
    }

    // ---------------------------------------------------------------- el registro de la activación

    public function test_la_clave_se_guarda_cifrada_y_no_sale_al_serializar(): void
    {
        $activacion = $this->activarWhatsApp($this->ana, '987654');

        $this->assertNotSame('987654', DB::table('activaciones_whatsapp')->value('apikey'));
        $this->assertSame('987654', $activacion->fresh()->clave());
        $this->assertArrayNotHasKey('apikey', $activacion->toArray());
        $this->assertStringNotContainsString('987654', $activacion->toJson());
    }

    public function test_una_activacion_es_de_una_persona_o_de_un_usuario_no_de_ambos_ni_de_ninguno(): void
    {
        $base = ['telefono' => '50688887777', 'apikey' => '123456', 'activo' => true];

        foreach ([['persona_id' => $this->ana->id, 'user_id' => $this->admin->id], []] as $propietario) {
            try {
                ActivacionWhatsApp::create($base + $propietario);
                $this->fail('Debió lanzar LogicException');
            } catch (LogicException $e) {
                $this->assertStringContainsString('de una persona o de un usuario', $e->getMessage());
            }
        }

        $this->assertSame(0, ActivacionWhatsApp::withoutGlobalScopes()->count());
    }

    public function test_hay_una_sola_activacion_por_persona_y_se_borra_con_ella(): void
    {
        $this->activarWhatsApp($this->ana);

        try {
            $this->activarWhatsApp($this->ana, '999999');
            $this->fail('Debió fallar por la restricción única');
        } catch (\Illuminate\Database\QueryException) {
            // esperado: una fila por persona
        }

        $this->ana->delete();
        $this->assertSame(0, ActivacionWhatsApp::withoutGlobalScopes()->count());
    }
}

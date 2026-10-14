<?php

namespace Tests\Feature;

use App\Models\Persona;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * php artisan whatsapp:probar <telefono>: manda un mensaje de prueba por CallMeBot a un receptor que ya
 * activó WhatsApp. Todo con Http::fake: nunca se llama al servicio real.
 */
class ProbarWhatsAppComandoTest extends TestCase
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
        $this->ana = $this->crearPersona('Ana Mora', '8888-7777');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_manda_el_mensaje_de_prueba_con_la_clave_del_receptor(): void
    {
        $this->activarWhatsApp($this->ana, 'CLAVEANA');
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);

        $this->artisan('whatsapp:probar', ['telefono' => '8888-7777'])
            ->expectsOutputToContain('Mensaje enviado a Ana Mora (persona, +506 8888-7777)')
            ->doesntExpectOutputToContain('CLAVEANA')
            ->assertExitCode(0);

        Http::assertSentCount(1);
        Http::assertSent(function (ClientRequest $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return $q['phone'] === '+50688887777' && $q['apikey'] === 'CLAVEANA'
                && str_contains($q['text'], 'Hola Ana Mora, esta es una prueba de Iglesia de Prueba');
        });
        $this->assertNotNull($this->ana->fresh()->activacionWhatsApp->verificado_at, 'Una prueba exitosa deja la activación verificada');
    }

    public function test_acepta_el_telefono_en_cualquiera_de_sus_formas(): void
    {
        $this->activarWhatsApp($this->ana);
        Http::fake(['*' => Http::response(self::EXITO, 200)]);

        foreach (['88887777', '+506 8888-7777', '506-8888-7777'] as $telefono) {
            $this->artisan('whatsapp:probar', ['telefono' => $telefono])->assertExitCode(0);
        }

        Http::assertSentCount(3);
    }

    public function test_un_mensaje_propio_reemplaza_al_de_prueba(): void
    {
        $this->activarWhatsApp($this->ana);
        Http::fake(['*' => Http::response(self::EXITO, 200)]);

        $this->artisan('whatsapp:probar', ['telefono' => '8888-7777', '--mensaje' => 'Hola, es una prueba del equipo'])->assertExitCode(0);

        Http::assertSent(function (ClientRequest $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return $q['text'] === 'Hola, es una prueba del equipo';
        });
    }

    public function test_simular_no_llama_a_callmebot_ni_cambia_nada(): void
    {
        $this->activarWhatsApp($this->ana, 'CLAVEANA');
        Http::fake();

        $this->artisan('whatsapp:probar', ['telefono' => '8888-7777', '--simular' => true])
            ->expectsOutputToContain('[simulación] Se le mandaría a Ana Mora (persona, +506 8888-7777)')
            ->expectsOutputToContain('Hola Ana Mora, esta es una prueba de Iglesia de Prueba')
            ->doesntExpectOutputToContain('CLAVEANA')
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertNull($this->ana->fresh()->activacionWhatsApp->verificado_at);
        $this->assertNull($this->ana->fresh()->activacionWhatsApp->ultimo_envio_at);
    }

    public function test_si_callmebot_falla_lo_dice_sin_la_clave_y_sale_con_error(): void
    {
        $this->activarWhatsApp($this->ana, 'CLAVEANA');
        Http::fake(['*' => Http::response('ERROR: APIKey CLAVEANA is invalid', 200)]);

        $this->artisan('whatsapp:probar', ['telefono' => '8888-7777'])
            ->expectsOutputToContain('No se pudo mandar a Ana Mora (persona, +506 8888-7777): CallMeBot respondió 200: ERROR: APIKey *** is invalid')
            ->doesntExpectOutputToContain('CLAVEANA')
            ->assertExitCode(1);

        $this->assertNull($this->ana->fresh()->activacionWhatsApp->verificado_at);
    }

    public function test_un_telefono_sin_activacion_no_manda_nada_y_explica_que_hacer(): void
    {
        Http::fake();

        $this->artisan('whatsapp:probar', ['telefono' => '8888-7777'])
            ->expectsOutputToContain('No hay ningún receptor con WhatsApp activado en el +506 8888-7777')
            ->expectsOutputToContain('tiene que activarlo primero')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_un_telefono_que_no_sirve_se_rechaza(): void
    {
        Http::fake();

        $this->artisan('whatsapp:probar', ['telefono' => 'abc'])
            ->expectsOutputToContain('El teléfono «abc» no es válido')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_con_otro_driver_no_hace_nada(): void
    {
        $this->activarWhatsApp($this->ana);
        config(['whatsapp.driver' => 'link']);
        Http::fake();

        $this->artisan('whatsapp:probar', ['telefono' => '8888-7777'])
            ->expectsOutputToContain('WHATSAPP_DRIVER es «link»')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_una_activacion_desactivada_no_cuenta(): void
    {
        $this->activarWhatsApp($this->ana, '123456', null, ['activo' => false, 'apikey' => null, 'revocado_at' => now()]);
        Http::fake();

        $this->artisan('whatsapp:probar', ['telefono' => '8888-7777'])->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_si_la_persona_cambio_de_telefono_avisa_que_hay_que_activar_de_nuevo(): void
    {
        $this->activarWhatsApp($this->ana);
        $this->ana->update(['telefono' => '7000-1111']);
        Http::fake();

        $this->artisan('whatsapp:probar', ['telefono' => '8888-7777'])
            ->expectsOutputToContain('su clave no está al día')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_tambien_prueba_a_un_usuario_del_sistema(): void
    {
        $tesorero = $this->crearLider('tesorero', '8888-1002', 'Teresa Tesorera');
        $this->activarWhatsApp($tesorero, 'CLAVETES');
        Http::fake(['*' => Http::response(self::EXITO, 200)]);

        $this->artisan('whatsapp:probar', ['telefono' => '8888-1002'])
            ->expectsOutputToContain('Mensaje enviado a Teresa Tesorera (usuario, +506 8888-1002)')
            ->assertExitCode(0);

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), 'apikey=CLAVETES'));
    }

    public function test_una_persona_y_un_usuario_con_el_mismo_numero_y_clave_reciben_un_solo_mensaje(): void
    {
        $this->activarWhatsApp($this->ana, 'MISMACLAVE');
        $this->admin->update(['telefono' => '8888-7777']);
        $this->activarWhatsApp($this->admin, 'MISMACLAVE');
        Http::fake(['*' => Http::response(self::EXITO, 200)]);

        $this->artisan('whatsapp:probar', ['telefono' => '8888-7777'])->expectsOutputToContain('Ya se atendió ese número')->assertExitCode(0);

        Http::assertSentCount(1);
    }
}

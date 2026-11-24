<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\CallMeBotDriver;
use App\Services\WhatsApp\WhatsAppEnvioException;
use App\Services\WhatsApp\WhatsAppNoConfiguradoException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * CallMeBot es un servicio gratuito de un tercero sin garantía: se prueba SIEMPRE con Http::fake
 * (nunca se llama al servicio real) y se vigila que la clave y el mensaje no salgan en los errores.
 */
class CallMeBotDriverTest extends TestCase
{
    private const EXITO = '<p>Message queued. You will receive it in a few seconds.</p>';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function driver(array $config = []): CallMeBotDriver
    {
        return new CallMeBotDriver($config);
    }

    /** @return array<string, string> */
    private function consulta(Request $peticion): array
    {
        parse_str((string) parse_url($peticion->url(), PHP_URL_QUERY), $consulta);

        return $consulta;
    }

    public function test_envia_un_get_con_telefono_con_mas_texto_y_clave(): void
    {
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);

        $this->driver()->enviarConClave('50688887777', 'Hola Ana, ¡Dios le bendiga! ₡5', '123456');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $r) {
            $q = $this->consulta($r);

            return $r->method() === 'GET'
                && str_starts_with($r->url(), 'https://api.callmebot.com/whatsapp.php?')
                && $q['phone'] === '+50688887777'
                && $q['text'] === 'Hola Ana, ¡Dios le bendiga! ₡5'
                && $q['apikey'] === '123456';
        });
    }

    public function test_los_saltos_de_linea_y_los_enlaces_viajan_codificados(): void
    {
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);
        $mensaje = "Línea 1\n\nInforme: https://ibbla.eu/informe/5?expires=1&signature=abc";

        $this->driver()->enviarConClave('50688887777', $mensaje, '123456');

        Http::assertSent(function (Request $r) use ($mensaje) {
            return str_contains($r->url(), '%0A%0A')
                && ! str_contains($r->url(), 'signature=abc')   // el & y el = del enlace van codificados
                && $this->consulta($r)['text'] === $mensaje;
        });
    }

    public function test_aplica_los_tiempos_de_espera_de_la_configuracion(): void
    {
        $opciones = [];
        Http::fake(function ($peticion, array $o) use (&$opciones) {
            $opciones = $o;

            return Http::response(self::EXITO, 200);
        });

        $this->driver(['connect_timeout' => 2, 'timeout' => 7])->enviarConClave('50688887777', 'Hola', '123456');

        $this->assertEquals(2, $opciones['connect_timeout']);
        $this->assertEquals(7, $opciones['timeout']);
    }

    public function test_la_configuracion_por_defecto_es_3_segundos_para_conectar_y_10_para_responder(): void
    {
        $this->assertSame(3, config('whatsapp.callmebot.connect_timeout'));
        $this->assertSame(10, config('whatsapp.callmebot.timeout'));
        $this->assertSame('https://api.callmebot.com/whatsapp.php', config('whatsapp.callmebot.url'));
    }

    public function test_usa_la_url_por_defecto_si_la_configuracion_no_la_trae(): void
    {
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);

        $this->driver(['url' => null])->enviarConClave('50688887777', 'Hola', '123456');

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api.callmebot.com/whatsapp.php?'));
    }

    public function test_callmebot_responde_200_aunque_falle_y_eso_cuenta_como_error(): void
    {
        Http::fake(['*' => Http::response('ERROR: APIKey is invalid or phone number not registered', 200)]);

        try {
            $this->driver()->enviarConClave('50688887777', 'Hola', '123456');
            $this->fail('Debió lanzar WhatsAppEnvioException');
        } catch (WhatsAppEnvioException $e) {
            $this->assertStringContainsString('CallMeBot respondió 200', $e->getMessage());
            $this->assertStringContainsString('APIKey is invalid', $e->getMessage());
        }
    }

    public function test_message_queued_se_reconoce_sin_importar_mayusculas_ni_el_html(): void
    {
        Http::fake(['*' => Http::response('<html><body><b>MESSAGE QUEUED</b>. Wait...</body></html>', 200)]);

        $this->driver()->enviarConClave('50688887777', 'Hola', '123456');

        $this->addToAssertionCount(1);
    }

    public function test_un_texto_con_la_palabra_error_no_cuenta_como_falla_si_se_encolo(): void
    {
        // El cuerpo repite el mensaje enviado: un aviso que diga «error» no debe confundirse con un fallo.
        Http::fake(['*' => Http::response('<p>Message queued. Text: Corrección de error en el plano</p>', 200)]);

        $this->driver()->enviarConClave('50688887777', 'Corrección de error en el plano', '123456');

        $this->addToAssertionCount(1);
    }

    public function test_un_error_http_tambien_es_falla(): void
    {
        Http::fake(['*' => Http::response('Service unavailable', 503)]);

        $this->expectException(WhatsAppEnvioException::class);
        $this->expectExceptionMessage('CallMeBot respondió 503: Service unavailable');

        $this->driver()->enviarConClave('50688887777', 'Hola', '123456');
    }

    public function test_el_error_no_repite_la_clave_ni_el_mensaje_ni_los_enlaces(): void
    {
        $mensaje = 'Su informe: https://ibbla.eu/informe/5?expires=1&signature=SECRETA';
        Http::fake(['*' => Http::response(
            '<p>APIKey 987654 is invalid. Text: '.$mensaje.' (see https://www.callmebot.com/help)</p>',
            200,
        )]);

        try {
            $this->driver()->enviarConClave('50688887777', $mensaje, '987654');
            $this->fail('Debió lanzar WhatsAppEnvioException');
        } catch (WhatsAppEnvioException $e) {
            $this->assertStringNotContainsString('987654', $e->getMessage());
            $this->assertStringNotContainsString('SECRETA', $e->getMessage());
            $this->assertStringNotContainsString('https://', $e->getMessage());
            $this->assertStringContainsString('APIKey *** is invalid', $e->getMessage());
        }
    }

    public function test_un_error_de_conexion_no_filtra_la_clave_y_no_encadena_la_excepcion_original(): void
    {
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 28: Connection timed out for https://api.callmebot.com/whatsapp.php?phone=%2B506&apikey=987654'
        ));

        try {
            $this->driver()->enviarConClave('50688887777', 'Hola', '987654');
            $this->fail('Debió lanzar WhatsAppEnvioException');
        } catch (WhatsAppEnvioException $e) {
            $this->assertStringStartsWith('No se pudo contactar a CallMeBot', $e->getMessage());
            $this->assertStringNotContainsString('987654', $e->getMessage());
            $this->assertNull($e->getPrevious(), 'La excepción original lleva la URL con la clave: no debe viajar en el log');
        }
    }

    public function test_el_detalle_del_error_se_corta_a_200_caracteres(): void
    {
        Http::fake(['*' => Http::response('<p>'.str_repeat('x', 900).'</p>', 200)]);

        try {
            $this->driver()->enviarConClave('50688887777', 'Hola', '123456');
            $this->fail('Debió lanzar WhatsAppEnvioException');
        } catch (WhatsAppEnvioException $e) {
            $this->assertLessThan(260, mb_strlen($e->getMessage()));
        }
    }

    public function test_sin_clave_no_toca_la_red(): void
    {
        Http::fake();

        foreach (['', '   '] as $clave) {
            try {
                $this->driver()->enviarConClave('50688887777', 'Hola', $clave);
                $this->fail('Debió lanzar WhatsAppNoConfiguradoException');
            } catch (WhatsAppNoConfiguradoException $e) {
                $this->assertStringContainsString('clave personal', $e->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_sin_telefono_o_sin_texto_tampoco_envia(): void
    {
        Http::fake();

        foreach ([['', 'Hola'], ['50688887777', '  ']] as [$telefono, $mensaje]) {
            try {
                $this->driver()->enviarConClave($telefono, $mensaje, '123456');
                $this->fail('Debió lanzar WhatsAppNoConfiguradoException');
            } catch (WhatsAppNoConfiguradoException) {
                // esperado
            }
        }

        Http::assertNothingSent();
    }

    public function test_el_envio_global_no_existe_porque_la_clave_es_de_cada_receptor(): void
    {
        Http::fake();
        $driver = $this->driver();

        $this->assertSame('callmebot', $driver->nombre());
        $this->assertFalse($driver->puedeEnviar());

        try {
            $driver->enviar('50688887777', 'Hola');
            $this->fail('Debió lanzar WhatsAppNoConfiguradoException');
        } catch (WhatsAppNoConfiguradoException $e) {
            $this->assertStringContainsString('CallMeBot', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_el_enlace_de_respaldo_sigue_siendo_wa_me(): void
    {
        $this->assertSame('https://wa.me/50688887777?text=Hola', $this->driver()->enlace('50688887777', 'Hola'));
    }

    public function test_el_servicio_lo_elige_con_whatsapp_driver_callmebot(): void
    {
        config(['whatsapp.driver' => 'callmebot']);
        $servicio = new WhatsAppService();

        $this->assertInstanceOf(CallMeBotDriver::class, $servicio->driver());
        $this->assertTrue($servicio->usaClavePorReceptor());
        $this->assertFalse($servicio->puedeEnviar());

        config(['whatsapp.driver' => 'link']);
        $this->assertFalse((new WhatsAppService())->usaClavePorReceptor());
    }

    public function test_la_respuesta_real_de_callmebot_se_reconoce_como_exito(): void
    {
        // Cuerpo observado el 13/10/2026 (HTTP 200, text/html): etiquetas pegadas y el texto repetido.
        Http::fake(['api.callmebot.com/*' => Http::response(
            '<p>Message to: +50688887777<p>Text to send: Prueba de IBBLA ✅ CallMeBot funciona<p><b>Message queued.</b> You will receive it in a few seconds.',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        )]);

        $this->driver()->enviarConClave('50688887777', 'Prueba de IBBLA ✅ CallMeBot funciona', '123456');

        $this->addToAssertionCount(1);
    }

    public function test_un_error_con_el_mismo_formato_no_repite_a_quien_ni_el_texto_enviado(): void
    {
        Http::fake(['*' => Http::response(
            '<p>Message to: +50688887777<p>Text to send: Hola Ana, su informe<p><b>ERROR</b>: APIKey 987654 is invalid or phone number not registered',
            200,
        )]);

        try {
            $this->driver()->enviarConClave('50688887777', 'Hola Ana, su informe', '987654');
            $this->fail('Debió lanzar WhatsAppEnvioException');
        } catch (WhatsAppEnvioException $e) {
            $this->assertSame('CallMeBot respondió 200: ERROR : APIKey *** is invalid or phone number not registered.', $e->getMessage());
            $this->assertStringNotContainsString('Message to', $e->getMessage());
            $this->assertStringNotContainsString('+50688887777', $e->getMessage());
            $this->assertStringNotContainsString('Hola Ana', $e->getMessage());
        }
    }
}

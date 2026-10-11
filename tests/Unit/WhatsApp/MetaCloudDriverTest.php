<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\LinkDriver;
use App\Services\WhatsApp\MetaCloudDriver;
use App\Services\WhatsApp\WhatsAppEnvioException;
use App\Services\WhatsApp\WhatsAppNoConfiguradoException;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El driver de Meta no se usa hoy, pero tiene que estar probado para el día que haya
 * credenciales: arma bien la petición a la Cloud API y, sin credenciales, falla claro
 * y sin tocar la red.
 */
class MetaCloudDriverTest extends TestCase
{
    private function credenciales(array $extra = []): array
    {
        return $extra + ['token' => 'TOKEN-SECRETO', 'phone_number_id' => '1234567890', 'version' => 'v21.0'];
    }

    public function test_sin_credenciales_lanza_excepcion_clara_y_no_toca_la_red(): void
    {
        Http::fake();
        $driver = new MetaCloudDriver(['token' => '', 'phone_number_id' => null]);

        $this->assertFalse($driver->puedeEnviar());

        try {
            $driver->enviar('50688887777', 'Hola');
            $this->fail('Debió lanzar WhatsAppNoConfiguradoException');
        } catch (WhatsAppNoConfiguradoException $e) {
            $this->assertStringContainsString('WHATSAPP_META_TOKEN', $e->getMessage());
            $this->assertStringContainsString('WHATSAPP_META_PHONE_NUMBER_ID', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_envia_texto_a_la_cloud_api_v21(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]], 200)]);

        (new MetaCloudDriver($this->credenciales()))->enviar('50688887777', 'Hola Ana');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $r) {
            return $r->url() === 'https://graph.facebook.com/v21.0/1234567890/messages'
                && $r->method() === 'POST'
                && $r->hasHeader('Authorization', 'Bearer TOKEN-SECRETO')
                && $r['messaging_product'] === 'whatsapp'
                && $r['to'] === '50688887777'
                && $r['type'] === 'text'
                && $r['text']['body'] === 'Hola Ana';
        });
    }

    public function test_con_plantilla_configurada_el_mensaje_viaja_como_parametro_sin_saltos_de_linea(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => []], 200)]);

        (new MetaCloudDriver($this->credenciales(['plantilla' => 'aviso_iglesia', 'idioma_plantilla' => 'es'])))
            ->enviar('50688887777', "Línea uno\n\nLínea   dos");

        Http::assertSent(function (Request $r) {
            return $r['type'] === 'template'
                && $r['template']['name'] === 'aviso_iglesia'
                && $r['template']['language']['code'] === 'es'
                && $r['template']['components'][0]['type'] === 'body'
                && $r['template']['components'][0]['parameters'][0]['text'] === 'Línea uno Línea dos';
        });
    }

    public function test_si_meta_rechaza_el_mensaje_el_error_trae_el_detalle_pero_no_el_token(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(
            ['error' => ['message' => 'Invalid OAuth access token.', 'code' => 190]],
            401,
        )]);

        try {
            (new MetaCloudDriver($this->credenciales()))->enviar('50688887777', 'Hola');
            $this->fail('Debió lanzar WhatsAppEnvioException');
        } catch (WhatsAppEnvioException $e) {
            $this->assertStringContainsString('401', $e->getMessage());
            $this->assertStringContainsString('Invalid OAuth access token.', $e->getMessage());
            $this->assertStringContainsString('190', $e->getMessage());
            $this->assertStringNotContainsString('TOKEN-SECRETO', $e->getMessage());
        }
    }

    public function test_si_no_hay_conexion_lanza_error_de_envio(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

        $this->expectException(WhatsAppEnvioException::class);
        $this->expectExceptionMessage('No se pudo contactar la Cloud API');
        (new MetaCloudDriver($this->credenciales()))->enviar('50688887777', 'Hola');
    }

    public function test_el_enlace_wa_me_sigue_disponible_como_respaldo_manual(): void
    {
        $this->assertSame(
            'https://wa.me/50688887777?text=Hola',
            (new MetaCloudDriver($this->credenciales()))->enlace('50688887777', 'Hola'),
        );
    }

    public function test_el_servicio_elige_el_driver_segun_la_configuracion(): void
    {
        config(['whatsapp.driver' => 'link']);
        $this->assertInstanceOf(LinkDriver::class, WhatsAppService::driverDesdeConfig());

        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => 'T', 'whatsapp.meta.phone_number_id' => '99']);
        $driver = (new WhatsAppService())->driver();
        $this->assertInstanceOf(MetaCloudDriver::class, $driver);
        $this->assertTrue($driver->puedeEnviar());

        // Un valor mal escrito cae en el driver seguro en vez de romper.
        config(['whatsapp.driver' => 'metta']);
        $this->assertInstanceOf(LinkDriver::class, WhatsAppService::driverDesdeConfig());

        config(['whatsapp.driver' => ' META ']);
        $this->assertInstanceOf(MetaCloudDriver::class, WhatsAppService::driverDesdeConfig());
    }

    public function test_por_defecto_el_driver_es_link_y_no_envia_solo(): void
    {
        // El valor por defecto vive en el archivo de config (el .env de cada máquina puede cambiarlo).
        $this->assertStringContainsString("env('WHATSAPP_DRIVER', 'link')", file_get_contents(config_path('whatsapp.php')));

        config(['whatsapp.driver' => 'link']);
        $this->assertFalse(app(WhatsAppService::class)->puedeEnviar());
    }

    public function test_el_servicio_normaliza_el_telefono_antes_de_enviar_por_meta(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => []], 200)]);

        (new WhatsAppService(new MetaCloudDriver($this->credenciales())))->enviar('8888-7777', 'Hola');

        Http::assertSent(fn (Request $r) => $r['to'] === '50688887777');
    }
}

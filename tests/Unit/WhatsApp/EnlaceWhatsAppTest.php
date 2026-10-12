<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\LinkDriver;
use App\Services\WhatsApp\WhatsAppNoConfiguradoException;
use App\Services\WhatsApp\WhatsAppService;
use PHPUnit\Framework\TestCase;

/** Construcción del enlace wa.me y armado de mensajes desde plantillas. Sin aplicación ni base de datos. */
class EnlaceWhatsAppTest extends TestCase
{
    private function servicio(): WhatsAppService
    {
        return new WhatsAppService(new LinkDriver());
    }

    public function test_arma_el_enlace_wa_me_con_el_mensaje_codificado(): void
    {
        $mensaje = "Hola Ana, mira esto: https://ejemplo.com/a?b=1&c=2\n¡Dios le bendiga!";

        $this->assertSame(
            'https://wa.me/50688887777?text=Hola%20Ana%2C%20mira%20esto%3A%20https%3A%2F%2Fejemplo.com%2Fa%3Fb%3D1%26c%3D2%0A%C2%A1Dios%20le%20bendiga%21',
            $this->servicio()->enlace('8888-7777', $mensaje),
        );
    }

    public function test_el_mensaje_se_recupera_intacto_al_decodificar_el_enlace(): void
    {
        $mensaje = "Línea 1\nLínea 2 con & y = y + y # y % y ñ y 100% «comillas»";

        $url = $this->servicio()->enlace('+506 8888 7777', $mensaje);

        $this->assertSame('wa.me', parse_url($url, PHP_URL_HOST));
        $this->assertSame('/50688887777', parse_url($url, PHP_URL_PATH));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($mensaje, $query['text']);
    }

    public function test_el_enlace_normaliza_el_telefono_antes_de_armarse(): void
    {
        foreach (['8888-7777', '+506 8888-7777', '506 8888 7777', '(506) 88887777'] as $telefono) {
            $this->assertSame('https://wa.me/50688887777?text=Hola', $this->servicio()->enlace($telefono, 'Hola'), $telefono);
        }
    }

    public function test_sin_mensaje_el_enlace_solo_abre_el_chat(): void
    {
        $this->assertSame('https://wa.me/50688887777', $this->servicio()->enlace('88887777', ''));
    }

    public function test_con_telefono_invalido_no_hay_enlace(): void
    {
        $this->assertNull($this->servicio()->enlace('no tiene', 'Hola'));
        $this->assertNull($this->servicio()->enlace(null, 'Hola'));
        $this->assertNull($this->servicio()->enlace('1234-5678', 'Hola'));
    }

    public function test_el_driver_link_no_envia_nada_por_si_solo(): void
    {
        $servicio = $this->servicio();

        $this->assertFalse($servicio->puedeEnviar());
        $this->assertSame('link', $servicio->driver()->nombre());

        $this->expectException(WhatsAppNoConfiguradoException::class);
        $this->expectExceptionMessage('no envía mensajes por sí solo');
        $servicio->enviar('8888-7777', 'Hola');
    }

    public function test_enviar_con_telefono_invalido_lanza_error_claro(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('teléfono no es válido');
        $this->servicio()->enviar('abc', 'Hola');
    }

    public function test_arma_el_mensaje_desde_la_plantilla(): void
    {
        $plantilla = "Hola {nombre}, le saluda {iglesia}.\nEnlace: {enlace} (vence el {vence}). {desconocida}";

        $this->assertSame(
            "Hola Ana Mora, le saluda Iglesia de Prueba.\nEnlace: https://x.test/i?a=1&b=2 (vence el 18 de octubre de 2026). {desconocida}",
            WhatsAppService::armarMensaje($plantilla, [
                'nombre' => 'Ana Mora',
                'iglesia' => 'Iglesia de Prueba',
                'enlace' => 'https://x.test/i?a=1&b=2',
                'vence' => '18 de octubre de 2026',
            ]),
        );
    }

    public function test_un_valor_con_llaves_no_se_vuelve_a_sustituir(): void
    {
        $this->assertSame(
            'Hola {enlace} -> https://x.test',
            WhatsAppService::armarMensaje('Hola {nombre} -> {enlace}', ['nombre' => '{enlace}', 'enlace' => 'https://x.test']),
        );
    }
}

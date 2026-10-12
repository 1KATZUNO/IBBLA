<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * Botón verde «Enviar por WhatsApp» (componente x-whatsapp-enviar) en la lista de personas, en el
 * detalle de la persona y en su pantalla de compromisos. Sin teléfono válido sale deshabilitado.
 */
class BotonWhatsAppPersonaTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearIglesiaConAdmin();
        $this->crearCategoriasPorDefecto();
    }

    public function test_la_lista_ofrece_el_boton_activo_a_quien_tiene_telefono(): void
    {
        $ana = $this->crearPersona('Ana Mora', '8888-7777');

        $respuesta = $this->actingAs($this->admin)->get(route('personas.index'));

        $respuesta->assertOk()
            ->assertSee('Enviar por WhatsApp')
            ->assertSee('href="'.route('personas.whatsapp', $ana).'"', false)
            ->assertSee('target="_blank"', false);
    }

    public function test_sin_telefono_el_boton_sale_deshabilitado_y_el_tooltip_dice_como_arreglarlo(): void
    {
        $juan = $this->crearPersona('Juan Pérez', null);

        $respuesta = $this->actingAs($this->admin)->get(route('personas.index'));

        $respuesta->assertOk()
            ->assertSee('Enviar por WhatsApp')
            ->assertSee('disabled', false)
            ->assertSee('Juan Pérez no tiene teléfono: edite la persona y agregue un número de 8 dígitos.')
            ->assertDontSee(route('personas.whatsapp', $juan), false);
    }

    public function test_con_un_telefono_que_no_sirve_el_tooltip_muestra_el_valor_guardado(): void
    {
        $rosa = $this->crearPersona('Rosa Solís', '8888-7777 / 2222-3333');

        $this->actingAs($this->admin)->get(route('personas.index'))
            ->assertSee('El teléfono guardado (8888-7777 / 2222-3333) no sirve para WhatsApp')
            ->assertDontSee(route('personas.whatsapp', $rosa), false);
    }

    public function test_cada_fila_decide_por_su_cuenta(): void
    {
        $ana = $this->crearPersona('Ana Mora', '8888-7777');
        $juan = $this->crearPersona('Juan Pérez', null);

        $html = $this->actingAs($this->admin)->get(route('personas.index'))->getContent();

        $this->assertSame(1, substr_count($html, 'href="'.route('personas.whatsapp', $ana).'"'));
        $this->assertStringNotContainsString(route('personas.whatsapp', $juan), $html);
        $this->assertSame(2, substr_count($html, 'Enviar por WhatsApp'), 'Una fila activa y una deshabilitada, ambas con el texto');
    }

    public function test_el_detalle_de_la_persona_y_su_pantalla_de_compromisos_tambien_lo_tienen(): void
    {
        $ana = $this->crearPersona('Ana Mora', '8888-7777');

        foreach ([route('personas.show', $ana), route('compromisos.show', $ana)] as $url) {
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertSee('Enviar por WhatsApp')
                ->assertSee('href="'.route('personas.whatsapp', $ana).'"', false);
        }
    }

    public function test_el_detalle_sin_telefono_lo_deja_deshabilitado(): void
    {
        $juan = $this->crearPersona('Juan Pérez', null);

        foreach ([route('personas.show', $juan), route('compromisos.show', $juan)] as $url) {
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertSee('Enviar por WhatsApp')
                ->assertSee('disabled', false)
                ->assertDontSee(route('personas.whatsapp', $juan), false);
        }
    }
}

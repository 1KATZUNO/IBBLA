<?php

namespace Tests\Feature\TarjetasQr;

use App\Support\Manual\Manual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioTarjetas;
use Tests\TestCase;

/** El "?" de las pantallas de tarjetas abre su seccion del manual y el buscador encuentra la descarga masiva. */
class TarjetasManualYBuscadorTest extends TestCase
{
    use CreaEscenarioTarjetas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Manual::olvidar();
        $this->armarIglesia();
    }

    public function test_el_boton_de_ayuda_abre_la_seccion_de_tarjetas(): void
    {
        foreach (['personas.tarjetas.index', 'personas.tarjetas.pdf', 'personas.tarjeta.show', 'personas.tarjeta.pdf'] as $ruta) {
            $this->assertSame('/manual/personas#tarjetas-con-qr-de-acceso', Manual::enlaceAyuda($ruta), $ruta);
        }
        $this->assertSame('/manual/mi-perfil#entrar-con-tu-tarjeta-qr', Manual::enlaceAyuda('acceso.tarjeta'));

        $this->actingAs($this->admin)->get(route('personas.tarjetas.index'))
            ->assertOk()
            ->assertSee('href="/manual/personas#tarjetas-con-qr-de-acceso"', false);
    }

    public function test_el_buscador_lleva_a_la_descarga_masiva_solo_al_administrador(): void
    {
        $titulos = fn ($usuario) => collect($this->actingAs($usuario)->getJson('/buscar?q=tarjetas')->assertOk()->json('grupos'))
            ->flatMap(fn ($g) => collect($g['items'])->pluck('url'))
            ->all();

        $this->assertContains(route('personas.tarjetas.index'), $titulos($this->admin));
        $this->assertNotContains(route('personas.tarjetas.index'), $titulos($this->crearUsuario($this->iglesia, 'tesorero')));
    }
}

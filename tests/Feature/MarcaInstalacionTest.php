<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * La marca que ve quien todavia no inicio sesion (pantalla de entrada y app instalable).
 * Una instalacion de UNA sola iglesia (ibbsc.co.uk) muestra la suya; una de varias iglesias (ibbla.eu)
 * no puede saber cual es antes del login y conserva la marca de siempre.
 */
class MarcaInstalacionTest extends TestCase
{
    use ConIglesia;
    use RefreshDatabase;

    public function test_con_una_sola_iglesia_la_entrada_lleva_su_nombre(): void
    {
        $iglesia = $this->crearIglesia('ibbsc', 'IBBSC');
        $iglesia->update(['nombre' => 'Iglesia Bíblica Bautista Santa Cruz']);

        $this->get('/login')->assertOk()
            ->assertSee('<title>Iniciar sesión · IBBSC</title>', false)
            ->assertSee('IBBSC')
            ->assertSee('Iglesia Bíblica Bautista Santa Cruz · Administración')
            ->assertDontSee('IBBLA')
            ->assertDontSee('La Aurora')
            ->assertSee('href="'.route('manifiesto').'"', false);

        // Las paginas publicas de la tarjeta QR usan el mismo encabezado
        $this->get('/t/'.str_repeat('x', 40))->assertNotFound()->assertSee('IBBSC')->assertDontSee('IBBLA');
    }

    public function test_una_iglesia_inactiva_no_cuenta(): void
    {
        $this->crearIglesia('ibbsc', 'IBBSC');
        $this->crearIglesia('vieja', 'VIEJA', false);

        $this->get('/login')->assertOk()->assertSee('IBBSC')->assertDontSee('VIEJA')->assertDontSee('IBBLA');
    }

    public function test_con_varias_iglesias_la_entrada_queda_como_siempre(): void
    {
        $this->crearIglesia('ibbla', 'IBBLA');
        $this->crearIglesia('ibbri', 'IBBRI');

        $this->get('/login')->assertOk()
            ->assertSee('<title>Iniciar sesión · IBBLA</title>', false)
            ->assertSee('Iglesia Bíblica Bautista en La Aurora · Administración')
            ->assertDontSee('IBBRI')
            ->assertSee('href="'.asset('manifest.json').'"', false)
            ->assertSee(asset('images/Logo.png'), false);
    }

    public function test_sin_ninguna_iglesia_tampoco_falla(): void
    {
        $this->get('/login')->assertOk()->assertSee('IBBLA');
    }

    public function test_el_manifiesto_de_una_sola_iglesia_lleva_su_marca(): void
    {
        $iglesia = $this->crearIglesia('ibbsc', 'IBBSC');
        $iglesia->update(['nombre' => 'Iglesia Bíblica Bautista Santa Cruz']);

        $respuesta = $this->get('/manifest.webmanifest')->assertOk();
        $this->assertStringContainsString('application/manifest+json', (string) $respuesta->headers->get('content-type'));
        $manifiesto = $respuesta->json();

        $this->assertSame('IBBSC', $manifiesto['short_name']);
        $this->assertSame('IBBSC · Administración de la iglesia', $manifiesto['name']);
        $this->assertStringContainsString('Iglesia Bíblica Bautista Santa Cruz', $manifiesto['description']);
        $this->assertSame('/', $manifiesto['start_url']);
        $this->assertNotEmpty($manifiesto['icons']);
    }

    public function test_el_manifiesto_con_varias_iglesias_es_el_archivo_de_siempre(): void
    {
        $this->crearIglesia('ibbla', 'IBBLA');
        $this->crearIglesia('ibbri', 'IBBRI');

        $this->assertSame(
            json_decode((string) file_get_contents(public_path('manifest.json')), true),
            $this->get('/manifest.webmanifest')->assertOk()->json(),
        );
    }

    public function test_con_sesion_iniciada_manda_la_iglesia_del_usuario(): void
    {
        $ibbla = $this->crearIglesia('ibbla', 'IBBLA');
        $this->crearIglesia('ibbri', 'IBBRI');
        $admin = $this->crearUsuario($ibbla, 'admin');

        $this->actingAs($admin);
        $this->assertSame($ibbla->id, iglesia_de_marca()?->id);
        $this->assertNull(iglesia_unica());
    }
}

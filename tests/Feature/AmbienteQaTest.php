<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Ambiente QA (qa.ibbla.eu): con APP_ENV=qa todas las pantallas llevan el aviso "AMBIENTE DE PRUEBAS"
 * y la cabecera X-Robots-Tag; en produccion y local no aparece ni una ni otra. Ver docs/AMBIENTE-QA.md.
 */
class AmbienteQaTest extends TestCase
{
    use ConIglesia;
    use RefreshDatabase;

    private const AVISO = 'AMBIENTE DE PRUEBAS';

    private Tenant $iglesia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia('ibbla-qa', 'QA');
    }

    private function enAmbiente(string $ambiente): void
    {
        $this->app['env'] = $ambiente;
    }

    public function test_en_qa_la_pantalla_de_ingreso_lleva_el_aviso_y_la_cabecera_noindex(): void
    {
        $this->enAmbiente('qa');

        $this->get('/login')
            ->assertOk()
            ->assertSee(self::AVISO)
            ->assertSee('los datos no son reales')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_en_qa_las_pantallas_de_la_app_llevan_el_aviso_y_la_cabecera_para_cada_rol(): void
    {
        $this->enAmbiente('qa');

        foreach (['admin' => '/cultos', 'tesorero' => '/recuento', 'asistente' => '/asistencia', 'miembro' => '/principal'] as $rol => $url) {
            $this->actingAs($this->crearUsuario($this->iglesia, $rol))
                ->get($url)
                ->assertOk()
                ->assertSee(self::AVISO)
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    public function test_en_qa_hasta_una_pagina_que_redirige_lleva_la_cabecera(): void
    {
        $this->enAmbiente('qa');

        $this->get('/manual')->assertRedirect('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_en_produccion_y_local_no_hay_aviso_ni_cabecera(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin');

        // Primero sin sesion (pantalla de ingreso) y despues con sesion: actingAs se queda para el resto de la prueba
        foreach (['production', 'local', 'testing'] as $ambiente) {
            $this->enAmbiente($ambiente);

            $login = $this->get('/login')->assertOk()->assertDontSee(self::AVISO);
            $this->assertFalse($login->headers->has('X-Robots-Tag'), "Cabecera noindex en {$ambiente}");
        }

        foreach (['production', 'local', 'testing'] as $ambiente) {
            $this->enAmbiente($ambiente);

            $cultos = $this->actingAs($admin)->get('/cultos')->assertOk()->assertDontSee(self::AVISO);
            $this->assertFalse($cultos->headers->has('X-Robots-Tag'), "Cabecera noindex en {$ambiente}");
        }
    }
}

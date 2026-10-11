<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Services\InformePersonaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * Enlace público firmado al informe de una persona (GET /informe/{persona}): se comparte por
 * WhatsApp, lo abre quien no tiene cuenta, y tiene que fallar con 403 si la firma venció o no coincide.
 */
class InformePublicoTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    private Persona $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearIglesiaConAdmin();
        $this->crearCategoriasPorDefecto();
        $this->ana = $this->crearPersona('Ana Mora');
        $this->crearSobre($this->crearCulto(now()->startOfYear()->addDays(10)->toDateString()), $this->ana, ['diezmo' => 5000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function enlace(?Persona $persona = null): string
    {
        return app(InformePersonaService::class)->urlFirmada($persona ?? $this->ana);
    }

    public function test_un_enlace_valido_devuelve_el_pdf_sin_iniciar_sesion(): void
    {
        $this->assertGuest();

        $respuesta = $this->get($this->enlace());

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', $respuesta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('informe-ana-mora-'.now()->year.'.pdf', $respuesta->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_el_pdf_no_se_guarda_en_caches_ni_buscadores(): void
    {
        $respuesta = $this->get($this->enlace());

        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $respuesta->headers->get('Cache-Control'));
        $respuesta->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_el_enlace_vence_a_los_siete_dias(): void
    {
        Carbon::setTestNow('2026-10-11 12:00:00');
        $enlace = $this->enlace();

        parse_str((string) parse_url($enlace, PHP_URL_QUERY), $query);
        $this->assertSame((string) Carbon::now()->addDays(7)->timestamp, $query['expires']);
        $this->assertSame('/informe/'.$this->ana->id, parse_url($enlace, PHP_URL_PATH));

        // El sétimo día todavía sirve; pasado el vencimiento ya no.
        Carbon::setTestNow(Carbon::now()->addDays(7)->subMinute());
        $this->get($enlace)->assertOk();

        Carbon::setTestNow(Carbon::now()->addMinutes(2));
        $this->get($enlace)->assertForbidden();
    }

    public function test_un_enlace_vencido_da_403(): void
    {
        $vencido = URL::temporarySignedRoute('informe.persona', now()->subMinute(), ['persona' => $this->ana->id]);

        $this->get($vencido)->assertForbidden();
    }

    public function test_un_enlace_con_la_firma_manipulada_da_403(): void
    {
        $enlace = $this->enlace();
        $manipulado = preg_replace('/signature=(.)/', 'signature=0$1', $enlace);
        $this->assertNotSame($enlace, $manipulado);

        $this->get($manipulado)->assertForbidden();
    }

    public function test_un_enlace_con_el_vencimiento_alargado_da_403(): void
    {
        $enlace = $this->enlace();
        $alargado = preg_replace_callback('/expires=(\d+)/', fn ($m) => 'expires='.($m[1] + 86400 * 365), $enlace);

        $this->get($alargado)->assertForbidden();
    }

    public function test_la_firma_de_una_persona_no_sirve_para_otra(): void
    {
        $otra = $this->crearPersona('Beto Rojas', '7777-6666');
        $enlaceDeAna = $this->enlace();

        $this->get(str_replace('/informe/'.$this->ana->id, '/informe/'.$otra->id, $enlaceDeAna))->assertForbidden();
    }

    public function test_sin_firma_da_403_aunque_la_persona_exista_o_no(): void
    {
        $this->get('/informe/'.$this->ana->id)->assertForbidden();
        $this->get('/informe/999999')->assertForbidden();
    }

    public function test_el_403_explica_en_español_que_el_enlace_ya_no_es_valido(): void
    {
        $vencido = URL::temporarySignedRoute('informe.persona', now()->subMinute(), ['persona' => $this->ana->id]);

        $this->get($vencido)
            ->assertForbidden()
            ->assertSee('Este enlace ya no es válido')
            ->assertSee('7 días')
            ->assertDontSee('Acceso Denegado');
    }

    public function test_una_persona_inexistente_con_firma_valida_da_404(): void
    {
        $this->get(URL::temporarySignedRoute('informe.persona', now()->addDay(), ['persona' => 999999]))->assertNotFound();
    }

    public function test_el_id_debe_ser_numerico(): void
    {
        $this->get(URL::temporarySignedRoute('informe.persona', now()->addDay(), ['persona' => 'abc']))->assertNotFound();
    }

    public function test_una_iglesia_dada_de_baja_no_entrega_el_informe(): void
    {
        $enlace = $this->enlace();
        $this->iglesia->update(['activo' => false]);

        $this->get($enlace)->assertNotFound();
    }

    public function test_funciona_aunque_haya_otra_sesion_iniciada_de_otra_iglesia(): void
    {
        $otraIglesia = $this->crearOtraIglesia();
        $ajena = $this->crearPersona('Persona Ajena', '8000-1111', $otraIglesia->id);

        // El admin de la iglesia de prueba abre el enlace de una persona de otra iglesia: la firma manda.
        $this->actingAs($this->admin)->get($this->enlace($ajena))->assertOk();
    }

    public function test_la_ruta_limita_a_30_peticiones_por_minuto(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/informe/'.$this->ana->id)->assertForbidden();
        }

        $this->get('/informe/'.$this->ana->id)->assertStatus(429);
    }
}

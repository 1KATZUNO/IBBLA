<?php

namespace Tests\Feature;

use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * Estado de cuenta por persona con sesión: el admin lo ve desde la ficha y el miembro el suyo
 * desde Mi perfil. (El enlace público firmado tiene sus propias pruebas en InformePublicoTest.)
 */
class InformePersonaRutaTest extends TestCase
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

    public function test_el_admin_ve_el_estado_de_cuenta_en_pdf_desde_la_ficha(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->get(route('personas.informe', ['persona' => $this->ana, 'accion' => 'ver']));

        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', $respuesta->headers->get('content-disposition'));
        $this->assertStringContainsString('informe-ana-mora-'.now()->year.'.pdf', $respuesta->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_descargar_manda_el_pdf_como_adjunto(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->get(route('personas.informe', ['persona' => $this->ana, 'accion' => 'descargar']));

        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', $respuesta->headers->get('content-disposition'));
        $this->assertStringContainsString('informe-ana-mora-'.now()->year.'.pdf', $respuesta->headers->get('content-disposition'));
    }

    public function test_una_accion_desconocida_no_pasa(): void
    {
        $this->actingAs($this->admin)
            ->from(route('personas.show', $this->ana))
            ->get(route('personas.informe', ['persona' => $this->ana, 'accion' => 'otra']))
            ->assertRedirect(route('personas.show', $this->ana))
            ->assertSessionHasErrors('accion');
    }

    public function test_la_ficha_compromisos_y_la_lista_ofrecen_el_boton(): void
    {
        $url = route('personas.informe', ['persona' => $this->ana, 'accion' => 'ver']);

        foreach ([route('personas.show', $this->ana), route('compromisos.show', $this->ana), route('personas.index')] as $pantalla) {
            $this->actingAs($this->admin)->get($pantalla)->assertOk()->assertSee($url, false);
        }
    }

    public function test_el_miembro_descarga_solo_el_suyo_desde_mi_perfil(): void
    {
        $miembro = $this->crearUsuarioConRol('miembro');
        $this->ana->update(['user_id' => $miembro->id]);

        $this->actingAs($miembro)
            ->get(route('mi-perfil.informe', ['accion' => 'ver']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($miembro)
            ->get(route('mi-perfil.index'))
            ->assertOk()
            ->assertSee(route('mi-perfil.informe', ['accion' => 'ver']), false);
    }

    public function test_un_usuario_sin_persona_vuelve_a_mi_perfil_con_aviso(): void
    {
        $this->actingAs($this->admin)
            ->get(route('mi-perfil.informe'))
            ->assertRedirect(route('mi-perfil.index'))
            ->assertSessionHas('error');
    }
}

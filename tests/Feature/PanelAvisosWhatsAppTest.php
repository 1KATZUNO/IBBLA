<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AvisoWhatsApp;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * Panel /avisos (admin y tesorero): pendientes agrupados por evento con «Abrir WhatsApp»,
 * «Marcar enviado» y «Descartar», más la pestaña de historial y el contador para el menú.
 */
class PanelAvisosWhatsAppTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-11 12:00:00');
        $this->crearIglesiaConAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearAviso(array $atributos = [], ?Tenant $iglesia = null): AvisoWhatsApp
    {
        return AvisoWhatsApp::create($atributos + [
            'tenant_id' => ($iglesia ?? $this->iglesia)->id,
            'evento' => AvisoWhatsApp::EVENTO_CULTO_CREADO,
            'titulo' => 'Culto creado: Domingo AM del 18/10/2026',
            'mensaje' => "Hola Ana Mora. Se creó un culto.\n\nRecuento: https://x.test/recuento?culto_id=1",
            'destinatario_nombre' => 'Ana Mora',
            'destinatario_telefono' => '50688881001',
            'enlace' => 'https://x.test/recuento?culto_id=1',
            'estado' => AvisoWhatsApp::PENDIENTE,
        ]);
    }

    // ---------------------------------------------------------------- acceso

    public function test_el_panel_responde_200_al_admin_y_al_tesorero(): void
    {
        $this->actingAs($this->admin)->get(route('avisos.index'))->assertOk();
        $this->actingAs($this->crearUsuarioConRol('tesorero'))->get(route('avisos.index'))->assertOk();
    }

    public function test_los_demas_roles_reciben_403_y_el_visitante_va_al_login(): void
    {
        foreach (['asistente', 'miembro', 'servidor', 'invitado'] as $rol) {
            $this->actingAs($this->crearUsuarioConRol($rol))->get(route('avisos.index'))->assertForbidden();
        }

        auth()->logout();
        $this->get(route('avisos.index'))->assertRedirect(route('login'));
    }

    public function test_las_acciones_tampoco_estan_abiertas_a_otros_roles(): void
    {
        $aviso = $this->crearAviso();
        $asistente = $this->crearUsuarioConRol('asistente');

        $this->actingAs($asistente)->post(route('avisos.enviado', $aviso))->assertForbidden();
        $this->actingAs($asistente)->post(route('avisos.descartar', $aviso))->assertForbidden();

        $this->assertSame(AvisoWhatsApp::PENDIENTE, $aviso->fresh()->estado);
    }

    // ---------------------------------------------------------------- pendientes

    public function test_muestra_los_pendientes_agrupados_por_evento_con_el_boton_de_whatsapp(): void
    {
        $this->crearAviso(['evento' => AvisoWhatsApp::EVENTO_SOBRES_ALCANZADOS, 'titulo' => '10 sobres en Domingo AM del 18/10/2026', 'destinatario_nombre' => 'Teresa Tesorera', 'destinatario_telefono' => '50688881002']);
        $this->crearAviso();

        $respuesta = $this->actingAs($this->admin)->get(route('avisos.index'));

        $respuesta->assertOk()
            ->assertSeeInOrder(['Cultos creados', 'Culto creado: Domingo AM del 18/10/2026', 'Sobres alcanzados', '10 sobres en Domingo AM del 18/10/2026'])
            ->assertSee('Abrir WhatsApp')
            ->assertSee('Marcar enviado')
            ->assertSee('Descartar')
            ->assertSee('+506 8888-1001')
            ->assertSee('Teresa Tesorera');
    }

    public function test_el_boton_abre_wa_me_con_el_telefono_y_el_mensaje_del_aviso(): void
    {
        $aviso = $this->crearAviso();
        $esperado = 'https://wa.me/50688881001?text='.rawurlencode($aviso->mensaje);

        $this->actingAs($this->admin)->get(route('avisos.index'))->assertSee('href="'.$esperado.'"', false);
        $this->assertSame($esperado, $aviso->enlace_whatsapp);
    }

    public function test_sin_pendientes_lo_dice(): void
    {
        $this->actingAs($this->admin)->get(route('avisos.index'))->assertSee('No hay avisos pendientes');
    }

    public function test_los_pendientes_no_incluyen_los_ya_atendidos_ni_los_de_otra_iglesia(): void
    {
        $this->crearAviso(['titulo' => 'Pendiente propio']);
        $this->crearAviso(['titulo' => 'Ya enviado', 'estado' => AvisoWhatsApp::ENVIADO]);
        $this->crearAviso(['titulo' => 'Ya descartado', 'estado' => AvisoWhatsApp::DESCARTADO]);
        $this->crearAviso(['titulo' => 'De otra iglesia'], $this->crearOtraIglesia());

        $this->actingAs($this->admin)->get(route('avisos.index'))
            ->assertSee('Pendiente propio')
            ->assertDontSee('Ya enviado')
            ->assertDontSee('Ya descartado')
            ->assertDontSee('De otra iglesia');
    }

    // ---------------------------------------------------------------- historial

    public function test_el_historial_muestra_enviados_y_descartados_con_quien_los_atendio(): void
    {
        $this->crearAviso(['titulo' => 'Aviso pendiente']);
        $this->crearAviso(['titulo' => 'Aviso enviado a mano', 'estado' => AvisoWhatsApp::ENVIADO, 'enviado_at' => now(), 'enviado_por' => $this->admin->id]);
        $this->crearAviso(['titulo' => 'Aviso enviado solo', 'estado' => AvisoWhatsApp::ENVIADO, 'enviado_at' => now()]);
        $this->crearAviso(['titulo' => 'Aviso descartado', 'estado' => AvisoWhatsApp::DESCARTADO, 'enviado_por' => $this->admin->id]);

        $this->actingAs($this->admin)->get(route('avisos.index', ['tab' => 'historial']))
            ->assertOk()
            ->assertSee('Aviso enviado a mano')
            ->assertSee('Aviso enviado solo')
            ->assertSee('Aviso descartado')
            ->assertSee('Enviado')
            ->assertSee('Descartado')
            ->assertSee('por '.$this->admin->name)
            ->assertSee('enviado por el sistema')
            ->assertDontSee('Aviso pendiente');
    }

    public function test_el_historial_vacio_lo_dice(): void
    {
        $this->actingAs($this->admin)->get(route('avisos.index', ['tab' => 'historial']))
            ->assertOk()
            ->assertSee('Todavía no hay avisos atendidos');
    }

    // ---------------------------------------------------------------- acciones

    public function test_marcar_enviado_cambia_el_estado_y_deja_quien_y_cuando(): void
    {
        $aviso = $this->crearAviso();

        $this->actingAs($this->admin)->post(route('avisos.enviado', $aviso))
            ->assertRedirect(route('avisos.index'))
            ->assertSessionHas('success');

        $aviso->refresh();
        $this->assertSame(AvisoWhatsApp::ENVIADO, $aviso->estado);
        $this->assertTrue($aviso->enviado_at->equalTo(now()));
        $this->assertSame($this->admin->id, $aviso->enviado_por);

        $registro = AuditLog::where('action', 'Marcar aviso de WhatsApp como enviado')->sole();
        $this->assertSame($this->admin->id, $registro->user_id);
        $this->assertSame($aviso->id, json_decode($registro->details, true)['aviso_id']);

        $this->get(route('avisos.index'))->assertDontSee('Culto creado: Domingo AM');
    }

    public function test_descartar_cambia_el_estado_y_lo_saca_de_los_pendientes(): void
    {
        $aviso = $this->crearAviso();

        $this->actingAs($this->admin)->post(route('avisos.descartar', $aviso))
            ->assertRedirect(route('avisos.index'))
            ->assertSessionHas('success');

        $aviso->refresh();
        $this->assertSame(AvisoWhatsApp::DESCARTADO, $aviso->estado);
        $this->assertNull($aviso->enviado_at);
        $this->assertSame($this->admin->id, $aviso->enviado_por);
        $this->assertSame(1, AuditLog::where('action', 'Descartar aviso de WhatsApp')->count());
    }

    public function test_el_tesorero_tambien_puede_atender_los_avisos(): void
    {
        $aviso = $this->crearAviso();
        $tesorero = $this->crearUsuarioConRol('tesorero');

        $this->actingAs($tesorero)->post(route('avisos.enviado', $aviso))->assertRedirect(route('avisos.index'));

        $this->assertSame($tesorero->id, $aviso->fresh()->enviado_por);
    }

    public function test_un_aviso_ya_atendido_no_se_atiende_dos_veces(): void
    {
        $aviso = $this->crearAviso();
        $this->actingAs($this->admin)->post(route('avisos.enviado', $aviso));
        $enviadoAt = $aviso->fresh()->enviado_at;

        Carbon::setTestNow(now()->addHour());
        $this->post(route('avisos.descartar', $aviso))
            ->assertRedirect(route('avisos.index'))
            ->assertSessionHas('error');
        $this->post(route('avisos.enviado', $aviso))->assertSessionHas('error');

        $aviso->refresh();
        $this->assertSame(AvisoWhatsApp::ENVIADO, $aviso->estado, 'No pasa de enviado a descartado');
        $this->assertTrue($aviso->enviado_at->equalTo($enviadoAt));
        $this->assertSame(1, AuditLog::whereIn('action', ['Marcar aviso de WhatsApp como enviado', 'Descartar aviso de WhatsApp'])->count());
    }

    public function test_no_se_puede_atender_un_aviso_de_otra_iglesia(): void
    {
        $ajeno = $this->crearAviso([], $this->crearOtraIglesia());

        $this->actingAs($this->admin)->post(route('avisos.enviado', $ajeno))->assertNotFound();
        $this->actingAs($this->admin)->post(route('avisos.descartar', $ajeno))->assertNotFound();

        $this->assertSame(AvisoWhatsApp::PENDIENTE, $ajeno->fresh()->estado);
    }

    // ---------------------------------------------------------------- contador para el menú

    public function test_el_contador_cuenta_solo_los_pendientes_de_la_iglesia(): void
    {
        $this->crearAviso();
        $this->crearAviso();
        $this->crearAviso(['estado' => AvisoWhatsApp::ENVIADO]);
        $this->crearAviso([], $this->crearOtraIglesia());

        $this->actingAs($this->admin);

        $this->assertSame(2, AvisoWhatsApp::contarPendientes());
    }

    public function test_el_componente_del_contador_muestra_el_numero_o_nada(): void
    {
        $this->actingAs($this->admin);
        $this->assertSame('', trim(Blade::render('<x-avisos-pendientes-badge />')));

        $this->crearAviso();
        $this->crearAviso();
        $this->assertStringContainsString('>2</span>', Blade::render('<x-avisos-pendientes-badge />'));

        foreach (range(1, 120) as $n) {
            $this->crearAviso();
        }
        $this->assertStringContainsString('>99+</span>', Blade::render('<x-avisos-pendientes-badge />'));
    }

    // ---------------------------------------------------------------- teléfono del usuario

    public function test_el_formulario_de_editar_usuario_muestra_y_guarda_el_telefono(): void
    {
        $usuario = $this->crearLider('tesorero', null, 'Teresa Tesorera');

        $this->actingAs($this->admin)->get(route('usuarios.edit', $usuario))
            ->assertOk()
            ->assertSee('name="telefono"', false);

        $this->put(route('usuarios.update', $usuario), [
            'name' => 'Teresa Tesorera', 'email' => $usuario->email, 'rol' => 'tesorero', 'telefono' => '8888-1002',
        ])->assertRedirect(route('usuarios.index'));

        $this->assertSame('8888-1002', $usuario->fresh()->telefono);

        $this->get(route('usuarios.edit', $usuario))->assertSee('value="8888-1002"', false);
    }

    public function test_se_puede_borrar_el_telefono_de_un_usuario(): void
    {
        $usuario = $this->crearLider('tesorero', '8888-1002');

        $this->actingAs($this->admin)->put(route('usuarios.update', $usuario), [
            'name' => $usuario->name, 'email' => $usuario->email, 'rol' => 'tesorero', 'telefono' => '',
        ])->assertRedirect(route('usuarios.index'));

        $this->assertNull($usuario->fresh()->telefono);
    }

    public function test_un_telefono_que_no_sirve_para_whatsapp_se_rechaza_al_guardar(): void
    {
        $usuario = $this->crearLider('tesorero', '8888-1002');

        $this->actingAs($this->admin)->from(route('usuarios.edit', $usuario))->put(route('usuarios.update', $usuario), [
            'name' => $usuario->name, 'email' => $usuario->email, 'rol' => 'tesorero', 'telefono' => '8888-7777 / 2222-3333',
        ])->assertRedirect(route('usuarios.edit', $usuario))
            ->assertSessionHasErrors(['telefono' => 'El teléfono no es válido para WhatsApp. Escriba 8 dígitos (por ejemplo 8888-7777) o con prefijo internacional (+506 8888-7777).']);

        $this->assertSame('8888-1002', $usuario->fresh()->telefono);
    }

    public function test_crear_un_usuario_con_telefono(): void
    {
        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Nuevo Líder', 'email' => 'nuevo@iglesia-prueba.test', 'rol' => 'tesorero',
            'password' => 'secreto-1234', 'password_confirmation' => 'secreto-1234', 'telefono' => '+506 8888-1009',
        ])->assertRedirect(route('usuarios.index'));

        $this->assertSame('+506 8888-1009', User::where('email', 'nuevo@iglesia-prueba.test')->value('telefono'));

        $this->get(route('usuarios.create'))->assertSee('name="telefono"', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActivacionWhatsApp;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * La misma tarjeta de WhatsApp (CallMeBot) en el perfil de los usuarios del sistema (admin, tesorero,
 * asistente), para los avisos de rol. El número de referencia es el de su usuario.
 */
class PerfilUsuarioWhatsAppTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    private const ENLACE = 'https://api.callmebot.com/whatsapp.php?phone=+50688881002&text=This+is+a+test&apikey=7654321';

    private const EXITO = '<p>Message queued. You will receive it in a few seconds.</p>';

    private User $tesorero;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 10:00:00');
        Http::preventStrayRequests();
        config(['whatsapp.driver' => 'callmebot', 'whatsapp.callmebot.url' => 'https://api.callmebot.com/whatsapp.php']);
        $this->crearIglesiaConAdmin();
        $this->tesorero = $this->crearLider('tesorero', '8888-1002', 'Teresa Tesorera');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function activar(string $enlace = self::ENLACE, bool $acepta = true): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->tesorero)->post(route('profile.whatsapp.guardar'), ['enlace' => $enlace] + ($acepta ? ['acepta' => '1'] : []));
    }

    public function test_los_usuarios_del_sistema_ven_la_tarjeta_en_su_perfil(): void
    {
        $this->admin->update(['telefono' => '8888-1001']);
        $asistente = $this->crearUsuarioConRol('asistente');
        $asistente->update(['telefono' => '8888-1003']);

        foreach ([$this->admin, $this->tesorero, $asistente] as $usuario) {
            $this->actingAs($usuario)->get(route('profile.edit'))->assertOk()
                ->assertSee('Avisos por WhatsApp')
                ->assertSee('Reciba en su WhatsApp los avisos del sistema')
                ->assertSee('Paso 1. Guarde el contacto del bot')
                ->assertSee('avisos del sistema (culto creado, sobres registrados)');
        }
    }

    public function test_un_miembro_no_ve_la_tarjeta_de_usuario_y_sus_rutas_le_dan_403(): void
    {
        $miembro = $this->crearUsuarioConRol('miembro');

        $this->actingAs($miembro)->get(route('profile.edit'))->assertOk()->assertDontSee('Avisos por WhatsApp');
        $this->actingAs($miembro)->post(route('profile.whatsapp.guardar'), ['enlace' => self::ENLACE, 'acepta' => '1'])->assertForbidden();
        $this->actingAs($miembro)->post(route('profile.whatsapp.probar'))->assertForbidden();
        $this->actingAs($miembro)->delete(route('profile.whatsapp.desactivar'))->assertForbidden();

        auth()->logout();
        $this->post(route('profile.whatsapp.guardar'))->assertRedirect(route('login'));
    }

    public function test_con_otro_driver_no_hay_tarjeta_ni_rutas(): void
    {
        config(['whatsapp.driver' => 'link']);

        $this->actingAs($this->tesorero)->get(route('profile.edit'))->assertOk()->assertDontSee('Avisos por WhatsApp');
        $this->activar()->assertNotFound();
    }

    public function test_activar_guarda_la_activacion_del_usuario_con_su_telefono(): void
    {
        $this->activar()->assertRedirect(route('profile.edit').'#whatsapp')
            ->assertSessionHas('whatsapp_aviso', fn (array $a) => $a['exito'] === true);

        $activacion = ActivacionWhatsApp::sole();
        $this->assertSame($this->tesorero->id, $activacion->user_id);
        $this->assertNull($activacion->persona_id);
        $this->assertSame('50688881002', $activacion->telefono);
        $this->assertSame('7654321', $activacion->clave());
        $this->assertSame(1, AuditLog::where('action', 'Activar WhatsApp (CallMeBot)')->count());

        $html = $this->get(route('profile.edit'))->assertSee('Activado, falta la prueba')->getContent();
        $this->assertStringNotContainsString('7654321', $html);
        $this->assertStringNotContainsString('7654321', DB::table('audit_logs')->pluck('details')->implode(' '));
    }

    public function test_el_enlace_tiene_que_ser_del_telefono_de_su_usuario(): void
    {
        $this->activar(str_replace('50688881002', '50688881999', self::ENLACE))->assertSessionHasErrors('enlace', null, 'whatsapp');

        $this->get(route('profile.edit'))
            ->assertSee('el número que tiene registrado su usuario es +506 8888-1002')
            ->assertSee('pídale al administrador que actualice su teléfono');
        $this->assertSame(0, ActivacionWhatsApp::withoutGlobalScopes()->count());
    }

    public function test_sin_telefono_en_su_usuario_pide_que_el_administrador_lo_agregue(): void
    {
        $this->tesorero->update(['telefono' => null]);

        $this->actingAs($this->tesorero)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Pídale al administrador (Gestionar Usuarios)')
            ->assertDontSee('Activar WhatsApp');
        $this->activar()->assertSessionHasErrors('telefono', null, 'whatsapp');
    }

    public function test_la_prueba_y_desactivar_funcionan_igual(): void
    {
        $this->activar();
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);

        $this->actingAs($this->tesorero)->post(route('profile.whatsapp.probar'))->assertRedirect(route('profile.edit').'#whatsapp');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'apikey=7654321') && str_contains(urldecode($r->url()), 'Hola Teresa Tesorera'));
        $this->assertNotNull($this->tesorero->fresh()->activacionWhatsApp->verificado_at);

        $this->actingAs($this->tesorero)->delete(route('profile.whatsapp.desactivar'))->assertRedirect(route('profile.edit').'#whatsapp');
        $this->assertNull(DB::table('activaciones_whatsapp')->value('apikey'));
        $this->assertSame(1, AuditLog::where('action', 'Desactivar WhatsApp (CallMeBot)')->count());
    }

    public function test_la_activacion_de_un_usuario_no_toca_la_de_otro(): void
    {
        $otro = $this->crearLider('admin', '8888-1009', 'Otro Admin');
        $ajena = $this->activarWhatsApp($otro, '111222');

        $this->activar();
        $this->actingAs($this->tesorero)->delete(route('profile.whatsapp.desactivar'));

        $this->assertTrue($ajena->fresh()->activo);
        $this->assertSame('111222', $ajena->fresh()->clave());
    }
}

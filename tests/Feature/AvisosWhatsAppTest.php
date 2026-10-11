<?php

namespace Tests\Feature;

use App\Events\CultoCreado;
use App\Events\SobresAlcanzados;
use App\Jobs\EnviarAvisoWhatsApp;
use App\Models\AvisoWhatsApp;
use App\Models\Culto;
use App\Models\Persona;
use App\Models\Sobre;
use App\Listeners\CrearAvisosWhatsApp;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\WhatsApp\AvisosWhatsAppService;
use App\Services\WhatsApp\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * Avisos de WhatsApp a los líderes: se crean cuando se crea un culto y cuando un culto llega a N
 * sobres (una sola vez). Con el driver link quedan pendientes; con meta un job los envía.
 */
class AvisosWhatsAppTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-11 12:00:00');
        $this->crearIglesiaConAdmin();
        $this->crearCategoriasPorDefecto();
        $this->admin->update(['telefono' => '8888-1001']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearCultoPorHttp(string $fecha = '2026-10-18'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('cultos.store'), [
            'fecha' => $fecha,
            'hora' => '09:00',
            'tipo_culto' => 'domingo',
        ]);
    }

    private function registrarSobrePorHttp(Culto $culto, Persona $persona): void
    {
        $this->actingAs($this->admin)->post(route('recuento.store'), [
            'culto_id' => $culto->id,
            'persona_id' => $persona->id,
            'metodo_pago' => 'efectivo',
            'moneda' => 'CRC',
            'detalles' => [['categoria' => 'diezmo', 'monto' => 1000]],
        ])->assertSessionHasNoErrors();
    }

    /** @return \Illuminate\Support\Collection<int, AvisoWhatsApp> */
    private function avisos(?string $evento = null)
    {
        return AvisoWhatsApp::withoutGlobalScopes()
            ->when($evento, fn ($q) => $q->where('evento', $evento))
            ->orderBy('id')
            ->get();
    }

    // ---------------------------------------------------------------- culto creado

    public function test_crear_un_culto_genera_un_aviso_por_cada_lider_con_telefono(): void
    {
        $this->crearLider('tesorero', '+506 8888-1002', 'Teresa Tesorera');
        $this->crearLider('asistente', '8888-1003', 'Asis Tente');            // rol que no recibe avisos
        $this->crearLider('tesorero', null, 'Sin Telefono');                  // sin teléfono
        $this->crearLider('admin', 'no tiene', 'Telefono Malo');              // teléfono inválido
        $this->crearLider('admin', '8888-1004', 'Admin Ajeno', $this->crearOtraIglesia()); // otra iglesia

        $this->crearCultoPorHttp()->assertRedirect(route('cultos.index'));

        $avisos = $this->avisos(AvisoWhatsApp::EVENTO_CULTO_CREADO);
        $this->assertCount(2, $avisos);
        $this->assertEqualsCanonicalizing(['50688881001', '50688881002'], $avisos->pluck('destinatario_telefono')->all());

        foreach ($avisos as $aviso) {
            $this->assertSame(AvisoWhatsApp::PENDIENTE, $aviso->estado);
            $this->assertSame($this->iglesia->id, $aviso->tenant_id);
            $this->assertNull($aviso->enviado_at);
            $this->assertNull($aviso->enviado_por);
        }
    }

    public function test_el_aviso_de_culto_creado_lleva_tipo_fecha_hora_e_enlace_al_recuento(): void
    {
        $this->crearLider('tesorero', '8888-1002', 'Teresa Tesorera');

        $this->crearCultoPorHttp('2026-10-18');

        $culto = Culto::sole();
        $aviso = $this->avisos()->firstWhere('destinatario_nombre', 'Teresa Tesorera');
        $enlace = route('recuento.index', ['culto_id' => $culto->id]);

        $this->assertSame($enlace, $aviso->enlace);
        $this->assertSame('Culto creado: Domingo AM del 18/10/2026', $aviso->titulo);
        $this->assertSame(
            "Hola Teresa Tesorera. Se creó un culto en Iglesia de Prueba: Domingo AM, domingo 18 de octubre de 2026 a las 9:00 am.\n\nRecuento: {$enlace}",
            $aviso->mensaje,
        );
        $this->assertSame(User::where('name', 'Teresa Tesorera')->value('id'), $aviso->user_id);
    }

    public function test_dos_usuarios_con_el_mismo_telefono_reciben_un_solo_aviso(): void
    {
        $this->crearLider('tesorero', '+506 8888 1001', 'Misma Persona');

        $this->crearCultoPorHttp();

        $this->assertCount(1, $this->avisos());
    }

    public function test_sin_lideres_con_telefono_no_hay_avisos_pero_el_culto_se_crea(): void
    {
        $this->admin->update(['telefono' => null]);

        $this->crearCultoPorHttp()->assertRedirect(route('cultos.index'));

        $this->assertSame(1, Culto::count());
        $this->assertCount(0, $this->avisos());
    }

    public function test_los_roles_que_reciben_avisos_se_configuran(): void
    {
        $this->crearLider('asistente', '8888-1003', 'Asis Tente');
        config(['whatsapp.avisos.roles' => ['asistente']]);

        $this->crearCultoPorHttp();

        $avisos = $this->avisos();
        $this->assertCount(1, $avisos);
        $this->assertSame('Asis Tente', $avisos[0]->destinatario_nombre);
    }

    public function test_el_rol_que_cuenta_es_el_del_rol_dinamico_y_si_no_tiene_el_de_siempre(): void
    {
        // Columna rol = invitado, pero su rol dinámico es tesorero: recibe.
        $conRolDinamico = $this->crearLider('invitado', '8888-1005', 'Rol Dinamico');
        $conRolDinamico->update(['tenant_role_id' => TenantRole::where('tenant_id', $this->iglesia->id)->where('slug', 'tesorero')->value('id')]);

        // Columna rol = tesorero, pero su rol dinámico es asistente: no recibe.
        $degradado = $this->crearLider('tesorero', '8888-1006', 'Degradado');
        $degradado->update(['tenant_role_id' => TenantRole::where('tenant_id', $this->iglesia->id)->where('slug', 'asistente')->value('id')]);

        // Sin rol dinámico: vale la columna rol.
        $this->crearLider('tesorero', '8888-1007', 'Solo Columna')->update(['tenant_role_id' => null]);

        $nombres = app(AvisosWhatsAppService::class)->destinatarios($this->iglesia->id)->pluck('usuario.name')->all();

        $this->assertEqualsCanonicalizing([$this->admin->name, 'Rol Dinamico', 'Solo Columna'], $nombres);
    }

    public function test_el_super_admin_no_recibe_avisos(): void
    {
        User::create([
            'name' => 'Super', 'email' => 'super@plataforma.test', 'password' => 'secreto-123', 'rol' => 'admin',
            'is_super_admin' => true, 'tenant_id' => $this->iglesia->id, 'telefono' => '8888-1008',
        ]);

        $this->assertSame(['50688881001'], app(AvisosWhatsAppService::class)->destinatarios($this->iglesia->id)->pluck('telefono')->all());
    }

    public function test_un_error_al_preparar_los_avisos_no_impide_crear_el_culto(): void
    {
        $this->mock(AvisosWhatsAppService::class)->shouldReceive('cultoCreado')->andThrow(new \RuntimeException('boom'));

        $this->crearCultoPorHttp()->assertRedirect(route('cultos.index'));

        $this->assertSame(1, Culto::count());
    }

    public function test_crear_un_culto_dispara_el_evento_culto_creado(): void
    {
        Event::fake([CultoCreado::class]);

        $this->crearCultoPorHttp('2026-10-18');

        Event::assertDispatched(CultoCreado::class, fn (CultoCreado $e) => $e->culto->fecha->toDateString() === '2026-10-18');
    }

    // ---------------------------------------------------------------- sobres alcanzados

    public function test_el_sobre_numero_10_genera_el_aviso_una_sola_vez_y_el_11_no(): void
    {
        $this->crearLider('tesorero', '8888-1002', 'Teresa Tesorera');
        $culto = $this->crearCulto('2026-10-18');
        $personas = collect(range(1, 11))->map(fn ($n) => $this->crearPersona("Persona {$n}", '7000-00'.sprintf('%02d', $n)));

        foreach ($personas->take(9) as $persona) {
            $this->registrarSobrePorHttp($culto, $persona);
        }
        $this->assertCount(0, $this->avisos(AvisoWhatsApp::EVENTO_SOBRES_ALCANZADOS), 'Con 9 sobres todavía no se avisa');

        $this->registrarSobrePorHttp($culto, $personas[9]);

        $avisos = $this->avisos(AvisoWhatsApp::EVENTO_SOBRES_ALCANZADOS);
        $this->assertCount(2, $avisos, 'Con el sobre 10 se crea un aviso por líder con teléfono');
        $this->assertEqualsCanonicalizing(['50688881001', '50688881002'], $avisos->pluck('destinatario_telefono')->all());
        $this->assertSame('10 sobres en Domingo AM del 18/10/2026', $avisos[0]->titulo);
        $this->assertStringContainsString('ya tiene 10 sobres registrados en Iglesia de Prueba', $avisos[0]->mensaje);
        $this->assertStringContainsString(route('recuento.index', ['culto_id' => $culto->id]), $avisos[0]->mensaje);
        $this->assertNotNull(Culto::find($culto->id)->aviso_sobres_at);

        $this->registrarSobrePorHttp($culto, $personas[10]);

        $this->assertCount(2, $this->avisos(AvisoWhatsApp::EVENTO_SOBRES_ALCANZADOS), 'El sobre 11 no repite el aviso');
        $this->assertSame(11, Sobre::where('culto_id', $culto->id)->count());
    }

    public function test_el_umbral_de_sobres_se_configura(): void
    {
        config(['whatsapp.avisos.sobres_umbral' => 3]);
        $culto = $this->crearCulto('2026-10-18');
        $servicio = app(AvisosWhatsAppService::class);

        $resultados = [];
        foreach (range(1, 4) as $n) {
            $this->crearSobre($culto, null, ['diezmo' => 1000]);
            $resultados[] = $servicio->sobreRegistrado($culto);
        }

        $this->assertSame([false, false, true, false], $resultados);
        $this->assertCount(1, $this->avisos(AvisoWhatsApp::EVENTO_SOBRES_ALCANZADOS));
    }

    public function test_borrar_un_sobre_y_volver_a_llegar_al_umbral_no_repite_el_aviso(): void
    {
        config(['whatsapp.avisos.sobres_umbral' => 2]);
        $culto = $this->crearCulto('2026-10-18');
        $servicio = app(AvisosWhatsAppService::class);

        $this->crearSobre($culto, null, ['diezmo' => 1000]);
        $ultimo = $this->crearSobre($culto, null, ['diezmo' => 1000]);
        $this->assertTrue($servicio->sobreRegistrado($culto));

        $ultimo->delete();
        $this->crearSobre($culto, null, ['diezmo' => 1000]);

        $this->assertFalse($servicio->sobreRegistrado($culto));
        $this->assertCount(1, $this->avisos(AvisoWhatsApp::EVENTO_SOBRES_ALCANZADOS));
    }

    public function test_un_salto_por_encima_del_umbral_tampoco_pierde_el_aviso(): void
    {
        config(['whatsapp.avisos.sobres_umbral' => 3]);
        $culto = $this->crearCulto('2026-10-18');
        foreach (range(1, 5) as $n) {
            $this->crearSobre($culto, null, ['diezmo' => 1000]);
        }

        $this->assertTrue(app(AvisosWhatsAppService::class)->sobreRegistrado($culto));
        $this->assertStringContainsString('ya tiene 5 sobres', $this->avisos()[0]->mensaje);
    }

    public function test_cada_culto_tiene_su_propio_aviso_de_sobres(): void
    {
        config(['whatsapp.avisos.sobres_umbral' => 1]);
        $servicio = app(AvisosWhatsAppService::class);

        foreach (['2026-10-18', '2026-10-25'] as $fecha) {
            $culto = $this->crearCulto($fecha);
            $this->crearSobre($culto, null, ['diezmo' => 1000]);
            $this->assertTrue($servicio->sobreRegistrado($culto));
        }

        $this->assertCount(2, $this->avisos(AvisoWhatsApp::EVENTO_SOBRES_ALCANZADOS));
    }

    public function test_el_umbral_dispara_el_evento_sobres_alcanzados_con_la_cantidad(): void
    {
        config(['whatsapp.avisos.sobres_umbral' => 1]);
        Event::fake([SobresAlcanzados::class]);
        $culto = $this->crearCulto('2026-10-18');
        $this->crearSobre($culto, null, ['diezmo' => 1000]);

        $this->assertTrue(app(AvisosWhatsAppService::class)->sobreRegistrado($culto));

        Event::assertDispatched(SobresAlcanzados::class, fn (SobresAlcanzados $e) => $e->cantidad === 1 && $e->culto->is($culto));
    }

    public function test_un_fallo_al_preparar_el_aviso_de_sobres_no_rompe_el_registro_del_sobre(): void
    {
        $this->mock(AvisosWhatsAppService::class)->shouldReceive('sobresAlcanzados')->andThrow(new \RuntimeException('boom'));
        $culto = $this->crearCulto('2026-10-18');

        // El listener se traga el error: lo reporta y sigue.
        app(CrearAvisosWhatsApp::class)->handleSobresAlcanzados(new SobresAlcanzados($culto, 10));

        $this->assertCount(0, $this->avisos());
    }

    // ---------------------------------------------------------------- driver link / meta

    public function test_con_el_driver_link_no_se_encola_ningun_envio(): void
    {
        Queue::fake();

        $this->crearCultoPorHttp();

        Queue::assertNothingPushed();
        $this->assertCount(1, $this->avisos());
        $this->assertSame(AvisoWhatsApp::PENDIENTE, $this->avisos()[0]->estado);
    }

    public function test_con_meta_configurado_se_encola_un_envio_por_aviso(): void
    {
        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => 'T', 'whatsapp.meta.phone_number_id' => '555']);
        $this->crearLider('tesorero', '8888-1002');
        Queue::fake();

        $this->crearCultoPorHttp();

        Queue::assertPushed(EnviarAvisoWhatsApp::class, 2);
        Queue::assertPushed(EnviarAvisoWhatsApp::class, fn (EnviarAvisoWhatsApp $job) => in_array($job->avisoId, $this->avisos()->pluck('id')->all(), true));
    }

    public function test_con_meta_sin_credenciales_no_se_encola_nada(): void
    {
        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => '', 'whatsapp.meta.phone_number_id' => null]);
        Queue::fake();

        $this->crearCultoPorHttp();

        Queue::assertNothingPushed();
        $this->assertSame(AvisoWhatsApp::PENDIENTE, $this->avisos()[0]->estado);
    }

    public function test_con_meta_el_aviso_se_envia_solo_y_queda_marcado_como_enviado(): void
    {
        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => 'T', 'whatsapp.meta.phone_number_id' => '555']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $this->crearCultoPorHttp();

        $aviso = $this->avisos()->sole();
        $this->assertSame(AvisoWhatsApp::ENVIADO, $aviso->estado);
        $this->assertNotNull($aviso->enviado_at);
        $this->assertNull($aviso->enviado_por, 'Lo envió el sistema, no una persona');

        Http::assertSent(fn (ClientRequest $r) => $r->url() === 'https://graph.facebook.com/v21.0/555/messages'
            && $r['to'] === '50688881001'
            && $r['text']['body'] === $aviso->mensaje);
    }

    public function test_si_meta_falla_el_aviso_sigue_pendiente_para_mandarlo_a_mano(): void
    {
        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => 'T', 'whatsapp.meta.phone_number_id' => '555']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'boom', 'code' => 1]], 500)]);

        $this->crearCultoPorHttp()->assertRedirect(route('cultos.index'));

        $this->assertSame(1, Culto::count());
        $aviso = $this->avisos()->sole();
        $this->assertSame(AvisoWhatsApp::PENDIENTE, $aviso->estado);
        $this->assertNotNull($aviso->enlace_whatsapp);
    }

    public function test_el_job_no_envia_un_aviso_que_ya_se_atendio_a_mano(): void
    {
        Http::fake();
        $this->crearCultoPorHttp();
        $aviso = $this->avisos()->sole();
        $aviso->descartar($this->admin);

        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => 'T', 'whatsapp.meta.phone_number_id' => '555']);
        (new EnviarAvisoWhatsApp($aviso->id))->handle(app(WhatsAppService::class));

        Http::assertNothingSent();
        $this->assertSame(AvisoWhatsApp::DESCARTADO, $aviso->fresh()->estado);
    }

    public function test_el_job_con_un_aviso_inexistente_no_hace_nada(): void
    {
        Http::fake();
        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => 'T', 'whatsapp.meta.phone_number_id' => '555']);

        (new EnviarAvisoWhatsApp(999999))->handle(app(WhatsAppService::class));

        Http::assertNothingSent();
    }

    public function test_el_job_sin_credenciales_falla_sin_marcar_el_aviso(): void
    {
        $this->crearCultoPorHttp();
        $aviso = $this->avisos()->sole();
        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => '', 'whatsapp.meta.phone_number_id' => '']);

        (new EnviarAvisoWhatsApp($aviso->id))->handle(app(WhatsAppService::class));

        $this->assertSame(AvisoWhatsApp::PENDIENTE, $aviso->fresh()->estado);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActivacionWhatsApp;
use App\Models\Persona;
use App\Services\WhatsApp\ActivacionNoValidaException;
use App\Services\WhatsApp\ActivacionWhatsAppService;
use App\Services\WhatsApp\MensajesWhatsApp;
use App\Services\WhatsApp\WhatsAppEnvioException;
use App\Services\WhatsApp\WhatsAppNoConfiguradoException;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * Activar, probar y desactivar WhatsApp (CallMeBot): validación del enlace pegado, consentimiento,
 * número registrado, reactivación y mensaje de prueba. Todo con Http::fake.
 */
class ActivacionWhatsAppServicioTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    private const ENLACE = 'https://api.callmebot.com/whatsapp.php?phone=+50688887777&text=This+is+a+test&apikey=1234567';

    private const EXITO = '<p>Message queued. You will receive it in a few seconds.</p>';

    private Persona $ana;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 10:00:00');
        Http::preventStrayRequests();
        config(['whatsapp.driver' => 'callmebot']);
        $this->crearIglesiaConAdmin();
        $this->ana = $this->crearPersona('Ana Mora', '8888-7777');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function servicio(): ActivacionWhatsAppService
    {
        return app(ActivacionWhatsAppService::class);
    }

    /** @return array<string, string> */
    private function erroresAl(callable $activar): array
    {
        try {
            $activar();
        } catch (ActivacionNoValidaException $e) {
            return $e->errores();
        }

        $this->fail('Debió lanzar ActivacionNoValidaException');
    }

    // ---------------------------------------------------------------- activar

    public function test_activar_guarda_el_numero_la_clave_cifrada_y_el_consentimiento(): void
    {
        $peticion = Request::create('/mi-perfil/whatsapp', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Android 14) Chrome/118',
        ]);

        $activacion = $this->servicio()->activar($this->ana, self::ENLACE, true, $peticion);

        $this->assertSame($this->ana->id, $activacion->persona_id);
        $this->assertNull($activacion->user_id);
        $this->assertSame($this->iglesia->id, $activacion->tenant_id);
        $this->assertSame('50688887777', $activacion->telefono);
        $this->assertTrue($activacion->activo);
        $this->assertSame('1234567', $activacion->fresh()->clave());
        $this->assertNotSame('1234567', DB::table('activaciones_whatsapp')->value('apikey'), 'La clave va cifrada en la base');
        $this->assertTrue($activacion->consentimiento_at->equalTo(now()));
        $this->assertSame('v1', $activacion->consentimiento_version);
        $this->assertSame('203.0.113.9', $activacion->consentimiento_ip);
        $this->assertSame('Mozilla/5.0 (Android 14) Chrome/118', $activacion->consentimiento_agente);
        $this->assertNull($activacion->revocado_at);
        $this->assertNull($activacion->verificado_at);
    }

    public function test_acepta_el_mensaje_completo_del_bot_y_un_enlace_sin_numero(): void
    {
        $this->servicio()->activar($this->ana, "API Activated for your phone number. Your APIKEY is 7654321\n\nYou can now send messages:\n"
            .'https://api.callmebot.com/whatsapp.php?phone=%2B50688887777&text=This+is+a+test&apikey=7654321', true);
        $this->assertSame('7654321', $this->ana->fresh()->activacionWhatsApp->clave());

        $beto = $this->crearPersona('Beto Rojas', '7777-6666');
        $this->servicio()->activar($beto, 'https://api.callmebot.com/whatsapp.php?text=Hola&apikey=555444', true);
        $this->assertSame('50677776666', $beto->fresh()->activacionWhatsApp->telefono, 'Sin phone= vale el número registrado');
    }

    public function test_el_numero_del_enlace_tiene_que_ser_el_registrado(): void
    {
        $errores = $this->erroresAl(fn () => $this->servicio()->activar(
            $this->ana,
            'https://api.callmebot.com/whatsapp.php?phone=+50689991111&text=Hola&apikey=1234567',
            true,
        ));

        $this->assertSame(['enlace'], array_keys($errores));
        $this->assertStringContainsString('+506 8999-1111', $errores['enlace']);
        $this->assertStringContainsString('+506 8888-7777', $errores['enlace']);
        $this->assertStringContainsString('la administración', $errores['enlace']);
        $this->assertSame(0, ActivacionWhatsApp::withoutGlobalScopes()->count(), 'No se guarda nada');
    }

    public function test_un_numero_del_enlace_que_no_sirve_se_avisa(): void
    {
        $errores = $this->erroresAl(fn () => $this->servicio()->activar(
            $this->ana,
            'https://api.callmebot.com/whatsapp.php?phone=12&text=Hola&apikey=1234567',
            true,
        ));

        $this->assertStringContainsString('número que trae ese enlace no es válido', $errores['enlace']);
    }

    public function test_sin_telefono_registrado_no_se_puede_activar(): void
    {
        foreach ([null, '', 'no tiene', '8888-7777 / 2222-3333'] as $telefono) {
            $sin = $this->crearPersona('Sin Tel '.md5((string) $telefono), $telefono);

            $errores = $this->erroresAl(fn () => $this->servicio()->activar($sin, self::ENLACE, true));

            $this->assertArrayHasKey('telefono', $errores, (string) $telefono);
            $this->assertStringContainsString('Pídale a la administración', $errores['telefono']);
            $this->assertStringContainsString('8888-7777', $errores['telefono']);
        }

        $this->assertSame(0, ActivacionWhatsApp::withoutGlobalScopes()->count());
    }

    public function test_sin_consentimiento_no_se_guarda_nada(): void
    {
        $errores = $this->erroresAl(fn () => $this->servicio()->activar($this->ana, self::ENLACE, false));

        $this->assertSame(['acepta'], array_keys($errores));
        $this->assertSame(0, ActivacionWhatsApp::withoutGlobalScopes()->count());
    }

    public function test_los_errores_salen_todos_juntos(): void
    {
        $errores = $this->erroresAl(fn () => $this->servicio()->activar($this->ana, 'ya activé el bot', false));

        $this->assertEqualsCanonicalizing(['enlace', 'acepta'], array_keys($errores));
    }

    public function test_cada_enlace_malo_explica_que_pasa(): void
    {
        $casos = [
            [null, 'Pegue aquí el mensaje que le respondió el bot'],
            ['ya activé el bot', 'No encontramos el enlace del bot'],
            ['https://www.ejemplo.com/x?apikey=1234567', 'Ese enlace no es de CallMeBot'],
            ['https://api.callmebot.com/whatsapp.php?phone=+50688887777', 'le falta la clave'],
        ];

        foreach ($casos as [$pegado, $esperado]) {
            $errores = $this->erroresAl(fn () => $this->servicio()->activar($this->ana, $pegado, true));

            $this->assertStringContainsString($esperado, $errores['enlace'], (string) $pegado);
        }
    }

    public function test_activar_de_nuevo_renueva_la_clave_y_el_consentimiento_en_la_misma_fila(): void
    {
        $this->servicio()->activar($this->ana, self::ENLACE, true);
        $this->ana->activacionWhatsApp()->first()->forceFill(['verificado_at' => now(), 'ultimo_error' => 'CallMeBot respondió 503.'])->save();

        Carbon::setTestNow('2026-11-01 08:30:00');
        $this->servicio()->activar($this->ana->fresh(), str_replace('1234567', '9998887', self::ENLACE), true);

        $this->assertSame(1, ActivacionWhatsApp::withoutGlobalScopes()->count());
        $activacion = $this->ana->fresh()->activacionWhatsApp;
        $this->assertSame('9998887', $activacion->clave());
        $this->assertTrue($activacion->consentimiento_at->equalTo(now()));
        $this->assertNull($activacion->verificado_at, 'Una clave nueva hay que volver a probarla');
        $this->assertNull($activacion->ultimo_error);
    }

    public function test_un_usuario_del_sistema_activa_con_su_telefono_de_usuario(): void
    {
        $tesorero = $this->crearLider('tesorero', '8888-1002', 'Teresa Tesorera');

        $activacion = $this->servicio()->activar(
            $tesorero,
            'https://api.callmebot.com/whatsapp.php?phone=+50688881002&text=Hola&apikey=111222',
            true,
        );

        $this->assertSame($tesorero->id, $activacion->user_id);
        $this->assertNull($activacion->persona_id);
        $this->assertSame('50688881002', $activacion->telefono);

        $errores = $this->erroresAl(fn () => $this->servicio()->activar($tesorero, self::ENLACE, true));
        $this->assertStringContainsString('tiene registrado su usuario', $errores['enlace']);
        $this->assertStringContainsString('pídale al administrador', $errores['enlace']);
    }

    // ---------------------------------------------------------------- situación

    public function test_la_situacion_dice_en_que_punto_esta(): void
    {
        $this->assertSame(ActivacionWhatsAppService::SIN_ACTIVAR, $this->servicio()->situacion($this->ana));

        $this->servicio()->activar($this->ana, self::ENLACE, true);
        $this->assertSame(ActivacionWhatsApp::ESTADO_ACTIVA, $this->servicio()->situacion($this->ana->fresh()));

        $this->ana->update(['telefono' => '7000-1111']);
        $this->assertSame(ActivacionWhatsApp::ESTADO_NUMERO_CAMBIO, $this->servicio()->situacion($this->ana->fresh()));

        $this->ana->update(['telefono' => '8888-7777']);
        $this->servicio()->desactivar($this->ana->fresh());
        $this->assertSame(ActivacionWhatsApp::ESTADO_DESACTIVADA, $this->servicio()->situacion($this->ana->fresh()));
    }

    // ---------------------------------------------------------------- desactivar

    public function test_desactivar_borra_la_clave_y_deja_la_fecha_de_revocacion(): void
    {
        $this->servicio()->activar($this->ana, self::ENLACE, true);
        Carbon::setTestNow('2026-10-20 09:00:00');

        $this->assertNotNull($this->servicio()->desactivar($this->ana->fresh()));

        $fila = DB::table('activaciones_whatsapp')->first();
        $this->assertNull($fila->apikey, 'La clave se borra de verdad');
        $this->assertFalse((bool) $fila->activo);
        $this->assertSame('2026-10-20 09:00:00', Carbon::parse($fila->revocado_at)->format('Y-m-d H:i:s'));
        $this->assertNotNull($fila->consentimiento_at, 'Se conserva cuándo había aceptado');
    }

    public function test_desactivar_sin_nada_que_desactivar_no_hace_nada(): void
    {
        $this->assertNull($this->servicio()->desactivar($this->ana));

        $this->servicio()->activar($this->ana, self::ENLACE, true);
        $this->servicio()->desactivar($this->ana->fresh());
        $this->assertNull($this->servicio()->desactivar($this->ana->fresh()), 'Ya estaba desactivada');
    }

    public function test_reactivar_despues_de_desactivar_vuelve_a_dejarla_activa(): void
    {
        $this->servicio()->activar($this->ana, self::ENLACE, true);
        $this->servicio()->desactivar($this->ana->fresh());

        $this->servicio()->activar($this->ana->fresh(), self::ENLACE, true);

        $activacion = $this->ana->fresh()->activacionWhatsApp;
        $this->assertTrue($activacion->activo);
        $this->assertNull($activacion->revocado_at);
        $this->assertSame('1234567', $activacion->clave());
    }

    // ---------------------------------------------------------------- prueba

    public function test_la_prueba_se_manda_con_la_clave_de_la_persona_y_la_deja_verificada(): void
    {
        $this->servicio()->activar($this->ana, self::ENLACE, true);
        Http::fake(['api.callmebot.com/*' => Http::response(self::EXITO, 200)]);

        $this->servicio()->enviarPrueba($this->ana->fresh());

        Http::assertSentCount(1);
        Http::assertSent(function (ClientRequest $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return $q['phone'] === '+50688887777'
                && $q['apikey'] === '1234567'
                && str_contains($q['text'], 'Hola Ana Mora')
                && str_contains($q['text'], 'Iglesia de Prueba')
                && str_contains($q['text'], 'quedaron activados');
        });
        $this->assertTrue($this->ana->fresh()->activacionWhatsApp->verificado_at->equalTo(now()));
    }

    public function test_si_la_prueba_falla_el_error_queda_y_no_se_verifica(): void
    {
        $this->servicio()->activar($this->ana, self::ENLACE, true);
        Http::fake(['*' => Http::response('ERROR: APIKey is invalid', 200)]);

        $this->expectException(WhatsAppEnvioException::class);
        try {
            $this->servicio()->enviarPrueba($this->ana->fresh());
        } finally {
            $activacion = $this->ana->fresh()->activacionWhatsApp;
            $this->assertNull($activacion->verificado_at);
            $this->assertStringContainsString('APIKey is invalid', (string) $activacion->ultimo_error);
        }
    }

    public function test_sin_activacion_no_hay_prueba_ni_llamada_a_la_red(): void
    {
        Http::fake();

        $this->expectException(WhatsAppNoConfiguradoException::class);
        try {
            $this->servicio()->enviarPrueba($this->ana);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_el_texto_del_consentimiento_lleva_la_iglesia_y_el_numero(): void
    {
        $persona = MensajesWhatsApp::consentimiento('persona', '+506 8888-7777', $this->iglesia);
        $usuario = MensajesWhatsApp::consentimiento('usuario', '+506 8888-7777', $this->iglesia);

        foreach ([$persona, $usuario] as $texto) {
            $this->assertStringContainsString('Iglesia de Prueba', $texto);
            $this->assertStringContainsString('+506 8888-7777', $texto);
            $this->assertStringContainsString('CallMeBot', $texto);
            $this->assertStringContainsString('desactivarlo cuando quiera', $texto);
            $this->assertStringNotContainsString('{', $texto);
        }
        $this->assertStringContainsString('estado de cuenta', $persona);
        $this->assertStringContainsString('avisos del sistema', $usuario);
    }
}

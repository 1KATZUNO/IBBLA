<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Persona;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * Botón «Enviar por WhatsApp» de una persona: personas/{persona}/whatsapp (admin y tesorero).
 * Con el driver por defecto (link) redirige a wa.me con el mensaje y el enlace firmado ya escritos.
 */
class EnviarInformeWhatsAppTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    private Persona $ana;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-11 12:00:00');
        $this->crearIglesiaConAdmin();
        $this->crearCategoriasPorDefecto();
        $this->ana = $this->crearPersona('Ana Mora', '8888-7777');
        $this->crearSobre($this->crearCulto('2026-03-01'), $this->ana, ['diezmo' => 5000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array{0: string, 1: string} URL de wa.me (sin la query) y el texto ya decodificado */
    private function destinoWhatsApp(string $location): array
    {
        $partes = parse_url($location);
        parse_str($partes['query'] ?? '', $query);

        return [$partes['scheme'].'://'.$partes['host'].$partes['path'], $query['text'] ?? ''];
    }

    public function test_el_admin_es_enviado_a_wa_me_con_el_mensaje_y_el_enlace_firmado(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('personas.whatsapp', $this->ana));

        $respuesta->assertRedirect();
        $location = $respuesta->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/50688887777?text=', $location);

        [, $texto] = $this->destinoWhatsApp($location);
        $this->assertStringContainsString('Hola Ana Mora', $texto);
        $this->assertStringContainsString('Iglesia de Prueba', $texto);
        $this->assertStringContainsString('vence el 18 de octubre de 2026', $texto);
        $this->assertMatchesRegularExpression('#https?://[^\s]+/informe/'.$this->ana->id.'\?expires=\d+&signature=\w+#', $texto);
    }

    public function test_el_enlace_del_mensaje_abre_el_pdf_sin_sesion(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('personas.whatsapp', $this->ana));
        [, $texto] = $this->destinoWhatsApp($respuesta->headers->get('Location'));
        preg_match('#https?://[^\s]+/informe/\d+\?[^\s]+#', $texto, $coincidencia);

        auth()->logout();
        $this->flushSession();

        $pdf = $this->get($coincidencia[0]);
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_deja_constancia_en_la_auditoria(): void
    {
        $this->actingAs($this->admin)->get(route('personas.whatsapp', $this->ana));

        $registro = AuditLog::where('action', 'Enviar informe por WhatsApp')->sole();
        $this->assertSame($this->admin->id, $registro->user_id);
        $this->assertSame($this->admin->email, $registro->user_email);
        $this->assertSame('personas.whatsapp', $registro->route);
        $this->assertSame('GET', $registro->method);

        $detalles = json_decode($registro->details, true);
        $this->assertSame($this->ana->id, $detalles['persona_id']);
        $this->assertSame('Ana Mora', $detalles['persona']);
        $this->assertSame('50688887777', $detalles['telefono']);
        $this->assertSame('link', $detalles['driver']);
        $this->assertSame('enlace', $detalles['resultado']);
        $this->assertStringNotContainsString('signature', $registro->details, 'La firma del enlace es un secreto: no va a la auditoría');
    }

    public function test_el_tesorero_tambien_puede(): void
    {
        $this->actingAs($this->crearUsuarioConRol('tesorero'))
            ->get(route('personas.whatsapp', $this->ana))
            ->assertRedirectContains('https://wa.me/50688887777');
    }

    public function test_otros_roles_reciben_403_y_el_visitante_va_al_login(): void
    {
        foreach (['asistente', 'miembro', 'servidor', 'invitado'] as $rol) {
            $this->actingAs($this->crearUsuarioConRol($rol))
                ->get(route('personas.whatsapp', $this->ana))
                ->assertForbidden();
        }

        auth()->logout();
        $this->get(route('personas.whatsapp', $this->ana))->assertRedirect(route('login'));
        $this->assertSame(0, AuditLog::where('action', 'Enviar informe por WhatsApp')->count());
    }

    public function test_sin_telefono_vuelve_con_un_error_que_dice_como_ponerlo(): void
    {
        $sinTelefono = $this->crearPersona('Juan Pérez', null);

        $this->actingAs($this->admin)
            ->from('/personas')
            ->get(route('personas.whatsapp', $sinTelefono))
            ->assertRedirect('/personas')
            ->assertSessionHas('error', fn ($mensaje) => str_contains($mensaje, 'Juan Pérez no tiene teléfono registrado')
                && str_contains($mensaje, 'Editar')
                && str_contains($mensaje, '8888-7777'));

        $this->assertSame(0, AuditLog::where('action', 'Enviar informe por WhatsApp')->count());
    }

    public function test_con_telefono_invalido_el_error_muestra_el_valor_guardado(): void
    {
        $dosNumeros = $this->crearPersona('Rosa Solís', '8888-7777 / 2222-3333');

        $this->actingAs($this->admin)
            ->from('/personas')
            ->get(route('personas.whatsapp', $dosNumeros))
            ->assertRedirect('/personas')
            ->assertSessionHas('error', fn ($mensaje) => str_contains($mensaje, '8888-7777 / 2222-3333')
                && str_contains($mensaje, 'no es válido')
                && str_contains($mensaje, 'deje uno solo'));
    }

    public function test_sin_pagina_previa_el_error_vuelve_a_personas(): void
    {
        $this->actingAs($this->admin)
            ->get(route('personas.whatsapp', $this->crearPersona('Sin Tel', '')))
            ->assertRedirect(route('personas.index'))
            ->assertSessionHas('error');
    }

    public function test_el_error_no_vuelve_al_boton_de_otra_persona(): void
    {
        // Sin Referer Laravel recuerda la última página de la sesión, que puede ser el botón de otra persona.
        $this->actingAs($this->admin)
            ->from(route('personas.whatsapp', $this->ana))
            ->get(route('personas.whatsapp', $this->crearPersona('Sin Tel', null)))
            ->assertRedirect(route('personas.index'));
    }

    public function test_no_se_puede_enviar_el_informe_de_una_persona_de_otra_iglesia(): void
    {
        $ajena = $this->crearPersona('Persona Ajena', '8000-1111', $this->crearOtraIglesia()->id);

        $this->actingAs($this->admin)->get(route('personas.whatsapp', $ajena))->assertNotFound();
    }

    public function test_con_meta_configurado_el_mensaje_sale_solo_y_no_se_abre_wa_me(): void
    {
        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => 'T0KEN', 'whatsapp.meta.phone_number_id' => '555']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $this->actingAs($this->admin)
            ->from('/personas')
            ->get(route('personas.whatsapp', $this->ana))
            ->assertRedirect('/personas')
            ->assertSessionHas('success', fn ($mensaje) => str_contains($mensaje, 'Ana Mora') && str_contains($mensaje, '+506 8888-7777'));

        Http::assertSent(function (ClientRequest $r) {
            return $r->url() === 'https://graph.facebook.com/v21.0/555/messages'
                && $r['to'] === '50688887777'
                && str_contains($r['text']['body'], 'Hola Ana Mora')
                && str_contains($r['text']['body'], '/informe/'.$this->ana->id.'?');
        });

        $detalles = json_decode(AuditLog::where('action', 'Enviar informe por WhatsApp')->sole()->details, true);
        $this->assertSame('meta', $detalles['driver']);
        $this->assertSame('enviado', $detalles['resultado']);
    }

    public function test_si_meta_falla_vuelve_con_el_error_y_la_auditoria_lo_dice(): void
    {
        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => 'T0KEN', 'whatsapp.meta.phone_number_id' => '555']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Recipient not in allowed list', 'code' => 131030]], 400)]);

        $this->actingAs($this->admin)
            ->from('/personas')
            ->get(route('personas.whatsapp', $this->ana))
            ->assertRedirect('/personas')
            ->assertSessionHas('error', fn ($mensaje) => str_contains($mensaje, 'Recipient not in allowed list'));

        $detalles = json_decode(AuditLog::where('action', 'Enviar informe por WhatsApp')->sole()->details, true);
        $this->assertSame('error', $detalles['resultado']);
    }

    public function test_con_meta_sin_credenciales_se_sigue_usando_el_enlace_manual(): void
    {
        config(['whatsapp.driver' => 'meta', 'whatsapp.meta.token' => '', 'whatsapp.meta.phone_number_id' => null]);
        Http::fake();

        $this->actingAs($this->admin)
            ->get(route('personas.whatsapp', $this->ana))
            ->assertRedirectContains('https://wa.me/50688887777');

        Http::assertNothingSent();
    }
}

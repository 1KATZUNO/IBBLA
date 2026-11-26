<?php

namespace Tests\Feature\TarjetasQr;

use App\Models\AuditLog;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaEscenarioTarjetas;
use Tests\TestCase;

/**
 * Lo que pasa al escanear la tarjeta (/t/{token}): crear el acceso, iniciar sesion con el identificador ya
 * escrito, la contrasena nueva tras "Restablecer acceso" y el codigo que ya no sirve.
 */
class AccesoConTarjetaTest extends TestCase
{
    use CreaEscenarioTarjetas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarIglesia();
    }

    public function test_un_codigo_que_no_existe_muestra_una_pagina_amable(): void
    {
        $this->get('/t/'.str_repeat('x', 40))
            ->assertNotFound()
            ->assertSee('Este código ya no es válido')
            ->assertSee('Pídele uno nuevo al administrador');

        $this->get('/t/corto')->assertNotFound()->assertSee('Este código ya no es válido');
        $this->assertGuest();
    }

    public function test_una_iglesia_inactiva_no_abre_la_tarjeta(): void
    {
        $persona = $this->persona();
        $url = $this->urlTarjeta($persona);
        $this->iglesia->update(['activo' => false]);

        $this->get($url)->assertNotFound()->assertSee('Este código ya no es válido');
    }

    public function test_sin_cuenta_crea_su_acceso_con_correo_y_entra_a_mi_perfil(): void
    {
        $persona = $this->persona('Ana Mora');
        $url = $this->urlTarjeta($persona);

        $this->get($url)
            ->assertOk()
            ->assertSee('Crea tu acceso')
            ->assertSee('Hola, Ana Mora')
            ->assertSee('El administrador de la iglesia puede ver esta contraseña para ayudarte si la olvidas. No uses la misma de tu correo ni la de tu banco.');

        $this->post($url, [
            'identificador' => '  Ana.Mora@Correo.TEST ',
            'password' => 'MiClave2026',
            'password_confirmation' => 'MiClave2026',
        ])->assertRedirect(route('mi-perfil.index'));

        $usuario = User::where('email', 'ana.mora@correo.test')->firstOrFail();
        $this->assertAuthenticatedAs($usuario);
        $this->assertNull($usuario->usuario);
        $this->assertSame('miembro', $usuario->rol);
        $this->assertSame($this->iglesia->id, $usuario->tenant_id);
        $this->assertTrue($usuario->esCuentaMiembro());
        $this->assertSame($usuario->id, $persona->fresh()->user_id);
        $this->assertSame('ana.mora@correo.test', $persona->fresh()->correo);
        $this->assertTrue(Hash::check('MiClave2026', $usuario->password));

        // La copia visible esta cifrada en la base (nunca en claro) y se lee con la llave de la app
        $crudo = DB::table('users')->where('id', $usuario->id)->value('password_visible');
        $this->assertStringNotContainsString('MiClave2026', $crudo);
        $this->assertSame('MiClave2026', $usuario->contrasenaVisible());

        $auditoria = AuditLog::where('action', 'Crear acceso con la tarjeta QR')->firstOrFail();
        $this->assertSame($this->iglesia->id, $auditoria->tenant_id);
        $this->assertStringNotContainsString('MiClave2026', $auditoria->details);

        $this->get(route('mi-perfil.index'))->assertOk();
    }

    public function test_sin_correo_crea_un_usuario_y_despues_entra_desde_el_login_normal(): void
    {
        $persona = $this->persona('Pedro Solano');
        $url = $this->urlTarjeta($persona);

        $this->post($url, [
            'identificador' => 'PedroSolano',
            'password' => 'pedro-1234',
            'password_confirmation' => 'pedro-1234',
        ])->assertRedirect(route('mi-perfil.index'));

        $usuario = User::where('usuario', 'pedrosolano')->firstOrFail();
        $this->assertNull($usuario->email);
        $this->assertNull($persona->fresh()->correo);
        $this->assertSame('pedrosolano', $usuario->identificador());

        $this->post(route('logout'));
        $this->assertGuest();

        // El login normal acepta el usuario (sin importar mayusculas)
        $this->post('/login', ['email' => 'PEDROSOLANO', 'password' => 'pedro-1234'])
            ->assertRedirect(route('mi-perfil.index'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_con_cuenta_la_tarjeta_pide_la_contrasena_y_no_entra_sola(): void
    {
        $persona = $this->personaConCuenta('Lucia Vargas', 'lucia-2026');
        $url = $this->urlTarjeta($persona);

        $this->get($url)
            ->assertOk()
            ->assertSee('Entra a tu cuenta')
            ->assertSee('value="lucia.vargas@correo.test"', false)
            ->assertDontSee('Crea tu acceso');
        $this->assertGuest();

        // Contrasena equivocada: no entra y vuelve a la tarjeta con el error
        $this->from($url)->post('/login', ['email' => 'lucia.vargas@correo.test', 'password' => 'otra-cosa'])
            ->assertRedirect($url)
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->from($url)->post('/login', ['email' => 'lucia.vargas@correo.test', 'password' => 'lucia-2026'])
            ->assertRedirect(route('mi-perfil.index'));
        $this->assertAuthenticated();
    }

    public function test_con_cuenta_no_se_puede_crear_otra_desde_la_tarjeta(): void
    {
        $persona = $this->personaConCuenta('Lucia Vargas', 'lucia-2026');
        $url = $this->urlTarjeta($persona);
        $antes = User::count();

        $this->post($url, ['identificador' => 'otra', 'password' => 'nueva-12345', 'password_confirmation' => 'nueva-12345'])
            ->assertRedirect($url);

        $this->assertSame($antes, User::count());
        $this->assertGuest();
        $this->assertTrue(Hash::check('lucia-2026', $persona->user->fresh()->password));
    }

    public function test_valida_el_usuario_o_correo_y_la_contrasena(): void
    {
        $this->personaConCuenta('Lucia Vargas');
        User::create(['name' => 'X', 'usuario' => 'ocupado', 'password' => 'x12345678', 'rol' => 'miembro', 'tenant_id' => $this->iglesia->id]);
        $url = $this->urlTarjeta($this->persona('Nuevo'));

        $casos = [
            [['identificador' => 'lucia.vargas@correo.test'], 'identificador', 'ya está en uso'],
            [['identificador' => 'Ocupado'], 'identificador', 'ya está en uso'],
            [['identificador' => 'con espacio'], 'identificador', 'sin espacios'],
            [['identificador' => 'josé'], 'identificador', 'sin tilde'],
            [['identificador' => 'ab'], 'identificador', 'al menos 3'],
            [['identificador' => 'mal@'], 'identificador', 'no parece válido'],
            [['identificador' => 'bueno', 'password' => 'corta', 'password_confirmation' => 'corta'], 'password', 'al menos 8'],
            [['identificador' => 'bueno', 'password' => 'larga-123', 'password_confirmation' => 'larga-124'], 'password', 'no coinciden'],
        ];

        foreach ($casos as [$datos, $campo, $texto]) {
            $this->from($url)->post($url, $datos + ['password' => 'valida-123', 'password_confirmation' => 'valida-123'])
                ->assertRedirect($url)
                ->assertSessionHasErrors($campo);
            $this->assertStringContainsString($texto, session('errors')->first($campo), json_encode($datos));
        }

        $this->assertGuest();
        $this->assertNull(User::where('usuario', 'bueno')->first());
    }

    public function test_tras_restablecer_acceso_el_escaneo_pide_una_contrasena_nueva(): void
    {
        $persona = $this->personaConCuenta('Lucia Vargas', 'vieja-1234');
        $url = $this->urlTarjeta($persona);

        $this->actingAs($this->admin)->postJson(route('personas.tarjeta.restablecer', $persona))->assertOk();
        $this->post(route('logout'));

        $usuario = $persona->user->fresh();
        $this->assertTrue($usuario->debe_crear_contrasena);
        $this->assertNull($usuario->password_visible);
        $this->assertFalse(Hash::check('vieja-1234', $usuario->password));

        // La contrasena vieja ya no entra
        $this->post('/login', ['email' => 'lucia.vargas@correo.test', 'password' => 'vieja-1234']);
        $this->assertGuest();

        $this->get($url)->assertOk()->assertSee('Crea tu contraseña nueva')->assertSee('lucia.vargas@correo.test');

        $this->post($url, ['password' => 'nueva-2026', 'password_confirmation' => 'nueva-2026'])
            ->assertRedirect(route('mi-perfil.index'));
        $this->assertAuthenticatedAs($usuario);

        $usuario->refresh();
        $this->assertFalse($usuario->debe_crear_contrasena);
        $this->assertTrue(Hash::check('nueva-2026', $usuario->password));
        $this->assertSame('nueva-2026', $usuario->contrasenaVisible());
        $this->assertTrue(AuditLog::where('action', 'Crear contraseña nueva con la tarjeta QR')->exists());

        // Ya con contrasena, la tarjeta vuelve a ser solo el inicio de sesion
        $this->post(route('logout'));
        $this->get($url)->assertOk()->assertSee('Entra a tu cuenta');
    }

    public function test_con_la_sesion_abierta(): void
    {
        $persona = $this->personaConCuenta('Lucia Vargas');
        $url = $this->urlTarjeta($persona);

        // Su propia cuenta: directo a su inicio
        $this->actingAs($persona->user)->get($url)->assertRedirect(route('mi-perfil.index'));

        // Otra cuenta: le pide cerrar la sesion, no mezcla cuentas
        $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertSee('Tienes otra sesión abierta')
            ->assertSee('Lucia Vargas');
    }

    public function test_el_aviso_de_contrasena_visible_desaparece_con_el_interruptor_apagado(): void
    {
        config(['ibbla.tarjetas.contrasena_visible' => false]);
        $persona = $this->persona();
        $url = $this->urlTarjeta($persona);

        $this->get($url)->assertOk()->assertDontSee('El administrador de la iglesia puede ver esta contraseña');

        $this->post($url, ['identificador' => 'anamora', 'password' => 'clave-1234', 'password_confirmation' => 'clave-1234']);
        $this->assertNull(DB::table('users')->where('usuario', 'anamora')->value('password_visible'));
    }

    public function test_las_rutas_publicas_tienen_limite_de_intentos(): void
    {
        $url = '/t/'.str_repeat('y', 40);
        for ($i = 0; $i < 30; $i++) {
            $this->get($url)->assertNotFound();
        }
        $this->get($url)->assertStatus(429);

        $persona = $this->persona();
        $urlPersona = $this->urlTarjeta($persona);
        for ($i = 0; $i < 10; $i++) {
            $this->post($urlPersona, ['identificador' => 'x'])->assertStatus(302);
        }
        $this->post($urlPersona, ['identificador' => 'x'])->assertStatus(429);
    }

    public function test_el_token_es_aleatorio_de_40_caracteres_y_no_sale_en_json(): void
    {
        $a = $this->persona('A');
        $b = $this->persona('B');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $a->asegurarTokenAcceso());
        $this->assertNotSame($a->token_acceso, $b->asegurarTokenAcceso());
        $this->assertSame($a->token_acceso, $a->fresh()->asegurarTokenAcceso());
        $this->assertArrayNotHasKey('token_acceso', $a->fresh()->toArray());
        $this->assertSame(1, Persona::where('token_acceso', $a->token_acceso)->count());
    }
}

<?php

namespace Tests\Feature\TarjetasQr;

use App\Models\AuditLog;
use App\Models\ClaseAsistencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaEscenarioTarjetas;
use Tests\TestCase;

/**
 * La tarjeta flotante de Personas y sus acciones: solo el Administrador; Ver contrasena solo de cuentas miembro,
 * auditada y detras del interruptor; Restablecer acceso; Generar QR nuevo.
 */
class TarjetaPersonaAdminTest extends TestCase
{
    use CreaEscenarioTarjetas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarIglesia();
    }

    public function test_solo_el_administrador_usa_las_tarjetas(): void
    {
        $persona = $this->personaConCuenta('Lucia Vargas');
        $get = [
            route('personas.tarjeta.show', $persona),
            route('personas.tarjeta.pdf', $persona),
            route('personas.tarjetas.index'),
            route('personas.tarjetas.pdf'),
        ];
        $post = [
            route('personas.tarjeta.contrasena', $persona),
            route('personas.tarjeta.restablecer', $persona),
            route('personas.tarjeta.nuevo-qr', $persona),
        ];

        foreach (['tesorero', 'asistente', 'miembro', 'servidor'] as $rol) {
            $usuario = $this->crearUsuario($this->iglesia, $rol);
            foreach ($get as $url) {
                $this->actingAs($usuario)->get($url)->assertForbidden();
            }
            foreach ($post as $url) {
                $this->actingAs($usuario)->postJson($url)->assertForbidden();
            }
        }

        foreach ($get as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        // Sin sesion, al login
        auth()->logout();
        $this->get($get[0])->assertRedirect(route('login'));

        // La persona de otra iglesia no existe para este administrador
        $otra = $this->crearIglesia('otra-tq', 'OTQ');
        $ajena = \App\Models\Persona::create(['nombre' => 'Ajena', 'activo' => true, 'tenant_id' => $otra->id]);
        $this->actingAs($this->admin)->get(route('personas.tarjeta.show', $ajena))->assertNotFound();
    }

    public function test_la_tarjeta_flotante_genera_el_qr_y_muestra_nombre_clase_y_cuenta(): void
    {
        $persona = $this->persona('Ana Mora');
        $clase = ClaseAsistencia::create(['nombre' => 'Damas', 'slug' => 'damas', 'orden' => 1, 'activa' => true, 'tenant_id' => $this->iglesia->id]);
        $persona->clasesAsistencia()->attach($clase->id, ['es_maestro' => false]);
        $this->assertNull($persona->token_acceso);

        $html = $this->actingAs($this->admin)->get(route('personas.tarjeta.show', $persona))
            ->assertOk()
            ->assertSee('Ana Mora')
            ->assertSee('Damas')
            ->assertSee('Escanea para entrar')
            ->assertSee('Sin cuenta')
            ->assertSee('Descargar tarjeta (PDF)')
            ->assertSee('Generar QR nuevo')
            ->assertDontSee('Ver contraseña')
            ->assertSee('<svg', false)
            ->getContent();

        $token = $persona->fresh()->token_acceso;
        $this->assertNotNull($token);
        $this->assertStringContainsString('Código QR de acceso de Ana Mora', $html);

        // Abrirla otra vez no cambia el QR
        $this->actingAs($this->admin)->get(route('personas.tarjeta.show', $persona))->assertOk();
        $this->assertSame($token, $persona->fresh()->token_acceso);
    }

    public function test_la_pantalla_de_personas_tiene_el_boton_tarjeta_y_la_descarga_masiva(): void
    {
        $persona = $this->persona('Ana Mora');

        $this->actingAs($this->admin)->get(route('personas.index'))
            ->assertOk()
            ->assertSee('data-abrir-tarjeta="'.route('personas.tarjeta.show', $persona).'"', false)
            ->assertSee('Tarjeta con QR de Ana Mora')
            ->assertSee('Tarjetas con QR')
            ->assertSee('id="modalTarjeta"', false);
    }

    public function test_ver_contrasena_de_un_miembro_queda_en_la_auditoria_sin_el_valor(): void
    {
        $persona = $this->personaConCuenta('Lucia Vargas', 'lucia-2026');

        $this->actingAs($this->admin)->get(route('personas.tarjeta.show', $persona))
            ->assertSee('Ver contraseña')
            ->assertSee('Con cuenta')
            ->assertSee('lucia.vargas@correo.test');

        $this->actingAs($this->admin)->postJson(route('personas.tarjeta.contrasena', $persona))
            ->assertOk()
            ->assertJson(['contrasena' => 'lucia-2026'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $fila = AuditLog::where('action', 'Ver contraseña de un miembro')->sole();
        $this->assertSame($this->admin->id, $fila->user_id);
        $this->assertSame($this->iglesia->id, $fila->tenant_id);
        $this->assertStringContainsString('Lucia Vargas', $fila->details);
        $this->assertStringNotContainsString('lucia-2026', $fila->details);
        // Y nada mas en la auditoria lleva la contrasena
        $this->assertSame(0, AuditLog::where('details', 'like', '%lucia-2026%')->count());
    }

    public function test_ver_contrasena_solo_de_cuentas_miembro(): void
    {
        $tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $persona = $this->persona('Tesorera');
        $persona->forceFill(['user_id' => $tesorero->id])->save();

        $this->actingAs($this->admin)->get(route('personas.tarjeta.show', $persona))
            ->assertOk()
            ->assertDontSee('Ver contraseña')
            ->assertDontSee('Restablecer acceso')
            ->assertSee('su contraseña no se puede ver ni restablecer desde aquí');

        $this->actingAs($this->admin)->postJson(route('personas.tarjeta.contrasena', $persona))->assertStatus(422);
        $this->actingAs($this->admin)->postJson(route('personas.tarjeta.restablecer', $persona))->assertStatus(422);
        $this->assertFalse($tesorero->fresh()->debe_crear_contrasena);

        $sinCuenta = $this->persona('Sin cuenta');
        $this->actingAs($this->admin)->postJson(route('personas.tarjeta.contrasena', $sinCuenta))->assertStatus(422);
    }

    public function test_la_copia_visible_nunca_existe_para_otros_roles(): void
    {
        foreach (['admin', 'tesorero', 'asistente', 'servidor'] as $rol) {
            $usuario = $this->crearUsuario($this->iglesia, $rol);
            $usuario->establecerContrasena('clave-de-'.$rol);
            $usuario->save();
            $this->assertNull(DB::table('users')->where('id', $usuario->id)->value('password_visible'), $rol);
        }

        $super = $this->crearSuperAdmin();
        $super->establecerContrasena('clave-super');
        $super->save();
        $this->assertNull(DB::table('users')->where('id', $super->id)->value('password_visible'));

        // Un miembro al que el administrador le cambia el rol pierde la copia
        $persona = $this->personaConCuenta('Lucia Vargas', 'lucia-2026');
        $usuario = $persona->user;
        $this->assertNotNull(DB::table('users')->where('id', $usuario->id)->value('password_visible'));

        $this->actingAs($this->admin)->put(route('usuarios.update', $usuario), [
            'name' => $usuario->name,
            'email' => $usuario->email,
            'rol' => 'tesorero',
        ])->assertRedirect(route('usuarios.index'));
        $this->assertNull(DB::table('users')->where('id', $usuario->id)->value('password_visible'));
    }

    public function test_el_administrador_crea_un_miembro_en_usuarios_y_la_copia_queda(): void
    {
        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Nuevo Miembro',
            'email' => 'nuevo@correo.test',
            'password' => 'nuevo-1234',
            'password_confirmation' => 'nuevo-1234',
            'rol' => 'miembro',
        ])->assertRedirect(route('usuarios.index'));

        $usuario = User::where('email', 'nuevo@correo.test')->sole();
        $this->assertTrue(Hash::check('nuevo-1234', $usuario->password));
        $this->assertSame('nuevo-1234', $usuario->contrasenaVisible());
    }

    public function test_si_el_miembro_cambia_su_contrasena_la_copia_se_actualiza(): void
    {
        $persona = $this->personaConCuenta('Lucia Vargas', 'lucia-2026');
        $usuario = $persona->user;

        $this->actingAs($usuario)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('El administrador de la iglesia puede ver esta contraseña');

        $this->actingAs($usuario)->from(route('profile.edit'))->put(route('password.update'), [
            'current_password' => 'lucia-2026',
            'password' => 'NuevaClave-99',
            'password_confirmation' => 'NuevaClave-99',
        ])->assertSessionHasNoErrors();

        $this->assertSame('NuevaClave-99', $usuario->fresh()->contrasenaVisible());

        // Si cambia por otra via (recuperar por correo), la copia vieja se borra: nunca se muestra una vieja
        $usuario->fresh()->forceFill(['password' => Hash::make('por-correo-1')])->save();
        $this->assertNull($usuario->fresh()->contrasenaVisible());
    }

    public function test_con_el_interruptor_apagado_ver_contrasena_no_existe(): void
    {
        $persona = $this->personaConCuenta('Lucia Vargas', 'lucia-2026');
        config(['ibbla.tarjetas.contrasena_visible' => false]);

        $this->actingAs($this->admin)->get(route('personas.tarjeta.show', $persona))
            ->assertOk()
            ->assertDontSee('Ver contraseña')
            ->assertSee('Restablecer acceso');
        $this->actingAs($this->admin)->postJson(route('personas.tarjeta.contrasena', $persona))->assertNotFound();
        $this->assertNull($persona->user->fresh()->contrasenaVisible());
        $this->assertFalse(AuditLog::where('action', 'Ver contraseña de un miembro')->exists());

        // Y no se guardan copias nuevas
        $usuario = $persona->user->fresh();
        $usuario->establecerContrasena('otra-clave-1');
        $usuario->save();
        $this->assertNull(DB::table('users')->where('id', $usuario->id)->value('password_visible'));
    }

    public function test_restablecer_acceso_borra_la_contrasena_cierra_sesiones_y_queda_auditado(): void
    {
        $persona = $this->personaConCuenta('Lucia Vargas', 'lucia-2026');
        $usuario = $persona->user;

        $respuesta = $this->actingAs($this->admin)->postJson(route('personas.tarjeta.restablecer', $persona))
            ->assertOk()
            ->assertJson(['ok' => true]);
        $this->assertStringContainsString('Debe crear contraseña nueva', $respuesta->json('html'));

        $usuario->refresh();
        $this->assertTrue($usuario->debe_crear_contrasena);
        $this->assertNull($usuario->password_visible);
        $this->assertFalse(Hash::check('lucia-2026', $usuario->password));
        $this->assertTrue(AuditLog::where('action', 'Restablecer acceso de un miembro')->exists());

        // Sin JavaScript tambien funciona: vuelve a Personas con el aviso
        $this->actingAs($this->admin)->post(route('personas.tarjeta.restablecer', $persona))
            ->assertRedirect(route('personas.index'))
            ->assertSessionHas('success');
    }

    public function test_generar_qr_nuevo_invalida_la_tarjeta_anterior(): void
    {
        $persona = $this->persona('Ana Mora');
        $viejo = $persona->asegurarTokenAcceso();
        $this->get('/t/'.$viejo)->assertOk();

        $this->actingAs($this->admin)->postJson(route('personas.tarjeta.nuevo-qr', $persona))
            ->assertOk()
            ->assertJsonPath('ok', true);
        auth()->logout();

        $nuevo = $persona->fresh()->token_acceso;
        $this->assertNotSame($viejo, $nuevo);
        $this->get('/t/'.$viejo)->assertNotFound()->assertSee('Este código ya no es válido');
        $this->get('/t/'.$nuevo)->assertOk()->assertSee('Crea tu acceso');

        $fila = AuditLog::where('action', 'Generar QR nuevo de una persona')->sole();
        $this->assertStringNotContainsString($nuevo, $fila->details);
        $this->assertStringNotContainsString($viejo, $fila->details);
    }

    public function test_el_pdf_de_una_tarjeta(): void
    {
        $persona = $this->persona('Ana Mora');

        $respuesta = $this->actingAs($this->admin)->get(route('personas.tarjeta.pdf', $persona))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
        $this->assertStringContainsString('tarjeta-ana-mora.pdf', $respuesta->headers->get('Content-Disposition'));
        $this->assertNotNull($persona->fresh()->token_acceso);
    }
}

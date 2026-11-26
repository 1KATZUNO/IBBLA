<?php

namespace Tests\Feature\TarjetasQr;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioTarjetas;
use Tests\TestCase;

/**
 * El login normal acepta correo o nombre de usuario. Lo de siempre (cuentas con correo) no cambia.
 */
class LoginUsuarioOCorreoTest extends TestCase
{
    use CreaEscenarioTarjetas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarIglesia();
    }

    private function cuentaConUsuario(string $usuario = 'mariarojas', string $clave = 'maria-1234'): User
    {
        return $this->personaConCuenta('Maria Rojas', $clave, ['email' => null, 'usuario' => $usuario])->user;
    }

    public function test_el_login_ofrece_correo_o_usuario(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Correo electrónico o usuario')
            ->assertSee('type="text" name="email"', false);
    }

    public function test_quien_ya_entraba_con_correo_sigue_entrando_igual(): void
    {
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'password'])
            ->assertRedirect(route('principal'));
        $this->assertAuthenticatedAs($this->admin);
        $this->post(route('logout'));

        // Mayusculas en el correo, como antes
        $this->post('/login', ['email' => strtoupper($this->admin->email), 'password' => 'password']);
        $this->assertAuthenticatedAs($this->admin);
        $this->post(route('logout'));

        $this->from('/login')->post('/login', ['email' => $this->admin->email, 'password' => 'mala'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_entra_con_su_nombre_de_usuario(): void
    {
        $usuario = $this->cuentaConUsuario();

        $this->post('/login', ['email' => ' MariaRojas ', 'password' => 'maria-1234'])
            ->assertRedirect(route('mi-perfil.index'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_usuario_con_contrasena_equivocada_no_entra_y_la_auditoria_no_guarda_la_contrasena(): void
    {
        $this->cuentaConUsuario();

        $this->from('/login')->post('/login', ['email' => 'mariarojas', 'password' => 'NoEsLaClave99'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $fallido = AuditLog::where('action', AuditLog::ACCION_LOGIN_FALLIDO)->latest('id')->firstOrFail();
        $this->assertSame('mariarojas', $fallido->user_email);
        $this->assertSame(0, AuditLog::where('details', 'like', '%NoEsLaClave99%')->count());
    }

    public function test_un_usuario_que_no_existe_no_entra(): void
    {
        $this->post('/login', ['email' => 'nadie', 'password' => 'lo-que-sea']);
        $this->assertGuest();
    }

    public function test_la_cuenta_con_usuario_edita_su_perfil_sin_correo(): void
    {
        $usuario = $this->cuentaConUsuario();

        $this->actingAs($usuario)->get(route('profile.edit'))->assertOk()->assertSee('mariarojas');

        $this->actingAs($usuario)->patch(route('profile.update'), ['name' => 'María Rojas', 'email' => ''])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));
        $this->assertSame('María Rojas', $usuario->fresh()->name);
        $this->assertNull($usuario->fresh()->email);
    }

    public function test_el_administrador_edita_en_usuarios_una_cuenta_sin_correo(): void
    {
        $usuario = $this->cuentaConUsuario();

        $this->actingAs($this->admin)->get(route('usuarios.index'))->assertOk()->assertSee('Usuario: mariarojas');
        $this->actingAs($this->admin)->get(route('usuarios.edit', $usuario))->assertOk()->assertSee('Entra con el usuario');

        $this->actingAs($this->admin)->put(route('usuarios.update', $usuario), [
            'name' => 'Maria Rojas',
            'email' => '',
            'rol' => 'miembro',
        ])->assertSessionHasNoErrors()->assertRedirect(route('usuarios.index'));

        // A una cuenta con correo se le sigue exigiendo
        $this->actingAs($this->admin)->put(route('usuarios.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => '',
            'rol' => 'admin',
        ])->assertSessionHasErrors('email');
    }
}

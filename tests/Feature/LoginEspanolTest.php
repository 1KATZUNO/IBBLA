<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login limpio y en español: sin "Entrar como invitado" y sin textos en ingles, aunque el .env de
 * produccion traiga APP_LOCALE=en (el idioma queda fijo en config/app.php).
 */
class LoginEspanolTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_idioma_de_la_app_es_espanol(): void
    {
        $this->assertSame('es', config('app.locale'));
        $this->assertSame('es', app()->getLocale());
    }

    public function test_el_login_esta_en_espanol_y_no_ofrece_entrar_como_invitado(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Iniciar sesión')
            ->assertSee('Correo electrónico')
            ->assertSee('Contraseña')
            ->assertSee('¿Olvidaste tu contraseña?')
            ->assertDontSee('Entrar como Invitado')
            ->assertDontSee('invitado', false)
            ->assertDontSee('Remember me')
            ->assertDontSee('Forgot your password');
    }

    public function test_los_errores_de_validacion_del_login_salen_en_espanol(): void
    {
        $this->from('/login')->post('/login', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email', 'password']);

        $mensaje = session('errors')->first('email');
        $this->assertStringNotContainsString('required', $mensaje);
        $this->assertStringContainsStringIgnoringCase('obligatorio', $mensaje);
    }

    public function test_credenciales_incorrectas_responden_en_espanol(): void
    {
        $this->from('/login')->post('/login', ['email' => 'nadie@example.com', 'password' => 'incorrecta'])
            ->assertSessionHasErrors('email');

        $this->assertStringNotContainsString('credentials', session('errors')->first('email'));
    }

    public function test_los_archivos_de_idioma_base_existen(): void
    {
        foreach (['validation', 'auth', 'pagination', 'passwords'] as $archivo) {
            $this->assertFileExists(lang_path("es/{$archivo}.php"));
        }
        $this->assertNotSame('validation.required', __('validation.required', ['attribute' => 'x']));
    }
}

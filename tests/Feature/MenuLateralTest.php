<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El menu lateral debe mostrar a cada rol solo lo que puede abrir: ningun enlace del menu
 * puede responder 403 (antes "Reportes" le daba 403 al asistente) y las pantallas que
 * existian pero no tenian enlace (dashboards de promesas/asistencia, ministerios,
 * carga historica) deben aparecer para quien tiene permiso.
 */
class MenuLateralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminUserSeeder::class);
    }

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function asistente(): User
    {
        $tenant = Tenant::where('slug', 'ibbsc')->firstOrFail();

        return User::create([
            'name' => 'Asistente Prueba',
            'email' => 'asistente.prueba@ibbsc.com',
            'password' => Hash::make('no-se-usa-en-la-prueba'),
            'rol' => 'asistente',
            'is_super_admin' => false,
            'tenant_id' => $tenant->id,
            'tenant_role_id' => TenantRole::where('tenant_id', $tenant->id)->where('slug', 'asistente')->value('id'),
        ]);
    }

    /** @return array<int, string> rutas (path) de los enlaces del menu lateral */
    private function enlacesDelMenu(User $user): array
    {
        $html = $this->actingAs($user)->get('/principal')->assertOk()->getContent();
        preg_match_all('/<a href="([^"]+)"\s+class="nav-link/', $html, $m);

        return array_map(fn ($url) => parse_url($url, PHP_URL_PATH), $m[1]);
    }

    private function assertTodosLosEnlacesAbren(User $user): void
    {
        foreach ($this->enlacesDelMenu($user) as $path) {
            $status = $this->actingAs($user)->get($path)->getStatusCode();
            $this->assertContains($status, [200, 302], "El enlace del menu {$path} responde {$status} para el rol {$user->rol}");
        }
    }

    public function test_admin_ve_las_pantallas_que_antes_no_tenian_enlace(): void
    {
        $enlaces = $this->enlacesDelMenu($this->usuario('admin@ibbsc.com'));

        foreach (['/dashboard/promesas', '/dashboard/asistencia', '/admin/ministerios', '/admin/asistencia/importar', '/app/clase', '/ingresos-asistencia'] as $path) {
            $this->assertContains($path, $enlaces, "Falta {$path} en el menu del admin");
        }
    }

    public function test_ningun_enlace_del_menu_da_error_para_admin(): void
    {
        $this->assertTodosLosEnlacesAbren($this->usuario('admin@ibbsc.com'));
    }

    public function test_tesorero_ve_recuento_y_dashboards_pero_no_administracion(): void
    {
        $tesorero = $this->usuario('tesorero@ibbsc.com');
        $enlaces = $this->enlacesDelMenu($tesorero);

        $this->assertContains('/recuento', $enlaces);
        $this->assertContains('/dashboard/promesas', $enlaces);
        $this->assertContains('/ingresos-asistencia', $enlaces);
        $this->assertNotContains('/asistencia', $enlaces);
        $this->assertNotContains('/personas', $enlaces);
        $this->assertTodosLosEnlacesAbren($tesorero);
    }

    public function test_asistente_entra_a_reportes_de_asistencia_sin_403(): void
    {
        $asistente = $this->asistente();
        $enlaces = $this->enlacesDelMenu($asistente);

        $this->assertContains('/asistencia', $enlaces);
        $this->assertContains('/ingresos-asistencia/asistencia', $enlaces);
        $this->assertNotContains('/ingresos-asistencia', $enlaces);
        $this->assertNotContains('/recuento', $enlaces);
        $this->assertTodosLosEnlacesAbren($asistente);
    }

    public function test_los_mensajes_de_aviso_del_controlador_se_muestran(): void
    {
        // RecuentoController avisa con 'warning' cuando detecta un sobre duplicado; antes el layout no lo pintaba.
        $this->actingAs($this->usuario('admin@ibbsc.com'))
            ->withSession(['warning' => 'Ya se registro un sobre para esta persona'])
            ->get('/principal')
            ->assertOk()
            ->assertSee('Ya se registro un sobre para esta persona');
    }
}

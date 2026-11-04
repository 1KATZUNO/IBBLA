<?php

namespace Tests\Feature;

use App\Models\ClaseAsistencia;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * QA-013: la API de la app de asistencia pedia solo sesion. Ahora sigue las reglas de la web:
 * admin y asistente todo; maestra solo sus clases; tesorero y miembro 403; login con limite.
 */
class AsistenciaAppPermisosTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    private ClaseAsistencia $ninos;

    private ClaseAsistencia $jovenes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
        $this->ninos = ClaseAsistencia::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Niños', 'slug' => 'ninos', 'activa' => true, 'orden' => 1]);
        $this->jovenes = ClaseAsistencia::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Jóvenes', 'slug' => 'jovenes', 'activa' => true, 'orden' => 2]);
    }

    public function test_miembro_y_tesorero_reciben_403_en_lecturas_y_escrituras(): void
    {
        $culto = $this->crearCulto($this->iglesia, '2026-11-01');

        foreach (['miembro', 'tesorero', 'servidor'] as $rol) {
            $usuario = $this->crearUsuario($this->iglesia, $rol);
            $this->actingAs($usuario)->getJson('/asistencia-app/clases')->assertForbidden();
            $this->actingAs($usuario)->getJson('/asistencia-app/cultos')->assertForbidden();
            $this->actingAs($usuario)->postJson('/asistencia-app/personas/quick-add', ['nombre' => 'X', 'clase_asistencia_id' => $this->ninos->id])->assertForbidden();
            $this->actingAs($usuario)->postJson('/asistencia-app/asistencia/clase', ['culto_id' => $culto->id, 'clase_asistencia_id' => $this->ninos->id, 'hombres' => 1, 'mujeres' => 1])->assertForbidden();
            $this->actingAs($usuario)->postJson('/asistencia-app/asistencia/capilla', ['culto_id' => $culto->id, 'chapel_adultos_hombres' => 1, 'chapel_adultos_mujeres' => 1])->assertForbidden();
        }

        $this->assertDatabaseMissing('personas', ['nombre' => 'X']);
    }

    public function test_el_asistente_usa_toda_la_app(): void
    {
        $asistente = $this->crearUsuario($this->iglesia, 'asistente');

        $this->actingAs($asistente)->getJson('/asistencia-app/clases')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_la_maestra_solo_ve_y_registra_su_clase(): void
    {
        $culto = $this->crearCulto($this->iglesia, '2026-11-01');
        $maestra = $this->crearUsuario($this->iglesia, 'miembro', ['clase_asistencia_id' => $this->ninos->id]);

        $this->actingAs($maestra)->getJson('/asistencia-app/clases')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $this->ninos->id);

        $this->actingAs($maestra)->postJson('/asistencia-app/asistencia/clase', ['culto_id' => $culto->id, 'clase_asistencia_id' => $this->jovenes->id, 'hombres' => 1, 'mujeres' => 1])
            ->assertForbidden();
        $this->actingAs($maestra)->postJson('/asistencia-app/asistencia/capilla', ['culto_id' => $culto->id, 'chapel_adultos_hombres' => 1, 'chapel_adultos_mujeres' => 1])
            ->assertForbidden();
        $this->actingAs($maestra)->postJson('/asistencia-app/asistencia/clase', ['culto_id' => $culto->id, 'clase_asistencia_id' => $this->ninos->id, 'hombres' => 3, 'mujeres' => 2])
            ->assertOk();
    }

    public function test_el_login_de_la_app_rechaza_a_quien_no_registra_asistencia_y_tiene_limite(): void
    {
        $this->crearUsuario($this->iglesia, 'tesorero', ['email' => 'teso@ibbla.test']);

        $this->postJson('/asistencia-app/login', ['email' => 'teso@ibbla.test', 'password' => 'password'])
            ->assertForbidden();
        $this->assertGuest();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/asistencia-app/login', ['email' => 'nadie@ibbla.test', 'password' => 'mala']);
        }
        $this->postJson('/asistencia-app/login', ['email' => 'nadie@ibbla.test', 'password' => 'mala'])
            ->assertStatus(429);
    }
}

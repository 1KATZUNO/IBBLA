<?php

namespace Tests\Feature\Dashboard;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConDatosDeDashboard;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * QA-043: el día 5 el dashboard decía "Los ingresos bajaron 74,8 % respecto a octubre" (5 días contra
 * un mes completo) y "202 de 206 promesas aún pendientes este mes" (el mes en curso no se cobra).
 * Hoy se fija en el 05/11/2026.
 */
class DashboardMesEnCursoTest extends TestCase
{
    use ConDatosDeDashboard, ConIglesia, RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05 09:00:00');
        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');

        // Octubre: 1.000 el 04/10 y 9.000 en el resto del mes; noviembre: 1.100 el 01/11
        $this->culto($this->iglesia, '2026-10-04', ['diezmo' => 1000], 100);
        $this->culto($this->iglesia, '2026-10-11', ['diezmo' => 3000], 100);
        $this->culto($this->iglesia, '2026-10-18', ['diezmo' => 3000], 100);
        $this->culto($this->iglesia, '2026-10-25', ['diezmo' => 3000], 100);
        $this->culto($this->iglesia, '2026-11-01', ['diezmo' => 1100], 100);
    }

    private function dashboard(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('dashboard', $query));
    }

    public function test_el_mes_en_curso_se_compara_con_los_mismos_dias_del_mes_anterior(): void
    {
        $respuesta = $this->dashboard()->assertOk();

        $comparativo = $respuesta->viewData('comparativo');
        // 1 al 5 de noviembre (1.100) contra 1 al 5 de octubre (1.000): +10 %, no -89 %
        $this->assertEquals(1000, $comparativo['periodo_anterior_total']);
        $this->assertSame('01/10/2026 – 05/10/2026', $comparativo['periodo_anterior_nombre']);
        $this->assertEquals(10.0, $comparativo['porcentaje']);
        $this->assertSame('mes en curso (5 días)', $comparativo['en_curso']);
        $respuesta->assertSee('mes en curso (5 días): mismos días');

        $mensajes = $respuesta->viewData('alertas')->pluck('mensaje')->implode(' | ');
        $this->assertStringNotContainsString('bajaron', $mensajes);
        $this->assertStringNotContainsString('respecto a octubre 2026', $mensajes);
    }

    public function test_si_bajan_respecto_a_los_mismos_dias_la_alerta_lo_dice_asi(): void
    {
        $this->culto($this->iglesia, '2026-10-02', ['diezmo' => 9000]);

        $mensajes = $this->dashboard()->viewData('alertas')->pluck('mensaje')->implode(' | ');

        // 1.100 contra 10.000 del 1 al 5 de octubre
        $this->assertStringContainsString(
            'Los ingresos bajaron 89% respecto a los mismos días de octubre 2026 (01/10/2026 – 05/10/2026; mes en curso (5 días))',
            $mensajes
        );
    }

    public function test_un_mes_ya_terminado_se_sigue_comparando_completo(): void
    {
        $comparativo = $this->dashboard(['mes' => 10, 'año' => 2026])->viewData('comparativo');

        $this->assertNull($comparativo['en_curso']);
        $this->assertSame('septiembre 2026', $comparativo['periodo_anterior_nombre']);
    }

    public function test_las_promesas_pendientes_son_al_ultimo_mes_completo_y_no_del_mes_en_curso(): void
    {
        $octubre = \App\Models\Culto::where('fecha', '2026-10-11')->firstOrFail();
        $alDia = $this->personaConPromesa($this->iglesia, 'Al día', 'misiones', 1000);
        $this->sobre($octubre, $alDia, 'misiones', 1000);
        $this->personaConPromesa($this->iglesia, 'Pendiente', 'misiones', 1000);
        $this->personaConPromesa($this->iglesia, 'Otra pendiente', 'misiones', 1000);

        $respuesta = $this->dashboard()->assertOk();

        $status = $respuesta->viewData('promesasStatus');
        // Noviembre no se cobra todavía: se evalúa al 31/10
        $this->assertSame([1, 2, '2026-10-31'], [$status['cumplidas'], $status['pendientes'], $status['corte']->toDateString()]);

        $mensajes = $respuesta->viewData('alertas')->pluck('mensaje')->implode(' | ');
        $this->assertStringContainsString('2 de 3 personas con promesa están pendientes al 31/10/2026 (último mes completo)', $mensajes);
        $this->assertStringNotContainsString('este mes', $mensajes);
        $respuesta->assertSee('Al día con su promesa')->assertSee('al 31/10/2026');
    }
}

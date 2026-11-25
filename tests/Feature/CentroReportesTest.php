<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Centro de reportes (/ingresos-asistencia): la puerta unica a todos los reportes.
 * El tesorero no ve Personas, asi que estas tarjetas son su unico camino a Reporte General,
 * cumplimiento y estados de cuenta.
 */
class CentroReportesTest extends TestCase
{
    use ConIglesia;
    use RefreshDatabase;

    private Tenant $iglesia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia('ibbla-hub', 'HUB');
    }

    private function hub(string $rol)
    {
        $u = $this->crearUsuario($this->iglesia, $rol);

        return $this->actingAs($u)->get(route('ingresos-asistencia.index'));
    }

    public function test_el_tesorero_ve_los_reportes_financieros_y_los_de_asistencia(): void
    {
        $r = $this->hub('tesorero')->assertOk();

        foreach (['reportes.cumplimiento', 'reportes.estados-cuenta', 'reportes.cultos', 'reportes.ingresos'] as $ruta) {
            $r->assertSee('href="'.route($ruta).'"', false);
        }
        $r->assertSee(route('personas.reporte-general', ['accion' => 'ver']), false)
            ->assertSee(route('personas.reporte-pdf', ['accion' => 'ver']), false)
            ->assertSee(route('ingresos-asistencia.promesas'), false)
            // Decision 5 de 04-DECISIONES: la asistencia es para admin, tesorero y asistente
            ->assertSee('href="'.route('ingresos-asistencia.asistencia').'"', false)
            ->assertSee('href="'.route('ingresos-asistencia.asistencia-clases').'"', false)
            // El reporte de ingresos viejo ya no se enlaza: lo reemplaza el nuevo
            ->assertDontSee('href="'.route('ingresos-asistencia.ingresos').'"', false);
    }

    public function test_las_tarjetas_nuevas_dicen_que_contienen_y_en_que_formatos(): void
    {
        $this->hub('admin')->assertOk()
            ->assertSeeInOrder(['Reporte de cultos', 'sobres, efectivo, transferencias, suelto, egresos y firmas', 'Pantalla, PDF y Excel'])
            ->assertSeeInOrder(['Reporte de ingresos', 'comparados con el periodo anterior', 'Pantalla, PDF y Excel'])
            ->assertSeeInOrder(['Asistencia por culto', 'rango de fechas'])
            ->assertSeeInOrder(['Asistencia por clase', 'una clase o todas']);
    }

    public function test_el_admin_ve_todas_las_tarjetas(): void
    {
        $r = $this->hub('admin')->assertOk();

        foreach (['reportes.cumplimiento', 'reportes.estados-cuenta', 'reportes.cultos', 'reportes.ingresos', 'ingresos-asistencia.promesas', 'ingresos-asistencia.asistencia', 'ingresos-asistencia.asistencia-clases'] as $ruta) {
            $r->assertSee(route($ruta), false);
        }
        $r->assertSee('Reporte General');
    }

    public function test_el_asistente_no_ve_los_reportes_financieros(): void
    {
        $u = $this->crearUsuario($this->iglesia, 'asistente');
        $r = $this->actingAs($u)->get(route('ingresos-asistencia.index'));

        // El asistente no puede abrir el hub (no es de dinero): si lo abriera, no vería estos enlaces
        if ($r->status() === 200) {
            foreach (['reportes.cumplimiento', 'reportes.estados-cuenta', 'reportes.cultos', 'reportes.ingresos'] as $ruta) {
                $r->assertDontSee(route($ruta), false);
            }
            $r->assertDontSee(route('personas.reporte-general', ['accion' => 'ver']), false);
        } else {
            $r->assertForbidden();
        }
    }
}

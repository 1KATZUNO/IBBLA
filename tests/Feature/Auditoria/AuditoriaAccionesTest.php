<?php

namespace Tests\Feature\Auditoria;

use App\Models\AuditLog;
use App\Models\PermisoEdicion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PermisosEdicionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * QE-013: el cierre del culto deja registro semántico; una acción con registro propio no se duplica
 * con el genérico del middleware; la pantalla abre en "solo acciones"; un acceso sobre cultos que se
 * borraron deja de verse vigente.
 */
class AuditoriaAccionesTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    private User $tesorero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-04 12:00:00');
        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin', ['name' => 'Pastor Admin']);
        $this->tesorero = $this->crearUsuario($this->iglesia, 'tesorero', ['name' => 'Ana Tesorera']);
    }

    public function test_cerrar_el_culto_deja_registro_con_quien_que_culto_y_totales(): void
    {
        $culto = $this->crearCulto($this->iglesia, '2026-11-01');
        $this->crearSobreEn($culto, null, ['diezmo' => 1500]);
        $this->crearSobreEn($culto, $this->crearPersonaDe($this->iglesia), ['diezmo' => 2500]);

        $this->actingAs($this->tesorero)->post(route('recuento.cerrar-culto', $culto))
            ->assertRedirect(route('recuento.culto', $culto));

        $logs = AuditLog::where('route', 'recuento.cerrar-culto')->get();
        $this->assertCount(1, $logs, 'Un solo registro del cierre');
        $log = $logs->first();
        $this->assertSame('Cerrar culto', $log->action);
        $this->assertSame($this->tesorero->id, (int) $log->user_id);
        $detalles = json_decode($log->details, true);
        $this->assertSame($culto->id, $detalles['culto_id']);
        $this->assertSame('2026-11-01', $detalles['fecha_culto']);
        $this->assertSame(2, $detalles['cantidad_sobres']);
        $this->assertEquals(4000, $detalles['total_general']);
    }

    public function test_una_accion_con_registro_semantico_no_deja_tambien_el_generico(): void
    {
        $culto = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true);

        $this->actingAs($this->admin)->post(route('admin.accesos.store'), [
            'user_id' => $this->tesorero->id, 'tipo' => 'cultos', 'cultos' => [$culto->id], 'motivo' => 'Corregir',
        ])->assertRedirect(route('admin.accesos.index'));

        $this->assertSame(1, AuditLog::where('action', PermisosEdicionService::ACCION_CONCEDER)->count());
        $this->assertSame(0, AuditLog::where('route', 'admin.accesos.store')->where('action', 'admin.accesos.store')->count(), 'Sin la fila genérica duplicada');
        $this->assertSame(1, AuditLog::where('method', 'POST')->count());
    }

    public function test_una_accion_sin_registro_semantico_sigue_con_el_generico(): void
    {
        $this->actingAs($this->admin)->get(route('admin.accesos.index'))->assertOk();

        $this->assertSame(1, AuditLog::where('action', 'admin.accesos.index')->where('method', 'GET')->count());
    }

    public function test_la_pantalla_abre_en_solo_acciones_sin_visitas_ni_columnas_tecnicas(): void
    {
        $this->log('GET', 'cultos.index', 'cultos.index', 'Mozilla/5.0 VisitaNavegador');
        $this->log('POST', 'personas.update', 'personas.update', 'Mozilla/5.0 AccionNavegador');
        $this->log('POST', 'login', AuditLog::ACCION_LOGIN, 'Mozilla/5.0 Ingreso');

        $this->actingAs($this->admin)->get(route('admin.auditoria.index'))
            ->assertOk()
            ->assertSee('Solo acciones')
            ->assertSee('personas.update')
            ->assertDontSee('cultos.index')
            ->assertDontSee('Inicio de sesión')
            ->assertDontSee('AccionNavegador')
            ->assertDontSee('<th>Agente</th>', false);
    }

    public function test_ver_todo_muestra_visitas_y_datos_tecnicos(): void
    {
        $this->log('GET', 'cultos.index', 'cultos.index', 'Mozilla/5.0 VisitaNavegador');

        $this->actingAs($this->admin)->get(route('admin.auditoria.index', ['ver' => 'todo']))
            ->assertOk()
            ->assertSee('cultos.index')
            ->assertSee('VisitaNavegador')
            ->assertSee('<th>Agente</th>', false);
    }

    public function test_solo_el_admin_ve_la_auditoria(): void
    {
        $this->actingAs($this->tesorero)->get(route('admin.auditoria.index'))->assertForbidden();
    }

    public function test_un_acceso_sobre_un_culto_borrado_deja_de_verse_vigente(): void
    {
        $culto = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true);
        $otro = $this->crearCulto($this->iglesia, '2026-10-18', 'domingo', true);
        PermisoEdicion::create([
            'tenant_id' => $this->iglesia->id, 'user_id' => $this->tesorero->id, 'tipo' => PermisoEdicion::TIPO_CULTOS,
            'referencia' => [$culto->id], 'otorgado_por' => $this->admin->id, 'motivo' => 'Borrado',
        ]);
        PermisoEdicion::create([
            'tenant_id' => $this->iglesia->id, 'user_id' => $this->tesorero->id, 'tipo' => PermisoEdicion::TIPO_CULTOS,
            'referencia' => [$otro->id], 'otorgado_por' => $this->admin->id, 'motivo' => 'Sigue',
        ]);
        $culto->delete();

        $respuesta = $this->actingAs($this->admin)->get(route('admin.accesos.index'))->assertOk();

        $this->assertSame(1, $respuesta->viewData('vigentes')->count());
        $this->assertSame('Culto del 18/10/2026 (Domingo AM)', $respuesta->viewData('vigentes')->first()->descripcionAlcance($respuesta->viewData('cultosPorId')));
        $respuesta->assertSee('Sus cultos se eliminaron');
    }

    private function log(string $metodo, string $ruta, string $accion, string $agente): void
    {
        AuditLog::$escribiendoGenerico = true;
        AuditLog::create([
            'tenant_id' => $this->iglesia->id, 'user_id' => $this->admin->id, 'user_name' => 'Pastor Admin',
            'user_email' => $this->admin->email, 'ip_address' => '10.0.0.1', 'user_agent' => $agente,
            'method' => $metodo, 'route' => $ruta, 'action' => $accion, 'details' => '{}',
        ]);
        AuditLog::$escribiendoGenerico = false;
    }
}

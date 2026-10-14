<?php

namespace Tests\Feature\Recuento;

use App\Models\AuditLog;
use App\Models\Culto;
use App\Models\PermisoEdicion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PermisosEdicionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * HU-REC-3: modulo Accesos. El admin le da a un usuario de tesoreria permiso de edicion sobre un
 * culto, varios, un rango de fechas o todos, con vencimiento opcional; lo puede revocar; todo queda
 * auditado (la concesion, la revocacion y cada cambio hecho con el acceso). Quien tiene un acceso
 * vigente puede crear cultos.
 */
class AccesosEdicionTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    private User $tesorero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin', ['name' => 'Pastor Admin']);
        $this->tesorero = $this->crearUsuario($this->iglesia, 'tesorero', ['name' => 'Ana Tesorera']);
    }

    private function conceder(array $datos): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('admin.accesos.store'), $datos + ['user_id' => $this->tesorero->id]);
    }

    private function puedeEditar(User $user, Culto $culto): bool
    {
        // Peticion nueva en cada consulta: la regla no debe quedarse con un resultado viejo
        $this->app->instance('request', \Illuminate\Http\Request::create('/'));

        return $user->fresh()->can('puedeEditarCulto', $culto->fresh());
    }

    public function test_el_admin_concede_acceso_a_cultos_concretos_y_queda_auditado(): void
    {
        $culto11 = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true);
        $culto04 = $this->crearCulto($this->iglesia, '2026-10-04', 'domingo', true);
        $culto07 = $this->crearCulto($this->iglesia, '2026-10-07', 'miercoles', true);

        $this->conceder(['tipo' => 'cultos', 'cultos' => [$culto11->id, $culto04->id], 'motivo' => 'Corregir sobres'])
            ->assertRedirect(route('admin.accesos.index'))
            ->assertSessionHas('success');

        $permiso = PermisoEdicion::firstOrFail();
        $this->assertSame([$culto11->id, $culto04->id], $permiso->idsCultos());
        $this->assertSame($this->admin->id, (int) $permiso->otorgado_por);
        $this->assertNull($permiso->vence_el);

        $this->assertTrue($this->puedeEditar($this->tesorero, $culto11));
        $this->assertTrue($this->puedeEditar($this->tesorero, $culto04));
        $this->assertFalse($this->puedeEditar($this->tesorero, $culto07));

        $log = AuditLog::where('action', PermisosEdicionService::ACCION_CONCEDER)->firstOrFail();
        $this->assertSame($this->iglesia->id, (int) $log->tenant_id);
        $this->assertSame($this->admin->id, (int) $log->user_id);
        $this->assertStringContainsString('Ana Tesorera', $log->details);
    }

    public function test_acceso_por_rango_de_fechas_y_a_todos(): void
    {
        $septiembre = $this->crearCulto($this->iglesia, '2026-09-20', 'domingo', true);
        $finDeMes = $this->crearCulto($this->iglesia, '2026-09-30', 'miercoles', true);
        $octubre = $this->crearCulto($this->iglesia, '2026-10-04', 'domingo', true);

        $this->conceder(['tipo' => 'rango', 'desde' => '2026-09-01', 'hasta' => '2026-09-30'])->assertSessionHasNoErrors();
        $this->assertTrue($this->puedeEditar($this->tesorero, $septiembre));
        $this->assertTrue($this->puedeEditar($this->tesorero, $finDeMes));
        $this->assertFalse($this->puedeEditar($this->tesorero, $octubre));

        $this->conceder(['tipo' => 'todos'])->assertSessionHasNoErrors();
        $this->assertTrue($this->puedeEditar($this->tesorero, $octubre));
    }

    public function test_un_acceso_vencido_ya_no_sirve(): void
    {
        $culto = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true);
        Carbon::setTestNow('2026-10-13 10:00:00');

        $this->conceder(['tipo' => 'todos', 'vence_el' => '2026-10-14'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-14 23:59:59', PermisoEdicion::first()->vence_el->format('Y-m-d H:i:s'));
        $this->assertTrue($this->puedeEditar($this->tesorero, $culto));

        Carbon::setTestNow('2026-10-15 08:00:00');
        $this->assertFalse($this->puedeEditar($this->tesorero, $culto));

        // Y aparece en el historial como vencido
        $this->actingAs($this->admin)->get(route('admin.accesos.index'))
            ->assertOk()
            ->assertSee('Venció el 14/10/2026');
        Carbon::setTestNow();
    }

    public function test_revocar_quita_el_acceso_y_queda_en_el_historial(): void
    {
        $culto = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true);
        $this->conceder(['tipo' => 'cultos', 'cultos' => [$culto->id]]);
        $permiso = PermisoEdicion::firstOrFail();

        $this->actingAs($this->admin)->delete(route('admin.accesos.destroy', $permiso))
            ->assertRedirect(route('admin.accesos.index'));

        $this->assertNotNull($permiso->fresh()->revocado_at);
        $this->assertSame($this->admin->id, (int) $permiso->fresh()->revocado_por);
        $this->assertFalse($this->puedeEditar($this->tesorero, $culto));
        $this->assertTrue(AuditLog::where('action', PermisosEdicionService::ACCION_REVOCAR)->exists());

        $this->actingAs($this->tesorero)->post(route('recuento.store-suelto'), ['culto_id' => $culto->id, 'monto' => 100])
            ->assertForbidden();
    }

    public function test_con_acceso_el_tesorero_corrige_el_culto_cerrado_y_cada_cambio_se_audita(): void
    {
        $culto = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true, $this->admin);
        $sobre = $this->crearSobreEn($culto, null, ['diezmo' => 10000]);
        $this->conceder(['tipo' => 'cultos', 'cultos' => [$culto->id], 'vence_el' => now()->addDays(3)->toDateString()]);
        $permiso = PermisoEdicion::firstOrFail();

        $this->actingAs($this->tesorero)->get(route('recuento.culto', $culto))
            ->assertOk()
            ->assertSee('tienes acceso de edición')
            ->assertSee('Pastor Admin te dio permiso para corregirlo')
            ->assertDontSee('class="accion-bloqueada"', false);

        $this->actingAs($this->tesorero)->put(route('recuento.update', $sobre), [
            'metodo_pago' => 'efectivo',
            'detalles' => [['categoria' => 'diezmo', 'monto' => 12500]],
        ])->assertRedirect(route('recuento.culto', $culto));

        $this->assertEquals(12500, (float) $sobre->fresh()->total_declarado);
        $edicion = AuditLog::where('action', PermisosEdicionService::ACCION_EDICION)->firstOrFail();
        $this->assertSame($this->tesorero->id, (int) $edicion->user_id);
        $detalles = json_decode($edicion->details, true);
        $this->assertSame($permiso->id, $detalles['permiso_id']);
        $this->assertSame($culto->id, $detalles['culto_id']);
        $this->assertSame('Editar sobre', $detalles['accion']);

        // El modulo cuenta el cambio y lo lista
        $this->actingAs($this->admin)->get(route('admin.accesos.index'))
            ->assertOk()
            ->assertSee('Ana Tesorera')
            ->assertSee('Culto del 11/10/2026')
            ->assertSee('Con acceso de edición');
    }

    public function test_las_correcciones_del_admin_en_un_cerrado_tambien_se_auditan(): void
    {
        $culto = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true);

        $this->actingAs($this->admin)->post(route('recuento.store-egreso'), ['culto_id' => $culto->id, 'monto' => 2000, 'descripcion' => 'Flores'])
            ->assertRedirect(route('recuento.culto', $culto));

        $log = AuditLog::where('action', PermisosEdicionService::ACCION_CORRECCION)->firstOrFail();
        $this->assertNull(json_decode($log->details, true)['permiso_id']);

        // En un culto abierto no se registra nada de esto
        $abierto = $this->crearCulto($this->iglesia, '2026-10-18');
        $this->actingAs($this->tesorero)->post(route('recuento.store-egreso'), ['culto_id' => $abierto->id, 'monto' => 100]);
        $this->assertSame(1, AuditLog::whereIn('action', [PermisosEdicionService::ACCION_CORRECCION, PermisosEdicionService::ACCION_EDICION])->count());
    }

    public function test_con_acceso_vigente_el_tesorero_puede_crear_cultos(): void
    {
        $this->actingAs($this->tesorero)->get(route('cultos.create'))->assertForbidden();
        $this->actingAs($this->tesorero)->get(route('recuento.index'))
            ->assertOk()
            ->assertSee('Crear cultos le toca al administrador', false);

        $this->conceder(['tipo' => 'todos']);

        $this->actingAs($this->tesorero)->get(route('cultos.create', ['desde' => 'recuento']))->assertOk();
        $respuesta = $this->actingAs($this->tesorero)->post(route('cultos.store'), [
            'fecha' => '2026-10-18',
            'hora' => '10:00',
            'tipo_culto' => 'domingo',
            'desde' => 'recuento',
        ]);

        $culto = Culto::withoutGlobalScopes()->where('fecha', '2026-10-18')->firstOrFail();
        $respuesta->assertRedirect(route('recuento.culto', $culto));
        $this->assertSame($this->iglesia->id, (int) $culto->tenant_id);
    }

    public function test_solo_el_admin_entra_al_modulo_y_solo_tesoreria_recibe_accesos(): void
    {
        $this->actingAs($this->tesorero)->get(route('admin.accesos.index'))->assertForbidden();

        $asistente = $this->crearUsuario($this->iglesia, 'asistente', ['name' => 'Asistente Clase']);
        $otroAdmin = $this->crearUsuario($this->iglesia, 'admin', ['name' => 'Otro Admin']);

        $this->actingAs($this->admin)->get(route('admin.accesos.index'))
            ->assertOk()
            ->assertSee('Ana Tesorera · Tesorero', false)
            ->assertDontSee('Asistente Clase')
            ->assertDontSee('Otro Admin');

        $this->actingAs($this->admin)->post(route('admin.accesos.store'), ['user_id' => $asistente->id, 'tipo' => 'todos'])
            ->assertSessionHasErrors('user_id');
        $this->assertSame(0, PermisoEdicion::count());
    }

    public function test_validaciones_del_formulario(): void
    {
        $this->conceder(['tipo' => 'cultos', 'cultos' => []])->assertSessionHasErrors('cultos');
        $this->conceder(['tipo' => 'rango', 'desde' => '2026-10-10', 'hasta' => '2026-10-01'])->assertSessionHasErrors('hasta');
        $this->conceder(['tipo' => 'todos', 'vence_el' => now()->subDay()->toDateString()])->assertSessionHasErrors('vence_el');
        $this->conceder(['tipo' => 'cualquiera'])->assertSessionHasErrors('tipo');
        $this->assertSame(0, PermisoEdicion::count());
    }

    public function test_nada_cruza_entre_iglesias(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        $ajeno = $this->crearUsuario($otra, 'tesorero');
        $cultoAjeno = $this->crearCulto($otra, '2026-10-11', 'domingo', true);
        $cultoPropio = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true);

        // No se le puede dar acceso a un usuario de otra iglesia ni sobre un culto de otra iglesia
        $this->actingAs($this->admin)->post(route('admin.accesos.store'), ['user_id' => $ajeno->id, 'tipo' => 'todos'])
            ->assertSessionHasErrors('user_id');
        $this->conceder(['tipo' => 'cultos', 'cultos' => [$cultoAjeno->id]])->assertSessionHasErrors('cultos.0');

        // Un acceso a "todos" no alcanza cultos de otra iglesia
        $this->conceder(['tipo' => 'todos']);
        $this->assertTrue($this->puedeEditar($this->tesorero, $cultoPropio));
        $this->assertFalse($this->puedeEditar($this->tesorero, $cultoAjeno));

        // Ni se revoca el acceso de otra iglesia
        $permisoAjeno = PermisoEdicion::withoutGlobalScopes()->create(['tenant_id' => $otra->id, 'user_id' => $ajeno->id, 'tipo' => 'todos']);
        $this->actingAs($this->admin)->delete(route('admin.accesos.destroy', $permisoAjeno))->assertNotFound();
        $this->assertNull($permisoAjeno->fresh()->revocado_at);
    }
}

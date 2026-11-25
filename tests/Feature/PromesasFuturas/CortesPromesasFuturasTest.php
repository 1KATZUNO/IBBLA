<?php

namespace Tests\Feature\PromesasFuturas;

use App\Models\AuditLog;
use App\Models\CortePromesas;
use App\Models\Persona;
use App\Models\Promesa;
use App\Models\PromesaProgramada;
use App\Models\Tenant;
use App\Models\TenantCategory;
use App\Models\User;
use App\Support\Navegacion\Menu;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Apartado «Promesas futuras» (06-PROMESAS-FUTURAS.md, 4.1 y 4.3): permisos por rol, validaciones del
 * corte, crear / editar / eliminar, alta / edicion / baja manual de personas, menu y buscador, auditoria.
 */
class CortesPromesasFuturasTest extends TestCase
{
    use ConIglesia;
    use RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-24 10:00:00');
        $this->travelTo('2026-11-24 10:00:00');
        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function corte(array $extra = []): CortePromesas
    {
        return CortePromesas::create($extra + ['tenant_id' => $this->iglesia->id, 'nombre' => 'Promesas 2027', 'inicia_el' => '2027-01-01', 'categorias_nuevas' => []]);
    }

    private function persona(string $nombre): Persona
    {
        return Persona::create(['nombre' => $nombre, 'activo' => true, 'tenant_id' => $this->iglesia->id]);
    }

    public function test_solo_el_administrador_entra(): void
    {
        $corte = $this->corte();
        foreach (['tesorero', 'asistente', 'miembro'] as $rol) {
            $u = $this->crearUsuario($this->iglesia, $rol);
            $this->actingAs($u)->get(route('promesas-futuras.index'))->assertForbidden();
            $this->actingAs($u)->get(route('promesas-futuras.show', $corte))->assertForbidden();
            $this->actingAs($u)->get(route('promesas-futuras.plantilla'))->assertForbidden();
            $this->actingAs($u)->post(route('promesas-futuras.store'), ['nombre' => 'X', 'mes' => '2027-02'])->assertForbidden();
        }
        $this->actingAs($this->admin)->get(route('promesas-futuras.index'))->assertOk()->assertSee('Promesas 2027')->assertSee('Programado')->assertSee('faltan 38 días');
    }

    public function test_lista_vacia_dice_que_hacer(): void
    {
        $this->actingAs($this->admin)->get(route('promesas-futuras.index'))->assertOk()
            ->assertSee('Todavía no hay cortes de promesas')->assertSee('+ Nuevo corte');
    }

    public function test_crear_corte_valida_mes_futuro_y_unico(): void
    {
        $this->actingAs($this->admin)->post(route('promesas-futuras.store'), ['nombre' => 'Viejo', 'mes' => '2026-11'])->assertSessionHasErrors('mes');
        $this->actingAs($this->admin)->post(route('promesas-futuras.store'), ['nombre' => '', 'mes' => '2027-01'])->assertSessionHasErrors('nombre');
        $this->actingAs($this->admin)->post(route('promesas-futuras.store'), ['nombre' => 'Promesas 2027', 'mes' => '2027-01'])->assertRedirect();

        $corte = CortePromesas::firstOrFail();
        $this->assertSame('2027-01-01', $corte->inicia_el->toDateString());
        $this->assertNull($corte->activado_at);
        $this->assertSame($this->admin->id, $corte->creado_por);
        $this->assertTrue(AuditLog::where('action', 'Corte de promesas creado')->exists());

        $this->actingAs($this->admin)->post(route('promesas-futuras.store'), ['nombre' => 'Otro', 'mes' => '2027-01'])->assertSessionHasErrors('mes');
        $this->assertSame(1, CortePromesas::count());
    }

    public function test_editar_corte_y_no_se_edita_ya_activado(): void
    {
        $corte = $this->corte();
        $this->actingAs($this->admin)->put(route('promesas-futuras.update', $corte), ['nombre' => 'Promesas 2027 (Aurora)', 'mes' => '2027-02'])->assertRedirect();
        $corte->refresh();
        $this->assertSame('Promesas 2027 (Aurora)', $corte->nombre);
        $this->assertSame('2027-02-01', $corte->inicia_el->toDateString());
        $this->assertTrue(AuditLog::where('action', 'Corte de promesas editado')->exists());

        $activo = $this->corte(['nombre' => 'Viejo', 'inicia_el' => '2026-01-01', 'activado_at' => now()]);
        $this->actingAs($this->admin)->put(route('promesas-futuras.update', $activo), ['nombre' => 'Cambiado', 'mes' => '2027-05'])
            ->assertRedirect(route('promesas-futuras.show', $activo));
        $this->assertSame('Viejo', $activo->fresh()->nombre);
        $this->actingAs($this->admin)->get(route('promesas-futuras.show', $activo))->assertOk()->assertSee('Solo lectura')->assertDontSee('+ Agregar persona');
    }

    public function test_eliminar_pide_confirmar_y_borra_programadas_y_categorias_nuevas(): void
    {
        $corte = $this->corte();
        $ana = $this->persona('Ana Mora');
        $nueva = TenantCategory::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Alcance Local', 'slug' => 'alcance_local', 'tipo' => 'ambos', 'activa' => false, 'orden' => 99]);
        $corte->update(['categorias_nuevas' => [$nueva->id]]);
        PromesaProgramada::create(['corte_id' => $corte->id, 'persona_id' => $ana->id, 'categoria' => 'alcance_local', 'monto' => 1000, 'moneda' => 'CRC', 'frecuencia' => 'semanal']);
        Promesa::create(['persona_id' => $ana->id, 'categoria' => 'misiones', 'monto' => 500, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);

        $this->actingAs($this->admin)->get(route('promesas-futuras.eliminar', $corte))->assertOk()->assertSee('Alcance Local');
        $this->actingAs($this->admin)->delete(route('promesas-futuras.destroy', $corte), ['confirmacion' => 'si'])->assertSessionHasErrors('confirmacion');
        $this->assertNotNull($corte->fresh());

        $this->actingAs($this->admin)->delete(route('promesas-futuras.destroy', $corte), ['confirmacion' => 'CONFIRMAR'])->assertRedirect(route('promesas-futuras.index'));
        $this->assertNull($corte->fresh());
        $this->assertSame(0, PromesaProgramada::count());
        $this->assertNull(TenantCategory::find($nueva->id));
        $this->assertSame(1, Promesa::count(), 'las promesas de hoy no se tocan');
        $this->assertTrue(AuditLog::where('action', 'Corte de promesas eliminado')->exists());
    }

    public function test_agregar_editar_y_quitar_persona_a_mano(): void
    {
        $corte = $this->corte();
        $ana = $this->persona('Ana Mora');
        Promesa::create(['persona_id' => $ana->id, 'categoria' => 'misiones', 'monto' => 500, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);

        $this->actingAs($this->admin)->get(route('promesas-futuras.personas.agregar', [$corte, 'q' => 'ana']))->assertOk()->assertSee('Ana Mora')->assertSee('Elegir');
        $this->actingAs($this->admin)->get(route('promesas-futuras.personas.edit', [$corte, $ana]))->assertOk()->assertSee('Misiones');

        // Sin montos no se agrega
        $this->actingAs($this->admin)->put(route('promesas-futuras.personas.update', [$corte, $ana]), ['promesas' => [
            ['categoria' => 'misiones', 'monto' => '', 'frecuencia' => 'semanal', 'moneda' => 'CRC'],
        ]])->assertSessionHas('error');
        $this->assertSame(0, PromesaProgramada::count());

        $this->actingAs($this->admin)->put(route('promesas-futuras.personas.update', [$corte, $ana]), ['promesas' => [
            ['categoria' => 'misiones', 'monto' => '230.769230769231', 'frecuencia' => 'semanal', 'moneda' => 'CRC'],
            ['categoria' => 'construccion', 'monto' => '0', 'frecuencia' => 'semanal', 'moneda' => 'CRC'],
            ['categoria' => 'seminario', 'monto' => '5', 'frecuencia' => 'mensual', 'moneda' => 'USD'],
        ]])->assertRedirect(route('promesas-futuras.show', $corte));
        $this->assertSame(2, PromesaProgramada::count());
        $this->assertSame('230.77', PromesaProgramada::where('categoria', 'misiones')->value('monto'));
        $this->assertTrue(AuditLog::where('action', 'Persona agregada a un corte de promesas')->exists());

        // Categoria que no es de promesa (diezmo) se rechaza
        $this->actingAs($this->admin)->put(route('promesas-futuras.personas.update', [$corte, $ana]), ['promesas' => [
            ['categoria' => 'diezmo', 'monto' => '100', 'frecuencia' => 'semanal', 'moneda' => 'CRC'],
        ]])->assertSessionHasErrors();

        // Editar reemplaza lo suyo
        $this->actingAs($this->admin)->put(route('promesas-futuras.personas.update', [$corte, $ana]), ['promesas' => [
            ['categoria' => 'misiones', 'monto' => '1000', 'frecuencia' => 'semanal', 'moneda' => 'CRC'],
        ]])->assertRedirect();
        $this->assertSame(1, PromesaProgramada::count());
        $this->assertTrue(AuditLog::where('action', 'Promesas programadas editadas')->exists());

        $this->actingAs($this->admin)->get(route('promesas-futuras.show', $corte))->assertOk()->assertSee('Ana Mora')->assertSee('₡1.000');

        $this->actingAs($this->admin)->delete(route('promesas-futuras.personas.destroy', [$corte, $ana]))->assertRedirect();
        $this->assertSame(0, PromesaProgramada::count());
        $this->assertTrue(AuditLog::where('action', 'Persona quitada de un corte de promesas')->exists());
        // Lo que rige hoy, intacto
        $this->assertSame(1, Promesa::count());
        $this->assertSame('500.00', Promesa::value('monto'));
    }

    public function test_alta_de_persona_nueva_y_aviso_si_ya_existe(): void
    {
        $corte = $this->corte();
        $this->persona('José Pérez');

        $this->actingAs($this->admin)->post(route('promesas-futuras.personas.crear', $corte), ['nombre' => 'jose perez'])->assertSessionHas('warning');
        $this->assertSame(1, Persona::count());

        $r = $this->actingAs($this->admin)->post(route('promesas-futuras.personas.crear', $corte), ['nombre' => '  Marta  Solís ', 'telefono' => '8888-1111']);
        $marta = Persona::where('nombre', 'Marta Solís')->firstOrFail();
        $r->assertRedirect(route('promesas-futuras.personas.edit', [$corte, $marta]));
        $this->assertTrue(AuditLog::where('action', 'Alta rápida de persona')->exists());
    }

    public function test_detalle_avisa_si_no_tiene_promesas(): void
    {
        $corte = $this->corte();
        $this->actingAs($this->admin)->get(route('promesas-futuras.show', $corte))->assertOk()
            ->assertSee('no entrará en vigor')->assertSee('Importar Excel')->assertSee('Descargar plantilla');
    }

    public function test_corte_de_otra_iglesia_no_se_ve(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        $ajeno = CortePromesas::create(['tenant_id' => $otra->id, 'nombre' => 'Ajeno', 'inicia_el' => '2027-01-01']);
        $this->actingAs($this->admin)->get(route('promesas-futuras.show', $ajeno))->assertNotFound();
        $this->actingAs($this->admin)->get(route('promesas-futuras.index'))->assertDontSee('Ajeno');
    }

    public function test_menu_y_buscador_solo_para_admin(): void
    {
        $rutas = fn (User $u) => collect(Menu::grupos($u))->flatMap(fn ($g) => collect($g['items'])->pluck('ruta'));
        $this->assertContains('promesas-futuras.index', $rutas($this->admin));
        $this->assertContains('promesas-futuras.index', collect(Menu::pantallas($this->admin))->pluck('ruta'));
        foreach (['tesorero', 'asistente', 'miembro'] as $rol) {
            $u = $this->crearUsuario($this->iglesia, $rol);
            $this->assertNotContains('promesas-futuras.index', $rutas($u), $rol);
            $this->assertNotContains('promesas-futuras.index', collect(Menu::pantallas($u))->pluck('ruta'), $rol);
        }
    }
}

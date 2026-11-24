<?php

namespace Tests\Feature\Ministerios;

use App\Models\Ministerio;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * CRUD de ministerios en /admin/ministerios (solo admin).
 */
class MinisterioCrudTest extends TestCase
{
    use ConIglesia, RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');
    }

    private function ministerio(string $nombre, array $extra = [], ?Tenant $iglesia = null): Ministerio
    {
        return Ministerio::create($extra + [
            'tenant_id' => ($iglesia ?? $this->iglesia)->id,
            'nombre' => $nombre,
        ]);
    }

    private function persona(string $nombre = 'Ana Pérez', ?Tenant $iglesia = null): Persona
    {
        return Persona::create([
            'nombre' => $nombre,
            'tenant_id' => ($iglesia ?? $this->iglesia)->id,
            'activo' => true,
        ]);
    }

    // ------------------------------------------------------------------ listado

    public function test_el_listado_muestra_los_ministerios_ordenados_y_cuenta_las_personas(): void
    {
        $alabanza = $this->ministerio('Alabanza', ['orden' => 2]);
        $this->ministerio('Ujieres', ['orden' => 1]);
        $this->persona('Ana')->ministerios()->attach($alabanza->id, ['desde' => '2026-01-01']);
        $this->persona('Beto')->ministerios()->attach($alabanza->id, ['desde' => '2025-01-01', 'hasta' => '2025-12-31']);

        $respuesta = $this->actingAs($this->admin)->get(route('admin.ministerios.index'));

        $respuesta->assertOk()
            ->assertSeeInOrder(['Ujieres', 'Alabanza'])
            ->assertSee('(+1 anteriores)');

        $fila = $respuesta->viewData('ministerios')->firstWhere('nombre', 'Alabanza');
        $this->assertSame(1, $fila->personas_activas_count);
        $this->assertSame(2, $fila->personas_count);
    }

    public function test_el_listado_solo_trae_los_ministerios_de_la_propia_iglesia(): void
    {
        $this->ministerio('Alabanza');
        $otra = $this->crearIglesia('otra', 'OTRA');
        $this->ministerio('Ministerio Ajeno', [], $otra);

        $this->actingAs($this->admin)->get(route('admin.ministerios.index'))
            ->assertOk()
            ->assertSee('Alabanza')
            ->assertDontSee('Ministerio Ajeno');
    }

    // ------------------------------------------------------------------ crear

    public function test_crear_a_mano_genera_slug_orden_y_toma_la_iglesia_del_usuario(): void
    {
        $this->ministerio('Alabanza', ['orden' => 5]);

        $this->actingAs($this->admin)->post(route('admin.ministerios.store'), [
            'nombre' => 'Niños / Escuela Dominical',
            'color' => '#EC4899',
            'descripcion' => 'Clases por edad',
            'activo' => '1',
            'slug' => '',
            'orden' => '',
        ])->assertRedirect(route('admin.ministerios.index'))
            ->assertSessionHas('success');

        $nuevo = Ministerio::where('nombre', 'Niños / Escuela Dominical')->firstOrFail();
        $this->assertSame('ninos-escuela-dominical', $nuevo->slug);
        $this->assertSame(6, $nuevo->orden, 'Sin orden explícito va al final de la lista.');
        $this->assertSame($this->iglesia->id, $nuevo->tenant_id);
        $this->assertTrue($nuevo->activo);
    }

    public function test_dos_nombres_que_dan_el_mismo_slug_no_chocan(): void
    {
        $this->ministerio('Medios y Comunicación');
        $this->actingAs($this->admin)->post(route('admin.ministerios.store'), [
            'nombre' => 'Medios y comunicación!',   // distinto nombre, mismo slug base
            'color' => '#112233',
            'activo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['medios-y-comunicacion', 'medios-y-comunicacion-2'],
            Ministerio::pluck('slug')->all()
        );
    }

    public function test_crear_con_slug_propio_lo_respeta(): void
    {
        $this->actingAs($this->admin)->post(route('admin.ministerios.store'), [
            'nombre' => 'Intercesión',
            'slug' => 'oracion',
            'color' => '#112233',
            'activo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('oracion', Ministerio::where('nombre', 'Intercesión')->value('slug'));
    }

    public function test_el_nombre_no_se_puede_repetir_en_la_misma_iglesia_ni_con_otras_mayusculas(): void
    {
        $this->ministerio('Alabanza');

        $this->actingAs($this->admin)->post(route('admin.ministerios.store'), [
            'nombre' => 'ALABANZA',
            'color' => '#112233',
        ])->assertSessionHasErrors('nombre');

        $this->assertSame(1, Ministerio::count());
    }

    public function test_el_mismo_nombre_si_se_permite_en_otra_iglesia(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        $this->ministerio('Alabanza', [], $otra);

        $this->actingAs($this->admin)->post(route('admin.ministerios.store'), [
            'nombre' => 'Alabanza',
            'color' => '#112233',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Ministerio::where('nombre', 'Alabanza')->count(), 'Con el scope de iglesia solo ve el propio.');
        $this->assertSame(2, Ministerio::withoutGlobalScopes()->where('nombre', 'Alabanza')->count());
    }

    public function test_slug_repetido_o_mal_formado_y_color_invalido_se_rechazan(): void
    {
        $this->ministerio('Alabanza');   // slug "alabanza"

        $this->actingAs($this->admin)->post(route('admin.ministerios.store'), [
            'nombre' => 'Otro',
            'slug' => 'alabanza',
            'color' => '#112233',
        ])->assertSessionHasErrors('slug');

        $this->actingAs($this->admin)->post(route('admin.ministerios.store'), [
            'nombre' => 'Otro',
            'slug' => 'Con Espacios',
            'color' => '#112233',
        ])->assertSessionHasErrors('slug');

        $this->actingAs($this->admin)->post(route('admin.ministerios.store'), [
            'nombre' => 'Otro',
            'color' => 'rojo',
        ])->assertSessionHasErrors('color');

        $this->assertSame(1, Ministerio::count());
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        $this->actingAs($this->admin)->post(route('admin.ministerios.store'), ['color' => '#112233'])
            ->assertSessionHasErrors('nombre');
    }

    public function test_el_super_admin_sin_iglesia_no_puede_crear(): void
    {
        $super = $this->crearSuperAdmin();

        $this->actingAs($super)->post(route('admin.ministerios.store'), [
            'nombre' => 'Huérfano',
            'color' => '#112233',
        ])->assertSessionHasErrors('nombre');

        $this->assertSame(0, Ministerio::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------------ editar

    public function test_editar_actualiza_los_datos_y_conserva_slug_y_orden_si_vienen_vacios(): void
    {
        $m = $this->ministerio('Alabanza', ['orden' => 3, 'color' => '#111111']);

        $this->actingAs($this->admin)->put(route('admin.ministerios.update', $m), [
            'nombre' => 'Alabanza y Adoración',
            'color' => '#222222',
            'descripcion' => 'Equipo de música',
            'activo' => '1',
            'slug' => '',
            'orden' => '',
        ])->assertRedirect(route('admin.ministerios.index'));

        $m->refresh();
        $this->assertSame('Alabanza y Adoración', $m->nombre);
        $this->assertSame('#222222', $m->color);
        $this->assertSame('Equipo de música', $m->descripcion);
        $this->assertSame('alabanza', $m->slug, 'Renombrar no cambia el identificador.');
        $this->assertSame(3, $m->orden, 'Un orden vacío no pisa el actual (la columna no admite NULL).');
    }

    public function test_editar_puede_cambiar_el_orden(): void
    {
        $m = $this->ministerio('Alabanza', ['orden' => 3]);

        $this->actingAs($this->admin)->put(route('admin.ministerios.update', $m), [
            'nombre' => 'Alabanza',
            'color' => '#222222',
            'orden' => '10',
        ])->assertSessionHasNoErrors();

        $this->assertSame(10, $m->fresh()->orden);
    }

    public function test_editar_no_choca_consigo_mismo_pero_si_con_otro(): void
    {
        $m = $this->ministerio('Alabanza');
        $this->ministerio('Ujieres');

        // Mismo nombre y slug que ya tiene: válido.
        $this->actingAs($this->admin)->put(route('admin.ministerios.update', $m), [
            'nombre' => 'Alabanza', 'slug' => 'alabanza', 'color' => '#222222',
        ])->assertSessionHasNoErrors();

        // Nombre de otro: inválido.
        $this->actingAs($this->admin)->put(route('admin.ministerios.update', $m), [
            'nombre' => 'Ujieres', 'color' => '#222222',
        ])->assertSessionHasErrors('nombre');
    }

    public function test_la_pantalla_de_edicion_lista_las_personas_asignadas(): void
    {
        $m = $this->ministerio('Alabanza');
        $this->persona('Ana Líder')->ministerios()->attach($m->id, ['desde' => '2026-02-01', 'es_lider' => true]);
        $this->persona('Beto Anterior')->ministerios()->attach($m->id, ['desde' => '2024-02-01', 'hasta' => '2025-01-31']);

        $this->actingAs($this->admin)->get(route('admin.ministerios.edit', $m))
            ->assertOk()
            ->assertSee('Personas asignadas (2)')
            ->assertSeeInOrder(['Ana Líder', 'Líder', 'Desde 01/02/2026', 'Beto Anterior', 'Hasta 31/01/2025']);
    }

    // ------------------------------------------------------------------ desactivar

    public function test_desactivar_y_volver_a_activar_conserva_las_asignaciones(): void
    {
        $m = $this->ministerio('Alabanza');
        $persona = $this->persona();
        $persona->ministerios()->attach($m->id, ['desde' => '2026-01-01']);

        $this->actingAs($this->admin)->patch(route('admin.ministerios.toggle', $m))
            ->assertRedirect(route('admin.ministerios.index'))
            ->assertSessionHas('success');
        $this->assertFalse($m->fresh()->activo);
        $this->assertSame(1, $persona->ministerios()->count(), 'Desactivar no toca las asignaciones.');

        $this->actingAs($this->admin)->patch(route('admin.ministerios.toggle', $m));
        $this->assertTrue($m->fresh()->activo);
    }

    // ------------------------------------------------------------------ eliminar

    public function test_eliminar_un_ministerio_sin_personas_lo_borra(): void
    {
        $m = $this->ministerio('Alabanza');

        $this->actingAs($this->admin)->delete(route('admin.ministerios.destroy', $m))
            ->assertRedirect(route('admin.ministerios.index'))
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        $this->assertModelMissing($m);
    }

    public function test_eliminar_un_ministerio_con_personas_asignadas_esta_protegido(): void
    {
        $m = $this->ministerio('Alabanza');
        $persona = $this->persona();
        $persona->ministerios()->attach($m->id, ['desde' => '2026-01-01', 'es_lider' => true]);

        $this->actingAs($this->admin)->delete(route('admin.ministerios.destroy', $m))
            ->assertRedirect(route('admin.ministerios.index'))
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertModelExists($m);
        $this->assertSame(1, $persona->ministerios()->count(), 'La asignación sigue intacta.');
    }

    public function test_la_proteccion_tambien_cubre_asignaciones_que_ya_terminaron(): void
    {
        $m = $this->ministerio('Alabanza');
        $this->persona()->ministerios()->attach($m->id, ['desde' => '2024-01-01', 'hasta' => '2025-01-01']);

        $this->actingAs($this->admin)->delete(route('admin.ministerios.destroy', $m))
            ->assertSessionHas('error');

        $this->assertModelExists($m);
    }

    public function test_el_listado_no_ofrece_eliminar_si_hay_personas(): void
    {
        $con = $this->ministerio('Con gente');
        $this->ministerio('Vacio');
        $this->persona()->ministerios()->attach($con->id, ['desde' => '2026-01-01']);

        $html = $this->actingAs($this->admin)->get(route('admin.ministerios.index'))->getContent();

        $this->assertSame(1, substr_count($html, 'action="'.route('admin.ministerios.destroy', Ministerio::where('nombre', 'Vacio')->first()).'"'));
        $this->assertStringNotContainsString('action="'.route('admin.ministerios.destroy', $con).'"', $html);
    }

    // ------------------------------------------------------------------ permisos y aislamiento

    public function test_un_ministerio_de_otra_iglesia_no_se_puede_ver_ni_modificar(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        $ajeno = $this->ministerio('Ajeno', [], $otra);

        $this->actingAs($this->admin);
        $this->get(route('admin.ministerios.edit', $ajeno))->assertNotFound();
        $this->put(route('admin.ministerios.update', $ajeno), ['nombre' => 'Hack', 'color' => '#111111'])->assertNotFound();
        $this->patch(route('admin.ministerios.toggle', $ajeno))->assertNotFound();
        $this->delete(route('admin.ministerios.destroy', $ajeno))->assertNotFound();

        $this->assertSame('Ajeno', $ajeno->fresh()->nombre);
        $this->assertTrue($ajeno->fresh()->activo);
    }

    public function test_solo_el_admin_entra_a_la_gestion_de_ministerios(): void
    {
        $m = $this->ministerio('Alabanza');
        $rutas = [
            ['get', route('admin.ministerios.index')],
            ['get', route('admin.ministerios.create')],
            ['post', route('admin.ministerios.store')],
            ['get', route('admin.ministerios.edit', $m)],
            ['put', route('admin.ministerios.update', $m)],
            ['patch', route('admin.ministerios.toggle', $m)],
            ['delete', route('admin.ministerios.destroy', $m)],
        ];

        foreach (['tesorero', 'asistente', 'miembro'] as $rol) {
            $usuario = $this->crearUsuario($this->iglesia, $rol);
            foreach ($rutas as [$metodo, $url]) {
                $this->actingAs($usuario)->{$metodo}($url)->assertForbidden();
            }
        }

        $this->assertModelExists($m);
    }

    public function test_un_visitante_sin_sesion_va_al_login(): void
    {
        $this->get(route('admin.ministerios.index'))->assertRedirect('login');
    }
}

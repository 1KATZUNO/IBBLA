<?php

namespace Tests\Feature;

use App\Models\Culto;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Buscador global de la barra superior (GET /buscar): personas, cultos, pantallas, reportes y manual,
 * agrupados, por palabras, sin importar tildes y respetando el rol: nadie ve lo que no puede abrir.
 */
class BuscadorGlobalTest extends TestCase
{
    use ConIglesia;
    use RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    private User $tesorero;

    private User $asistente;

    private User $miembro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iglesia = $this->crearIglesia('ibbla-bus', 'BUS');
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');
        $this->tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $this->asistente = $this->crearUsuario($this->iglesia, 'asistente');
        $this->miembro = $this->crearUsuario($this->iglesia, 'miembro');

        Persona::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Paula Araya Zuniga', 'pin' => '4321', 'telefono' => '8845-0099', 'activo' => true]);
        Persona::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'José Pérez Mora', 'pin' => '7788', 'telefono' => '8000-1111', 'activo' => false]);

        Culto::create(['tenant_id' => $this->iglesia->id, 'fecha' => '2026-10-11', 'hora' => '10:00', 'tipo_culto' => 'domingo', 'cerrado' => true]);
        Culto::create(['tenant_id' => $this->iglesia->id, 'fecha' => '2026-10-07', 'hora' => '19:00', 'tipo_culto' => 'miércoles']);
        Culto::create(['tenant_id' => $this->iglesia->id, 'fecha' => '2026-09-20', 'hora' => '10:00', 'tipo_culto' => 'domingo']);
    }

    /** @return array<string, mixed> */
    private function buscar(User $usuario, string $q): array
    {
        return $this->actingAs($usuario)->getJson('/buscar?q='.urlencode($q))->assertOk()->json();
    }

    /** @return list<string> */
    private function titulos(array $resultado, string $grupo): array
    {
        foreach ($resultado['grupos'] as $g) {
            if ($g['clave'] === $grupo) {
                return array_column($g['items'], 'titulo');
            }
        }

        return [];
    }

    /** @return list<string> */
    private function claves(array $resultado): array
    {
        return array_column($resultado['grupos'], 'clave');
    }

    public function test_sin_sesion_pide_iniciar_sesion(): void
    {
        $this->get('/buscar?q=paula')->assertRedirect('/login');
    }

    public function test_con_menos_de_dos_letras_no_busca(): void
    {
        $r = $this->buscar($this->admin, 'p');

        $this->assertSame(0, $r['total']);
        $this->assertSame([], $r['grupos']);
    }

    public function test_la_consulta_de_mas_de_100_letras_se_rechaza(): void
    {
        $this->actingAs($this->admin)->getJson('/buscar?q='.str_repeat('a', 101))->assertStatus(422);
    }

    public function test_admin_encuentra_personas_por_nombre_pin_y_telefono(): void
    {
        foreach (['paula', '4321', '8845', '8845-0099', 'ARAYA'] as $consulta) {
            $r = $this->buscar($this->admin, $consulta);
            $this->assertSame(['Paula Araya Zuniga'], $this->titulos($r, 'personas'), "Consulta: {$consulta}");
        }
    }

    public function test_el_resultado_de_una_persona_abre_su_ficha(): void
    {
        $r = $this->buscar($this->admin, 'paula');
        $persona = Persona::where('nombre', 'Paula Araya Zuniga')->first();

        $this->assertSame(route('personas.show', $persona), $r['grupos'][0]['items'][0]['url']);
        $this->assertSame('PIN 4321 · 8845-0099 · Activa', $r['grupos'][0]['items'][0]['detalle']);
    }

    public function test_todas_las_palabras_deben_aparecer(): void
    {
        $this->assertSame(['Paula Araya Zuniga'], $this->titulos($this->buscar($this->admin, 'paula zuniga'), 'personas'));
        $this->assertSame([], $this->titulos($this->buscar($this->admin, 'paula perez'), 'personas'));
    }

    public function test_no_importan_las_tildes_ni_las_mayusculas(): void
    {
        foreach (['jose perez', 'JOSÉ', 'pérez mora', 'jose'] as $consulta) {
            $this->assertSame(['José Pérez Mora'], $this->titulos($this->buscar($this->admin, $consulta), 'personas'), "Consulta: {$consulta}");
        }
    }

    public function test_tesorero_y_asistente_no_ven_personas_porque_no_pueden_abrir_la_ficha(): void
    {
        foreach ([$this->tesorero, $this->asistente, $this->miembro] as $usuario) {
            $r = $this->buscar($usuario, 'paula');
            $this->assertNotContains('personas', $this->claves($r), "El rol {$usuario->rol} no debe ver personas");
            $this->assertSame(0, $r['total']);
        }
    }

    public function test_no_se_mezclan_los_datos_de_otra_iglesia(): void
    {
        $otra = $this->crearIglesia('otra-bus', 'OTR');
        Persona::create(['tenant_id' => $otra->id, 'nombre' => 'Paula de Otra Iglesia', 'activo' => true]);
        Culto::create(['tenant_id' => $otra->id, 'fecha' => '2026-10-11', 'hora' => '10:00', 'tipo_culto' => 'domingo']);

        $this->assertSame(['Paula Araya Zuniga'], $this->titulos($this->buscar($this->admin, 'paula'), 'personas'));
        $this->assertCount(1, $this->titulos($this->buscar($this->admin, '11/10/2026'), 'cultos'));
    }

    public function test_el_super_admin_no_busca_personas_de_ninguna_iglesia(): void
    {
        $super = $this->crearSuperAdmin();

        $this->assertNotContains('personas', $this->claves($this->buscar($super, 'paula')));
    }

    public function test_cultos_por_fecha_en_varios_formatos(): void
    {
        foreach (['11/10/2026', '11/10', '2026-10-11', '11-10-26'] as $consulta) {
            $titulos = $this->titulos($this->buscar($this->tesorero, $consulta), 'cultos');
            $this->assertCount(1, $titulos, "Consulta: {$consulta}");
            $this->assertStringContainsString('11/10/2026', $titulos[0]);
        }
    }

    public function test_cultos_por_mes_dia_de_la_semana_y_tipo(): void
    {
        $this->assertCount(2, $this->titulos($this->buscar($this->tesorero, 'octubre'), 'cultos'));
        $this->assertCount(1, $this->titulos($this->buscar($this->tesorero, 'septiembre 2026'), 'cultos'));
        $this->assertCount(2, $this->titulos($this->buscar($this->tesorero, 'domingo'), 'cultos'));
        $this->assertCount(1, $this->titulos($this->buscar($this->tesorero, 'miercoles'), 'cultos'));
    }

    public function test_el_tesorero_abre_el_recuento_y_el_asistente_la_asistencia(): void
    {
        $culto = Culto::where('fecha', '2026-10-07')->first();

        $porTesorero = $this->buscar($this->tesorero, '07/10/2026');
        $this->assertSame(route('recuento.index', ['culto_id' => $culto->id]), $porTesorero['grupos'][0]['items'][0]['url']);

        $porAsistente = $this->buscar($this->asistente, '07/10/2026');
        $this->assertSame(route('asistencia.create', ['culto_id' => $culto->id]), $porAsistente['grupos'][0]['items'][0]['url']);
    }

    public function test_un_culto_cerrado_abre_la_vista_de_solo_lectura(): void
    {
        $cerrado = Culto::where('fecha', '2026-10-11')->first();

        $r = $this->buscar($this->tesorero, '11/10/2026');

        $this->assertSame(route('recuento.ver-culto-cerrado', $cerrado), $r['grupos'][0]['items'][0]['url']);
        $this->assertStringContainsString('Cerrado', $r['grupos'][0]['items'][0]['detalle']);
    }

    public function test_el_miembro_no_ve_cultos(): void
    {
        $this->assertNotContains('cultos', $this->claves($this->buscar($this->miembro, '11/10/2026')));
    }

    public function test_una_fecha_no_se_confunde_con_un_telefono(): void
    {
        // "11/10" no debe traer personas cuyo telefono contenga 11 y 10
        Persona::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Telefono Parecido', 'telefono' => '81101100', 'activo' => true]);

        $this->assertNotContains('personas', $this->claves($this->buscar($this->admin, '11/10')));
    }

    public function test_cada_rol_encuentra_solo_las_pantallas_que_puede_abrir(): void
    {
        $this->assertContains('Gestionar Usuarios', $this->titulos($this->buscar($this->admin, 'usuarios'), 'pantallas'));
        $this->assertSame([], $this->titulos($this->buscar($this->tesorero, 'usuarios'), 'pantallas'));

        $this->assertContains('Recuento', $this->titulos($this->buscar($this->tesorero, 'recuento'), 'pantallas'));
        $this->assertSame([], $this->titulos($this->buscar($this->asistente, 'recuento'), 'pantallas'));

        $this->assertContains('Asistencia', $this->titulos($this->buscar($this->asistente, 'asistencia'), 'pantallas'));

        // El miembro solo tiene Principal y su perfil: Cumpleaneros es de admin, tesorero y asistente (decision 2.7)
        $this->assertSame([], $this->titulos($this->buscar($this->miembro, 'recuento'), 'pantallas'));
        $this->assertNotContains('Cumpleañeros', $this->titulos($this->buscar($this->miembro, 'cumpleaneros'), 'pantallas'));
        $this->assertContains('Cumpleañeros', $this->titulos($this->buscar($this->asistente, 'cumpleaneros'), 'pantallas'));
        $this->assertContains('Mi cuenta', $this->titulos($this->buscar($this->miembro, 'cuenta'), 'pantallas'));
    }

    public function test_los_reportes_salen_en_su_propio_grupo_y_segun_el_rol(): void
    {
        $this->assertContains('Reporte de ingresos', $this->titulos($this->buscar($this->tesorero, 'reporte ingresos'), 'reportes'));
        // El asistente no puede abrir el reporte de ingresos pero si el de asistencia
        $this->assertNotContains('Reporte de ingresos', $this->titulos($this->buscar($this->asistente, 'reporte ingresos'), 'reportes'));
        $this->assertContains('Reporte de asistencia', $this->titulos($this->buscar($this->asistente, 'reporte asistencia'), 'reportes'));
        $this->assertSame([], $this->titulos($this->buscar($this->miembro, 'reporte'), 'reportes'));
    }

    public function test_las_palabras_clave_encuentran_la_pantalla_aunque_el_nombre_no_coincida(): void
    {
        // "sobres" no esta en el nombre "Recuento" pero si en sus palabras clave
        $this->assertContains('Recuento', $this->titulos($this->buscar($this->tesorero, 'sobres'), 'pantallas'));
        $this->assertContains('Gestionar Personas', $this->titulos($this->buscar($this->admin, 'hermanos'), 'pantallas'));
    }

    public function test_el_resultado_trae_url_icono_y_trazo_para_dibujarlo(): void
    {
        $item = $this->buscar($this->tesorero, 'recuento')['grupos'][0]['items'][0];

        $this->assertSame(route('recuento.index'), $item['url']);
        $this->assertNotEmpty($item['icono']);
        $this->assertNotEmpty($item['trazo']);
    }

    public function test_cada_resultado_trae_el_enlace_a_la_pagina_completa(): void
    {
        $r = $this->buscar($this->admin, 'paula');

        $this->assertSame(route('buscar', ['q' => 'paula']), $r['ver_todo']);
        $this->assertSame('paula', $r['q']);
    }

    public function test_la_pagina_de_resultados_funciona_sin_javascript(): void
    {
        $this->actingAs($this->admin)->get('/buscar?q=paula')
            ->assertOk()
            ->assertSee('Resultados de la búsqueda')
            ->assertSee('Paula Araya Zuniga')
            ->assertSee('Personas');

        $this->actingAs($this->admin)->get('/buscar?q=zzzzzz')
            ->assertOk()
            ->assertSee('No encontramos');

        $this->actingAs($this->admin)->get('/buscar')
            ->assertOk()
            ->assertSee('¿Qué estás buscando?');
    }

    public function test_el_buscador_del_manual_pide_solo_los_temas_del_manual(): void
    {
        $r = $this->actingAs($this->admin)->getJson('/buscar?solo=manual&q=pantalla')->assertOk()->json();

        foreach ($r['grupos'] as $grupo) {
            $this->assertSame('manual', $grupo['clave']);
        }
    }

    public function test_los_reportes_nuevos_se_encuentran_por_sus_sinonimos_y_segun_el_rol(): void
    {
        $casos = [
            ['Cumplimiento de promesas', 'al día'],
            ['Cumplimiento de promesas', 'cumplimiento corte'],
            ['Estados de cuenta en lote', 'estados de cuenta'],
            ['Estados de cuenta en lote', 'lote'],
            ['Reporte General', 'reporte general'],
        ];

        foreach ([$this->admin, $this->tesorero] as $usuario) {
            foreach ($casos as [$titulo, $consulta]) {
                $this->assertContains($titulo, $this->titulos($this->buscar($usuario, $consulta), 'reportes'), "{$usuario->rol}: {$consulta}");
            }
        }

        foreach ([$this->asistente, $this->miembro] as $usuario) {
            foreach ($casos as [$titulo, $consulta]) {
                $this->assertNotContains($titulo, $this->titulos($this->buscar($usuario, $consulta), 'reportes'), "{$usuario->rol}: {$consulta}");
            }
        }
    }

    public function test_los_reportes_de_cultos_e_ingresos_se_encuentran_segun_el_rol(): void
    {
        $casos = [
            ['Reporte de cultos', 'reporte cultos'],
            ['Reporte de cultos', 'firmas'],
            ['Reporte de ingresos', 'ingresos'],
            ['Reporte de ingresos', 'periodo anterior'],
        ];
        foreach ([$this->admin, $this->tesorero] as $usuario) {
            foreach ($casos as [$titulo, $consulta]) {
                $this->assertContains($titulo, $this->titulos($this->buscar($usuario, $consulta), 'reportes'), "{$usuario->rol}: {$consulta}");
            }
        }
        foreach ([$this->asistente, $this->miembro] as $usuario) {
            foreach ($casos as [$titulo, $consulta]) {
                $this->assertNotContains($titulo, $this->titulos($this->buscar($usuario, $consulta), 'reportes'), "{$usuario->rol}: {$consulta}");
            }
        }

        // El reporte de ingresos del buscador abre la pantalla nueva
        $ingresos = collect(collect($this->buscar($this->tesorero, 'reporte ingresos')['grupos'])->firstWhere('clave', 'reportes')['items'])->firstWhere('titulo', 'Reporte de ingresos');
        $this->assertSame(route('reportes.ingresos'), $ingresos['url'] ?? null);
    }

    public function test_el_tesorero_encuentra_los_reportes_de_asistencia(): void
    {
        $this->assertContains('Reporte de asistencia', $this->titulos($this->buscar($this->tesorero, 'reporte asistencia'), 'reportes'));
        $this->assertContains('Asistencia por clase', $this->titulos($this->buscar($this->tesorero, 'asistencia por clase'), 'reportes'));
        $this->assertNotContains('Asistencia por clase', $this->titulos($this->buscar($this->miembro, 'asistencia por clase'), 'reportes'));
    }

    public function test_el_reporte_general_del_buscador_abre_directo_el_pdf(): void
    {
        $r = $this->buscar($this->tesorero, 'reporte general');
        $url = collect($r['grupos'])->firstWhere('clave', 'reportes')['items'][0]['url'];

        $this->assertStringContainsString('accion=ver', $url);
    }

    public function test_accesos_se_encuentra_por_sus_sinonimos_solo_para_el_admin(): void
    {
        foreach (['accesos', 'permisos de edicion'] as $consulta) {
            $this->assertContains('Accesos', $this->titulos($this->buscar($this->admin, $consulta), 'pantallas'), $consulta);
            foreach ([$this->tesorero, $this->asistente, $this->miembro] as $usuario) {
                $this->assertNotContains('Accesos', $this->titulos($this->buscar($usuario, $consulta), 'pantallas'), "{$usuario->rol}: {$consulta}");
            }
        }
    }
}

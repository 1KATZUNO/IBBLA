<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\Manual\Manual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Manual de usuario dentro de la app: rutas, visibilidad por rol, contenido integro (imagenes, enlaces,
 * encabezados), boton "?" de cada pantalla y descarga en PDF.
 */
class ManualTest extends TestCase
{
    use ConIglesia;
    use RefreshDatabase;

    private Tenant $iglesia;

    protected function setUp(): void
    {
        parent::setUp();
        Manual::olvidar();
        $this->iglesia = $this->crearIglesia('ibbla-manual', 'MAN');
    }

    private const ROLES = ['admin', 'tesorero', 'asistente', 'servidor', 'miembro'];

    public function test_sin_sesion_el_manual_pide_iniciar_sesion(): void
    {
        $this->get('/manual')->assertRedirect('/login');
        $this->get('/manual/cultos')->assertRedirect('/login');
        $this->get('/manual/pdf')->assertRedirect('/login');
    }

    public function test_todos_los_roles_abren_el_indice_y_ven_su_nombre_de_rol(): void
    {
        foreach (self::ROLES as $rol) {
            $this->actingAs($this->crearUsuario($this->iglesia, $rol))
                ->get('/manual')
                ->assertOk()
                ->assertSee('Primeros pasos')
                ->assertSee('Tu rol:');
        }
    }

    public function test_cada_pagina_del_manual_abre_para_cada_rol(): void
    {
        $paginas = Manual::paginas();
        $this->assertNotEmpty($paginas);

        foreach (self::ROLES as $rol) {
            $usuario = $this->crearUsuario($this->iglesia, $rol);
            foreach ($paginas as $pagina) {
                $this->actingAs($usuario)
                    ->get(Manual::url($pagina))
                    ->assertOk()
                    ->assertSee($pagina->titulo);
            }
        }
    }

    public function test_una_pagina_que_no_existe_da_404(): void
    {
        $this->actingAs($this->crearUsuario($this->iglesia, 'admin'))
            ->get('/manual/no-existe')->assertNotFound();
    }

    public function test_el_indice_marca_para_tu_rol_solo_lo_que_corresponde(): void
    {
        $tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
        $admin = $this->crearUsuario($this->iglesia, 'admin');

        $this->assertTrue(Manual::esParaRol(Manual::pagina('recuento'), $tesorero));
        $this->assertFalse(Manual::esParaRol(Manual::pagina('personas'), $tesorero), 'El tesorero no usa Personas');
        $this->assertFalse(Manual::esParaRol(Manual::pagina('usuarios'), $tesorero));
        $this->assertTrue(Manual::esParaRol(Manual::pagina('primeros-pasos'), $tesorero));
        $this->assertTrue(Manual::esParaRol(Manual::pagina('personas'), $admin));
        $this->assertTrue(Manual::esParaRol(Manual::pagina('usuarios'), $admin));
    }

    public function test_las_paginas_traen_encabezado_completo_y_roles_validos(): void
    {
        foreach (Manual::paginas() as $pagina) {
            $this->assertNotSame('', $pagina->titulo, $pagina->slug);
            $this->assertNotSame('', $pagina->resumen, "{$pagina->slug} sin resumen");
            $this->assertNotSame('', trim($pagina->cuerpo), "{$pagina->slug} vacia");
            foreach ($pagina->roles as $rol) {
                $this->assertArrayHasKey($rol, Manual::ROLES, "{$pagina->slug}: rol desconocido «{$rol}»");
            }
        }
    }

    public function test_todas_las_capturas_que_cita_el_manual_existen(): void
    {
        $faltan = [];
        foreach (Manual::paginas() as $pagina) {
            preg_match_all('/\(img:([^\s)]+)/', $pagina->cuerpo, $m);
            foreach ($m[1] as $archivo) {
                // Las guias de WhatsApp (whatsapp/*.png) las genera otro equipo y llegan al integrar
                if (str_starts_with($archivo, 'whatsapp/') && ! is_dir(Manual::directorioImagenes().'/whatsapp')) {
                    continue;
                }
                if (! is_file(Manual::directorioImagenes().'/'.$archivo)) {
                    $faltan[] = "{$pagina->slug}: {$archivo}";
                }
            }
        }

        $this->assertSame([], $faltan, "Capturas que faltan (vuelve a correr tools/manual):\n".implode("\n", $faltan));
    }

    public function test_los_enlaces_a_pantallas_del_manual_apuntan_a_rutas_que_existen(): void
    {
        $malos = [];
        foreach (Manual::paginas() as $pagina) {
            preg_match_all('/\]\(route:([a-z0-9_.\-]+)\)/', $pagina->cuerpo, $m);
            foreach ($m[1] as $nombre) {
                if (! Route::has($nombre)) {
                    $malos[] = "{$pagina->slug}: {$nombre}";
                }
            }
        }

        $this->assertSame([], $malos, implode("\n", $malos));
    }

    public function test_cada_pantalla_con_ayuda_tiene_su_seccion_en_el_manual(): void
    {
        $pantallas = [
            'principal' => 'principal', 'cumpleaneros.index' => 'principal',
            'recuento.index' => 'recuento', 'recuento.create' => 'recuento', 'recuento.edit' => 'recuento',
            'cultos.index' => 'cultos', 'cultos.create' => 'cultos', 'cultos.edit' => 'cultos',
            'personas.index' => 'personas', 'personas.create' => 'personas', 'personas.show' => 'personas', 'personas.edit' => 'personas',
            'asistencia.index' => 'asistencia', 'asistencia.create' => 'asistencia', 'admin.clases.index' => 'asistencia',
            'ingresos-asistencia.index' => 'reportes', 'ingresos-asistencia.ingresos' => 'reportes', 'ingresos-asistencia.asistencia-clases' => 'reportes',
            'ingresos-asistencia.promesas' => 'promesas',
            'dashboard' => 'dashboards', 'dashboard.asistencia' => 'dashboards', 'dashboard.promesas' => 'promesas',
            'admin.ministerios.index' => 'ministerios', 'admin.servidores.index' => 'ministerios',
            'usuarios.index' => 'usuarios', 'usuarios.create' => 'usuarios',
            'admin.auditoria.index' => 'auditoria', 'admin.auditoria.ingresos' => 'auditoria',
            'avisos.index' => 'whatsapp',
            'mi-perfil.index' => 'mi-perfil', 'profile.edit' => 'mi-perfil',
            'buscar' => 'primeros-pasos',
        ];

        foreach ($pantallas as $ruta => $slug) {
            $hallada = Manual::paginaParaRuta($ruta);
            $this->assertNotNull($hallada, "La pantalla «{$ruta}» no tiene pagina en el manual");
            $this->assertSame($slug, $hallada['pagina']->slug, "«{$ruta}» abre la pagina equivocada");
            $this->assertStringStartsWith('/manual/'.$slug, Manual::enlaceAyuda($ruta));
        }
    }

    public function test_el_boton_de_ayuda_aparece_en_la_pantalla_y_abre_su_seccion(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin');

        $this->actingAs($admin)->get('/cultos')
            ->assertOk()
            ->assertSee('class="help-btn"', false)
            ->assertSee('href="/manual/cultos"', false);

        $this->actingAs($admin)->get('/personas')
            ->assertSee('href="/manual/personas"', false);
    }

    public function test_cada_rol_ve_el_boton_de_ayuda_en_su_pantalla_principal(): void
    {
        $destino = ['admin' => '/cultos', 'tesorero' => '/recuento', 'asistente' => '/asistencia', 'miembro' => '/principal'];

        foreach ($destino as $rol => $url) {
            $this->actingAs($this->crearUsuario($this->iglesia, $rol))
                ->get($url)
                ->assertOk()
                ->assertSee('class="help-btn"', false);
        }
    }

    public function test_el_boton_manual_de_la_barra_superior_lleva_al_indice(): void
    {
        foreach (self::ROLES as $rol) {
            $this->actingAs($this->crearUsuario($this->iglesia, $rol))
                ->get('/principal')
                ->assertSee('href="'.url('/manual').'"', false);
        }
    }

    public function test_la_pagina_de_whatsapp_explica_los_tres_pasos_de_callmebot(): void
    {
        $pagina = Manual::pagina('mi-perfil');
        $this->assertNotNull($pagina);
        $this->assertSame('whatsapp-como-activar-tus-avisos', $pagina->ancla);

        $this->actingAs($this->crearUsuario($this->iglesia, 'miembro'))
            ->get('/manual/mi-perfil')
            ->assertOk()
            ->assertSee('WhatsApp: cómo activar tus avisos')
            ->assertSee('Paso 1')
            ->assertSee('Paso 2')
            ->assertSee('Paso 3')
            ->assertSee('I allow callmebot to send me messages')
            ->assertSee('id="whatsapp-como-activar-tus-avisos"', false);
    }

    public function test_el_manual_se_descarga_en_pdf_completo_y_por_pagina(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin');

        $completo = $this->actingAs($admin)->get('/manual/pdf');
        $completo->assertOk();
        $this->assertSame('application/pdf', $completo->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $completo->headers->get('Content-Disposition'));

        $uno = $this->actingAs($admin)->get('/manual/cultos/pdf');
        $uno->assertOk();
        $this->assertSame('application/pdf', $uno->headers->get('Content-Type'));
        $this->actingAs($admin)->get('/manual/no-existe/pdf')->assertNotFound();
    }

    public function test_el_pdf_usa_dejavu_para_no_perder_tildes_ni_enies(): void
    {
        $this->assertStringContainsString('DejaVu Sans', file_get_contents(resource_path('views/manual/pdf.blade.php')));

        $respuesta = $this->actingAs($this->crearUsuario($this->iglesia, 'admin'))->get('/manual/cultos/pdf');
        $respuesta->assertOk();
        $bytes = file_get_contents($respuesta->baseResponse->getFile()->getPathname());

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertStringContainsString('DejaVu', $bytes, 'El PDF debe llevar la fuente DejaVu incrustada');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\Manual\Manual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * El boton "?" de cada pantalla: ninguna pantalla de la app se queda sin ayuda y las que tienen una seccion
 * propia (Cumplimiento, Estados de cuenta...) abren esa seccion y no solo la pagina.
 */
class ManualAyudaPantallasTest extends TestCase
{
    use ConIglesia;
    use RefreshDatabase;

    private Tenant $iglesia;

    /** Rutas con nombre que NO son pantalla de la intranet: ingreso, enlaces publicos, plataforma, API y el propio manual. */
    private const SIN_AYUDA = [
        'login', 'password.*', 'verification.*', 'logout',
        'informe.persona',      // enlace firmado que se comparte por WhatsApp (abre sin sesion)
        'api.*', 'storage.*',
        'manual.*',             // el manual no se ayuda a si mismo
        'super-admin.*',        // plataforma: otra consola, con su propio layout y sin este boton
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Manual::olvidar();
        $this->iglesia = $this->crearIglesia('ibbla-ayuda', 'AYU');
    }

    public function test_toda_pantalla_de_la_app_tiene_su_boton_de_ayuda(): void
    {
        $sinAyuda = [];

        foreach (Route::getRoutes() as $ruta) {
            $nombre = $ruta->getName();
            if (! $nombre || ! in_array('GET', $ruta->methods(), true)) {
                continue;
            }
            if (collect(self::SIN_AYUDA)->contains(fn ($patron) => \Illuminate\Support\Str::is($patron, $nombre))) {
                continue;
            }
            if (Manual::enlaceAyuda($nombre) === null) {
                $sinAyuda[] = $nombre.'  ('.$ruta->uri().')';
            }
        }

        $this->assertSame([], $sinAyuda, "Pantallas sin pagina de manual (declara su ruta en el encabezado «rutas:» de la pagina que corresponda):\n".implode("\n", $sinAyuda));
    }

    public function test_cumplimiento_y_estados_de_cuenta_abren_su_propia_seccion(): void
    {
        $this->assertSame('/manual/reportes#cumplimiento-de-promesas-con-corte', Manual::enlaceAyuda('reportes.cumplimiento'));
        $this->assertSame('/manual/reportes#cumplimiento-de-promesas-con-corte', Manual::enlaceAyuda('reportes.cumplimiento.pdf'));
        $this->assertSame('/manual/reportes#cumplimiento-de-promesas-con-corte', Manual::enlaceAyuda('reportes.cumplimiento.excel'));
        $this->assertSame('/manual/reportes#estados-de-cuenta-en-lote', Manual::enlaceAyuda('reportes.estados-cuenta'));
        $this->assertSame('/manual/reportes#estados-de-cuenta-en-lote', Manual::enlaceAyuda('reportes.estados-cuenta.zip'));
        $this->assertSame('/manual/reportes#estados-de-cuenta-en-lote', Manual::enlaceAyuda('reportes.estados-cuenta.excel'));

        // Los demas reportes siguen en su pagina; el de promesas, en la suya
        $this->assertStringStartsWith('/manual/reportes', Manual::enlaceAyuda('ingresos-asistencia.index'));
        $this->assertStringStartsWith('/manual/promesas', Manual::enlaceAyuda('ingresos-asistencia.promesas'));
    }

    public function test_el_boton_de_ayuda_de_cumplimiento_y_estados_de_cuenta_lleva_a_su_seccion_para_admin_y_tesorero(): void
    {
        foreach (['admin', 'tesorero'] as $rol) {
            $usuario = $this->crearUsuario($this->iglesia, $rol);

            $this->actingAs($usuario)->get(route('reportes.cumplimiento'))
                ->assertOk()
                ->assertSee('class="help-btn"', false)
                ->assertSee('href="/manual/reportes#cumplimiento-de-promesas-con-corte"', false);

            $this->actingAs($usuario)->get(route('reportes.estados-cuenta'))
                ->assertOk()
                ->assertSee('class="help-btn"', false)
                ->assertSee('href="/manual/reportes#estados-de-cuenta-en-lote"', false);
        }
    }

    public function test_cada_ancla_que_declaran_las_paginas_existe_como_seccion(): void
    {
        $malas = [];

        foreach (Manual::paginas() as $pagina) {
            $anclas = array_filter([$pagina->ancla]);
            foreach ($pagina->rutas as $declaracion) {
                if (str_contains($declaracion, '#')) {
                    $anclas[] = explode('#', $declaracion, 2)[1];
                }
            }

            $ids = array_column(Manual::render($pagina)['secciones'], 'id');
            foreach (array_unique($anclas) as $ancla) {
                if (! in_array($ancla, $ids, true)) {
                    $malas[] = "{$pagina->slug}: #{$ancla}";
                }
            }
        }

        $this->assertSame([], $malas, "Anclas que no existen como titulo de seccion:\n".implode("\n", $malas));
    }

    public function test_un_nombre_de_ruta_sin_pagina_no_pinta_el_boton(): void
    {
        $this->assertNull(Manual::enlaceAyuda('ruta.que.no.existe'));
        $this->assertNull(Manual::enlaceAyuda(null));
    }
}

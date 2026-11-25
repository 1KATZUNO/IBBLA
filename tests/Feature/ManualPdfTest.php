<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\Manual\Manual;
use App\Support\Manual\ManualPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * PDF del manual completo: lleva portada, indice, las paginas con sus capturas; se descarga desde el manual
 * y, una vez generado (php artisan manual:pdf, que corre en el deploy), responde en menos de 10 s
 * (el limite de Hostinger).
 */
class ManualPdfTest extends TestCase
{
    use ConIglesia;
    use RefreshDatabase;

    private Tenant $iglesia;

    protected function setUp(): void
    {
        parent::setUp();
        Manual::olvidar();
        $this->iglesia = $this->crearIglesia('ibbla-pdf', 'PDF');
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/manual/manual-'.$this->iglesia->id.'-*.pdf')) ?: [] as $archivo) {
            File::delete($archivo);
        }
        parent::tearDown();
    }

    public function test_el_pdf_completo_lleva_portada_indice_y_todas_las_paginas_con_sus_capturas(): void
    {
        $paginas = Manual::paginas();
        $this->assertGreaterThanOrEqual(14, count($paginas));

        app()->instance('current_tenant', $this->iglesia);
        $html = ManualPdf::vista($paginas, true)->render();

        $this->assertStringContainsString('Manual de usuario', $html);
        $this->assertStringContainsString('<h2>Contenido</h2>', $html);
        $this->assertSame(count($paginas), substr_count($html, 'class="modulo'), 'Una seccion por cada pagina del manual');

        foreach ($paginas as $pagina) {
            // En el indice y como titulo de su pagina
            $this->assertGreaterThanOrEqual(2, substr_count($html, e($pagina->titulo)), "Falta «{$pagina->titulo}» en el PDF");
        }

        // Las capturas van incrustadas por ruta de archivo (DomPDF no baja URLs) y todas existen
        preg_match_all('#<img src="([^"]*images/manual/[^"]+)"#', $html, $m);
        $this->assertGreaterThan(20, count($m[1]), 'El PDF debe llevar las capturas');
        foreach ($m[1] as $ruta) {
            $this->assertFileExists($ruta);
        }
    }

    public function test_el_comando_manual_pdf_deja_el_archivo_listo_y_la_descarga_lo_sirve_en_menos_de_10_segundos(): void
    {
        $this->artisan('manual:pdf', ['--iglesia' => $this->iglesia->slug])->assertSuccessful();

        $listos = glob(storage_path('app/manual/manual-'.$this->iglesia->id.'-completo-*.pdf'));
        $this->assertCount(1, $listos, 'El comando deja un solo PDF completo de la iglesia');
        $bytes = file_get_contents($listos[0]);
        $this->assertStringStartsWith('%PDF', $bytes);

        // El manual completo son varias decenas de paginas, no una sola
        $paginasPdf = preg_match_all('#/Type\s*/Page[^s]#', $bytes);
        $this->assertGreaterThanOrEqual(30, $paginasPdf, 'El PDF completo debe tener todas las paginas del manual');

        $inicio = microtime(true);
        $respuesta = $this->actingAs($this->crearUsuario($this->iglesia, 'tesorero'))->get('/manual/pdf');
        $segundos = microtime(true) - $inicio;

        $respuesta->assertOk();
        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $respuesta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('manual-de-usuario-pdf.pdf', $respuesta->headers->get('Content-Disposition'));
        $this->assertSame($listos[0], $respuesta->baseResponse->getFile()->getPathname(), 'Sirve el archivo ya generado, no uno nuevo');
        $this->assertLessThan(10, $segundos, "Descargar el PDF ya generado tardo {$segundos} s");
    }

    public function test_todos_los_roles_pueden_descargar_el_pdf_completo_desde_el_manual(): void
    {
        $this->artisan('manual:pdf', ['--iglesia' => $this->iglesia->slug])->assertSuccessful();

        foreach (['admin', 'tesorero', 'asistente', 'servidor', 'miembro'] as $rol) {
            $this->actingAs($this->crearUsuario($this->iglesia, $rol))
                ->get('/manual/pdf')
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_el_indice_del_manual_ofrece_la_descarga_del_pdf_completo(): void
    {
        $this->actingAs($this->crearUsuario($this->iglesia, 'miembro'))
            ->get('/manual')
            ->assertOk()
            ->assertSee(route('manual.pdf'), false);
    }

    public function test_si_cambia_una_pagina_el_pdf_se_vuelve_a_generar(): void
    {
        app()->instance('current_tenant', $this->iglesia);
        $paginas = Manual::paginas();
        $huellaAntes = basename(ManualPdf::archivo(array_slice($paginas, 0, 1), false));

        $mtime = filemtime($paginas[0]->archivo);
        try {
            touch($paginas[0]->archivo, $mtime + 5);
            clearstatcache();
            Manual::olvidar();
            $huellaDespues = basename(ManualPdf::archivo(array_slice(Manual::paginas(), 0, 1), false));
        } finally {
            touch($paginas[0]->archivo, $mtime);
        }

        $this->assertNotSame($huellaAntes, $huellaDespues);
    }
}

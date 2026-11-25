<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Finder\Finder;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\TestCase;

/**
 * El reporte mensual por correo toma sus destinatarios de config('reportes.mensual'), no de env():
 * con la configuración en caché (así queda producción tras cada deploy) env() devuelve vacío fuera
 * de config/ y el comando abortaba sin enviar. Escenario de reportes con "hoy" = 15/10/2026.
 */
class ReporteMensualConfigTest extends TestCase
{
    use CreaEscenarioReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->crearEscenarioReportes();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_los_destinatarios_salen_de_la_configuracion(): void
    {
        config(['reportes.mensual.para' => 'tesoreria@example.org, no-es-un-correo ,pastor@example.org']);

        $this->artisan('reporte:enviar-mensual', ['--dry-run' => true, '--mes' => 9, '--año' => 2026, '--tenant' => $this->iglesia->id])
            ->expectsOutputToContain('para: tesoreria@example.org, pastor@example.org')
            ->expectsOutputToContain('--dry-run activo: NO se envía el correo.')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_sin_destinatarios_no_envia_y_lo_dice(): void
    {
        config(['reportes.mensual.para' => '']);

        $this->artisan('reporte:enviar-mensual', ['--mes' => 9, '--año' => 2026, '--tenant' => $this->iglesia->id])
            ->expectsOutputToContain('No hay destinatarios configurados')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_la_iglesia_por_defecto_sale_de_la_configuracion(): void
    {
        config(['reportes.mensual.para' => 'tesoreria@example.org', 'reportes.mensual.iglesia_id' => $this->iglesia->id]);

        $this->artisan('reporte:enviar-mensual', ['--dry-run' => true, '--mes' => 9, '--año' => 2026])
            ->expectsOutputToContain('Tenant activo: '.$this->iglesia->siglas)
            ->assertSuccessful();
    }

    /** Fuera de config/ nadie lee env(): con la configuración en caché devolvería vacío. */
    public function test_el_codigo_de_la_app_no_lee_env_directamente(): void
    {
        $infractores = [];
        foreach ((new Finder)->files()->in([app_path(), base_path('routes')])->name('*.php') as $archivo) {
            if (preg_match('/(?<![A-Za-z0-9_>:$])env\(/', $archivo->getContents())) {
                $infractores[] = $archivo->getRelativePathname();
            }
        }

        $this->assertSame([], $infractores, 'Usar config() y declarar el valor en config/*.php');
    }
}

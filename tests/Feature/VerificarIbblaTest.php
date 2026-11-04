<?php

namespace Tests\Feature;

use App\Models\Promesa;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * php artisan ibbla:verificar (chequeo posterior al deploy).
 *
 * Usa MySQL (las migraciones no corren en SQLite):
 *   DB_CONNECTION=mysql DB_DATABASE=ibbla_x_test php vendor/bin/phpunit --filter VerificarIbblaTest
 */
class VerificarIbblaTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->crearIglesiaConAdmin();
        $this->crearCategoriasPorDefecto();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function cargarDatos(): void
    {
        $ana = $this->crearPersona('Ana Mora');
        $this->crearSobre($this->crearCulto('2026-01-04'), $ana, ['diezmo' => 30000, 'misiones' => 10000]);
        Promesa::create(['persona_id' => $ana->id, 'categoria' => 'misiones', 'monto' => 10000, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);
    }

    public function test_entorno_sano_termina_ok_y_mide_los_dos_reportes(): void
    {
        $this->cargarDatos();

        $this->artisan('ibbla:verificar')
            ->expectsOutputToContain('Conexión: mysql')
            ->expectsOutputToContain('Migraciones: al día')
            ->expectsOutputToContain('Reporte General (PDF)')
            ->expectsOutputToContain('Estado de cuenta de Ana Mora (PDF)')
            ->expectsOutputToContain('RESUMEN: OK')
            ->assertExitCode(0);
    }

    public function test_sin_datos_avisa_pero_no_falla(): void
    {
        $this->artisan('ibbla:verificar')
            ->expectsOutputToContain('no hay iglesia con personas activas')
            ->expectsOutputToContain('RESUMEN: OK')
            ->assertExitCode(0);
    }

    public function test_fuera_de_produccion_los_problemas_de_entorno_son_solo_avisos(): void
    {
        config(['app.debug' => true]);

        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('APP_DEBUG: true (en el servidor debe ser false)')
            ->expectsOutputToContain('RESUMEN: OK')
            ->assertExitCode(0);
    }

    public function test_en_produccion_con_debug_y_sin_caches_falla(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true]);

        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('APP_DEBUG: true en producción')
            ->expectsOutputToContain('RESUMEN: FALLA')
            ->assertExitCode(1);
    }

    public function test_migracion_pendiente_es_falla(): void
    {
        $ultima = DB::table('migrations')->orderByDesc('id')->value('migration');
        DB::table('migrations')->where('migration', $ultima)->delete();

        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('1 pendiente(s): '.$ultima)
            ->expectsOutputToContain('RESUMEN: FALLA')
            ->assertExitCode(1);
    }

    public function test_el_umbral_pedido_se_muestra_en_cada_reporte(): void
    {
        $this->cargarDatos();

        $this->artisan('ibbla:verificar', ['--umbral' => 600])
            ->expectsOutputToContain('(límite 600 s)')
            ->expectsOutputToContain('RESUMEN: OK')
            ->assertExitCode(0);
    }

    public function test_iglesia_inexistente_no_revienta(): void
    {
        $this->cargarDatos();

        $this->artisan('ibbla:verificar', ['--tenant' => 99999])
            ->expectsOutputToContain('no hay iglesia con personas activas')
            ->assertExitCode(0);
    }
}

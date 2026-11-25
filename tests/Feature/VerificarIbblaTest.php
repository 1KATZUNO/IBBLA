<?php

namespace Tests\Feature;

use App\Console\Commands\VerificarIbbla;
use App\Models\AvisoWhatsApp;
use App\Models\Promesa;
use Carbon\Carbon;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
        // Entorno sano: el cron del planificador corrió hace un momento.
        Cache::forever(VerificarIbbla::CLAVE_LATIDO, now()->subMinute()->toIso8601String());
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

    public function test_cuentas_con_la_contrasena_de_la_semilla_son_aviso_o_falla_segun_el_entorno(): void
    {
        $this->seed(AdminUserSeeder::class);

        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('admin@ibbsc.com, super@admin.com, tesorero@ibbsc.com conservan la contraseña')
            ->expectsOutputToContain('RESUMEN: OK')
            ->assertExitCode(0);

        $this->app['env'] = 'production';

        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('contraseña publica en el repositorio')
            ->assertExitCode(1);
    }

    public function test_cuenta_de_semilla_con_otra_contrasena_no_se_marca(): void
    {
        $this->seed(AdminUserSeeder::class);
        DB::table('users')->update(['password' => bcrypt('una-clave-propia-larga')]);

        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('ninguna conserva la contraseña del repositorio')
            ->assertExitCode(0);
    }

    // ---------------------------------------------------------------- QE-017: ambiente, cron y avisos

    public function test_informa_el_ambiente_y_el_cron_vivo(): void
    {
        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('ambiente testing')
            ->expectsOutputToContain('Cron del planificador: última corrida hace 1 min')
            ->expectsOutputToContain('QUEUE_CONNECTION: sync')
            ->expectsOutputToContain('RESUMEN: OK')
            ->assertExitCode(0);
    }

    public function test_sin_latido_del_planificador_falla(): void
    {
        Cache::forget(VerificarIbbla::CLAVE_LATIDO);

        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('Cron del planificador: nunca corrió')
            ->expectsOutputToContain('RESUMEN: FALLA')
            ->assertExitCode(1);
    }

    public function test_latido_de_mas_de_10_minutos_falla(): void
    {
        Cache::forever(VerificarIbbla::CLAVE_LATIDO, now()->subMinutes(11)->toIso8601String());

        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('última corrida hace 11 min')
            ->expectsOutputToContain('RESUMEN: FALLA')
            ->assertExitCode(1);
    }

    public function test_latido_de_10_minutos_todavia_es_ok(): void
    {
        Cache::forever(VerificarIbbla::CLAVE_LATIDO, now()->subMinutes(10)->toIso8601String());

        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('RESUMEN: OK')
            ->assertExitCode(0);
    }

    public function test_en_la_maquina_de_desarrollo_el_cron_ausente_es_solo_aviso(): void
    {
        $this->app['env'] = 'local';
        Cache::forget(VerificarIbbla::CLAVE_LATIDO);

        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('en desarrollo es normal')
            ->expectsOutputToContain('RESUMEN: OK')
            ->assertExitCode(0);
    }

    public function test_la_tarea_programada_deja_el_latido(): void
    {
        Cache::forget(VerificarIbbla::CLAVE_LATIDO);

        $this->artisan('schedule:run')->assertExitCode(0);

        $this->assertNotNull(Cache::get(VerificarIbbla::CLAVE_LATIDO));
    }

    public function test_avisa_si_hay_avisos_automaticos_que_nadie_procesa(): void
    {
        AvisoWhatsApp::withoutGlobalScopes()->create([
            'tenant_id' => $this->iglesia->id, 'evento' => AvisoWhatsApp::EVENTO_CULTO_CREADO, 'titulo' => 'Culto creado',
            'mensaje' => 'Hola', 'destinatario_nombre' => 'Admin', 'destinatario_telefono' => '50688881001',
            'user_id' => $this->admin->id, 'estado' => AvisoWhatsApp::PENDIENTE,
        ])->forceFill(['envio_automatico' => true, 'created_at' => now()->subMinutes(30)])->save();

        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('1 aviso(s) automático(s) esperan hace más de 10 min')
            ->assertExitCode(0);
    }

    public function test_avisa_si_la_cola_no_es_sync(): void
    {
        config(['queue.default' => 'database']);

        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('QUEUE_CONNECTION: database: en Hostinger no hay worker')
            ->assertExitCode(0);
    }
}

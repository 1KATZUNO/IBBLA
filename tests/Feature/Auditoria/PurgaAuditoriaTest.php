<?php

namespace Tests\Feature\Auditoria;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * audit:purge (programado cada mes en routes/console.php): el detalle de las peticiones se
 * purga a los 30 días, pero el historial de ingresos se conserva más tiempo.
 */
class PurgaAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private function fila(string $action, int $diasAtras, ?string $nombre = null): AuditLog
    {
        return AuditLog::forceCreate([
            'user_name' => $nombre ?? $action.' hace '.$diasAtras,
            'action' => $action,
            'created_at' => now()->subDays($diasAtras),
            'updated_at' => now()->subDays($diasAtras),
        ]);
    }

    private function nombres(): array
    {
        return AuditLog::orderBy('id')->pluck('user_name')->all();
    }

    public function test_purga_las_peticiones_viejas_pero_no_los_ingresos_de_hace_un_par_de_meses(): void
    {
        $this->fila('recuento.store', 5);
        $this->fila('recuento.store', 45);              // peticion vieja: se va
        $this->fila(AuditLog::ACCION_LOGIN, 45);        // ingreso de hace 45 dias: se queda
        $this->fila(AuditLog::ACCION_LOGOUT, 200);      // salida de hace 200 dias: se queda
        $this->fila(AuditLog::ACCION_LOGIN_FALLIDO, 100);

        $this->artisan('audit:purge')->assertSuccessful();

        $this->assertSame([
            'recuento.store hace 5',
            'auth.login hace 45',
            'auth.logout hace 200',
            'auth.failed hace 100',
        ], $this->nombres());
    }

    public function test_los_ingresos_se_purgan_pasado_su_propio_plazo_de_un_anio(): void
    {
        $this->fila(AuditLog::ACCION_LOGIN, 364);
        $this->fila(AuditLog::ACCION_LOGIN, 400);
        $this->fila(AuditLog::ACCION_LOGOUT, 500);

        $this->artisan('audit:purge')->assertSuccessful();

        $this->assertSame(['auth.login hace 364'], $this->nombres());
    }

    public function test_las_filas_sin_action_se_tratan_como_peticiones(): void
    {
        AuditLog::forceCreate(['user_name' => 'sin accion vieja', 'action' => null, 'created_at' => now()->subDays(60), 'updated_at' => now()]);
        AuditLog::forceCreate(['user_name' => 'sin accion reciente', 'action' => null, 'created_at' => now()->subDays(2), 'updated_at' => now()]);

        $this->artisan('audit:purge')->assertSuccessful();

        $this->assertSame(['sin accion reciente'], $this->nombres());
    }

    public function test_las_opciones_ajustan_cada_plazo(): void
    {
        $this->fila('recuento.store', 10);
        $this->fila(AuditLog::ACCION_LOGIN, 10);
        $this->fila(AuditLog::ACCION_LOGIN, 40);

        $this->artisan('audit:purge', ['--days' => 7, '--auth-days' => 30])->assertSuccessful();

        $this->assertSame(['auth.login hace 10'], $this->nombres());
    }

    public function test_el_plazo_de_ingresos_nunca_es_menor_que_el_de_peticiones(): void
    {
        $this->fila(AuditLog::ACCION_LOGIN, 100);

        // --auth-days=10 con --days=400: los ingresos no pueden durar menos que las peticiones.
        $this->artisan('audit:purge', ['--days' => 400, '--auth-days' => 10])->assertSuccessful();

        $this->assertSame(['auth.login hace 100'], $this->nombres());
    }

    public function test_sin_nada_que_purgar_lo_dice_y_termina_bien(): void
    {
        $this->fila(AuditLog::ACCION_LOGIN, 1);

        $this->artisan('audit:purge')
            ->expectsOutputToContain('No hay registros de auditoria para purgar')
            ->assertSuccessful();

        $this->assertCount(1, $this->nombres());
    }
}

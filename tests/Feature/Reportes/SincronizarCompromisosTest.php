<?php

namespace Tests\Feature\Reportes;

use App\Models\Compromiso;
use App\Models\Culto;
use App\Services\Reportes\MotorReportes;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Reportes\SincronizadorCompromisos;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\TestCase;

/**
 * QA-025: las pantallas no escriben; la tabla compromisos la llena solo el sincronizador (comando
 * compromisos:sincronizar, cierre de culto y boton del admin), idempotente y acotado.
 */
class SincronizarCompromisosTest extends TestCase
{
    use CreaEscenarioReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->crearEscenarioReportes();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function sincronizar(?int $mes = null, int $anio = 2026): array
    {
        return app(SincronizadorCompromisos::class)->sincronizar($this->iglesia, $anio, $mes);
    }

    public function test_consultar_compromisos_y_mi_perfil_no_escribe_nada(): void
    {
        foreach ([2025, 2026, 2027, 2030] as $anio) {
            $this->actingAs($this->admin)->get(route('compromisos.show', ['persona' => $this->ana, 'año' => $anio, 'mes' => 1]))->assertOk();
        }
        $this->actingAs($this->admin)->get(route('personas.informe', ['persona' => $this->ana, 'accion' => 'ver']))->assertOk();

        $this->assertSame(0, Compromiso::count());
    }

    public function test_solo_meses_con_cultos_hasta_hoy_y_las_cifras_del_motor(): void
    {
        $r = $this->sincronizar();

        // Cultos de la iglesia en 2026 hasta hoy: ene, feb, mar, jun, ago, sep, oct (el del 01/11 no).
        $this->assertSame([1, 2, 3, 6, 8, 9, 10], $r['meses']);
        // Ana (3 promesas) y Carla (2): 5 x 7 meses. Beto no tiene promesa; la inactiva no entra.
        $this->assertSame(35, $r['creados']);
        $this->assertSame(35, Compromiso::count());
        $this->assertSame(0, Compromiso::where('año', 2026)->where('mes', '>', 10)->count());

        $cuenta = app(MotorReportes::class)->calcular(PeriodoReporte::deMeses(2026, 1, 10), [$this->ana])->cuenta($this->ana->id);
        $febrero = Compromiso::where(['persona_id' => $this->ana->id, 'categoria' => 'misiones', 'año' => 2026, 'mes' => 2])->sole();
        // Misiones de febrero: 10.000 + $20 x 510 del sobre; lo prometido, 10.000.
        $this->assertEquals(10000, $febrero->monto_prometido);
        $this->assertEquals(10020, $febrero->monto_dado);
        $this->assertEquals($cuenta->dado('2026-02', ['misiones']), (float) $febrero->monto_dado_crc);
        $this->assertEquals(20200, (float) $febrero->monto_dado_crc);
        $this->assertEquals(10200, (float) $febrero->saldo_actual);

        // Octubre (en curso): lo prometido del mes se guarda como dato (no se cobra en los reportes).
        $seminario = Compromiso::where(['persona_id' => $this->ana->id, 'categoria' => 'seminario', 'año' => 2026, 'mes' => 10])->sole();
        $this->assertSame('USD', $seminario->moneda_promesa);
        $this->assertEquals(10, $seminario->monto_prometido);
        $this->assertEquals(5000, (float) $seminario->monto_prometido_crc);
        $this->assertEquals(500, (float) $seminario->tipo_cambio_usado);
    }

    public function test_es_idempotente(): void
    {
        $this->sincronizar();
        $antes = Compromiso::orderBy('id')->get(['id', 'updated_at'])->toArray();

        Carbon::setTestNow('2026-10-15 11:00:00');
        $r = $this->sincronizar();

        $this->assertSame(0, $r['creados'] + $r['actualizados'] + $r['borrados']);
        $this->assertSame(35, $r['sin_cambios']);
        $this->assertSame($antes, Compromiso::orderBy('id')->get(['id', 'updated_at'])->toArray());
    }

    public function test_corrige_lo_que_cambio_y_borra_lo_que_sobra(): void
    {
        $this->sincronizar();
        // Un sobre nuevo en marzo, una promesa borrada y basura de meses futuros (QA-025).
        $this->crearSobre($this->crearCulto('2026-03-15'), $this->ana, ['construccion' => 1000]);
        $this->carla->promesas()->where('categoria', 'micro')->delete();
        Compromiso::create(['persona_id' => $this->ana->id, 'categoria' => 'misiones', 'año' => 2027, 'mes' => 1, 'saldo_actual' => -10000]);
        Compromiso::create(['persona_id' => $this->ana->id, 'categoria' => 'misiones', 'año' => 2026, 'mes' => 12, 'saldo_actual' => -10000]);

        $r = $this->sincronizar();

        $this->assertSame(1, $r['actualizados']);
        // 7 meses de micro de Carla + los 2 futuros.
        $this->assertSame(9, $r['borrados']);
        $this->assertEquals(1000, Compromiso::where(['persona_id' => $this->ana->id, 'categoria' => 'construccion', 'mes' => 3])->value('monto_dado_crc'));
        $this->assertSame(0, Compromiso::where('persona_id', $this->carla->id)->where('categoria', 'micro')->count());
        $this->assertSame(0, Compromiso::where('año', '>', 2026)->count());
    }

    public function test_un_solo_mes_y_meses_sin_cultos(): void
    {
        $this->assertSame([3], $this->sincronizar(3)['meses']);
        $this->assertSame(5, Compromiso::count());

        // Abril no tuvo cultos: no se crea nada.
        $this->assertSame([], $this->sincronizar(4)['meses']);
        $this->assertSame(5, Compromiso::count());
    }

    public function test_al_cerrar_un_culto_se_sincroniza_su_mes(): void
    {
        $culto = Culto::whereDate('fecha', '2026-06-07')->firstOrFail();
        $culto->update(['cerrado' => true, 'cerrado_at' => now()]);

        $this->assertSame(5, Compromiso::where('año', 2026)->where('mes', 6)->count());
        $this->assertSame(5, Compromiso::count());
    }

    public function test_el_comando_sincroniza_y_avisa(): void
    {
        $this->artisan('compromisos:sincronizar', ['--tenant' => [$this->iglesia->id], '--anio' => 2026])
            ->expectsOutputToContain('Compromisos de 2026')
            ->assertSuccessful();
        $this->assertSame(35, Compromiso::count());

        $this->artisan('compromisos:sincronizar', ['--mes' => 13])->assertFailed();
    }

    public function test_el_boton_del_admin_recalcula_y_otros_roles_no(): void
    {
        $this->actingAs($this->admin)->post(route('compromisos.recalcular'))->assertRedirect()->assertSessionHas('success');
        $this->assertSame(35, Compromiso::count());

        $this->actingAs($this->crearUsuarioConRol('tesorero'))->post(route('compromisos.recalcular'))->assertForbidden();
    }
}

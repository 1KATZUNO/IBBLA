<?php

namespace Tests\Feature;

use App\Models\Compromiso;
use App\Models\Culto;
use App\Models\Persona;
use App\Models\Promesa;
use App\Models\Sobre;
use App\Models\TipoCambio;
use App\Services\CalculoTotalesCultoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioWhatsApp;
use Tests\TestCase;

/**
 * php artisan ingresos:analizar: cada revisión encuentra el defecto que se le siembra, no inventa casos en datos
 * sanos y nunca escribe en la base.
 */
class AnalizarIngresosComandoTest extends TestCase
{
    use CreaEscenarioWhatsApp;
    use RefreshDatabase;

    private Persona $ana;

    private Persona $luis;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->crearIglesiaConAdmin();
        $this->crearCategoriasPorDefecto();
        $this->ana = $this->crearPersona('Ana Mora', '8888-7777');
        $this->luis = $this->crearPersona('Luis Soto', '8999-1111');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function analizar(array $opciones = []): string
    {
        Artisan::call('ingresos:analizar', $opciones);

        return Artisan::output();
    }

    private function cultoCerrado(string $fecha): Culto
    {
        $culto = $this->crearCulto($fecha);
        $culto->update(['cerrado' => true, 'tipo_cambio_venta' => 500]);

        return $culto;
    }

    public function test_con_datos_sanos_no_encuentra_nada_y_no_cambia_la_base(): void
    {
        TipoCambio::create(['fecha' => '2026-10-01', 'compra' => 500, 'venta' => 510, 'source' => 'manual']);
        $culto = $this->cultoCerrado('2026-10-11');
        $this->crearSobre($culto, $this->ana, ['diezmo' => 10000, 'misiones' => 5000]);
        $this->crearSobre($culto, $this->luis, ['diezmo' => 20, 'misiones' => 5], 'USD', 500);
        app(CalculoTotalesCultoService::class)->recalcular($culto);
        $antes = [DB::table('sobres')->count(), DB::table('totales_culto')->count(), DB::table('compromisos')->count()];

        $salida = $this->analizar();

        $this->assertStringContainsString('# Análisis de ingresos', $salida);
        $this->assertStringContainsString('| Sobres contra sus detalles | 2 | 0 | OK |', $salida);
        $this->assertStringContainsString('| Totales de cada culto contra sus sobres | 1 | 0 | OK |', $salida);
        $this->assertStringContainsString('| Posibles duplicados | 2 | 0 | OK |', $salida);
        $this->assertSame($antes, [DB::table('sobres')->count(), DB::table('totales_culto')->count(), DB::table('compromisos')->count()]);
    }

    public function test_un_sobre_cuyo_total_no_es_la_suma_de_sus_detalles_sale_con_su_diferencia(): void
    {
        $culto = $this->cultoCerrado('2026-10-11');
        $sobre = $this->crearSobre($culto, $this->ana, ['diezmo' => 10000, 'misiones' => 5000]);
        $sobre->update(['total_declarado' => 16000]);

        $salida = $this->analizar();

        $this->assertStringContainsString('| Sobres contra sus detalles | 1 | 1 | ALERTA |', $salida);
        $this->assertStringContainsString('| 11/10/2026 | #'.$sobre->numero_sobre.' | Ana Mora | CRC | ₡16,000.00 | ₡15,000.00 | +₡1,000.00 |', $salida);
        $this->assertStringContainsString('Estado de cuenta de cada persona contra sus sobres (2026)', $salida);
        $this->assertStringContainsString('| Ana Mora | 1 | ₡15,000.00 | ₡16,000.00 | +₡1,000.00 |', $salida);
    }

    public function test_un_culto_con_total_guardado_viejo_y_otro_sin_totales_se_detectan(): void
    {
        $viejo = $this->cultoCerrado('2026-10-04');
        $this->crearSobre($viejo, $this->ana, ['diezmo' => 10000]);
        app(CalculoTotalesCultoService::class)->recalcular($viejo);
        $this->crearSobre($viejo, $this->luis, ['diezmo' => 3000]); // entra sin recalcular
        $sinTotales = $this->cultoCerrado('2026-10-11');
        $this->crearSobre($sinTotales, $this->ana, ['diezmo' => 7000]);

        $salida = $this->analizar();

        $this->assertStringContainsString('| 04/10/2026 domingo | cerrado | ₡10,000.00 | ₡13,000.00 | +₡3,000.00 | 1 / 2 |', $salida);
        $this->assertStringContainsString('| 11/10/2026 domingo | 1 | ₡7,000.00 | $0.00 |', $salida);
    }

    public function test_detecta_sobres_repetidos_numeros_repetidos_y_personas_con_el_mismo_nombre(): void
    {
        $culto = $this->cultoCerrado('2026-10-11');
        $a = $this->crearSobre($culto, $this->ana, ['diezmo' => 10000]);
        $b = $this->crearSobre($culto, $this->ana, ['diezmo' => 10000]);
        Sobre::whereIn('id', [$a->id, $b->id])->update(['numero_sobre' => 5]);
        $this->crearPersona('ANA  MORÁ', '8000-0000');

        $salida = $this->analizar();

        $this->assertStringContainsString('| Posibles duplicados | 2 | 3 | ALERTA |', $salida);
        $this->assertStringContainsString('| 11/10/2026 | Ana Mora | ₡10,000.00 | efectivo | #5, #5 | sí (', $salida);
        $this->assertStringContainsString('| 11/10/2026 | #5 | 2 |', $salida);
        $this->assertStringContainsString('| Ana Mora | 2 | #', $salida);
    }

    public function test_los_sobres_anonimos_y_los_dolares_sin_tipo_de_cambio_se_cuentan(): void
    {
        TipoCambio::create(['fecha' => '2026-10-01', 'compra' => 500, 'venta' => 510, 'source' => 'manual']);
        $culto = $this->crearCulto('2026-10-11');
        $this->crearSobre($culto, null, ['diezmo' => 4000]);
        $this->crearSobre($culto, $this->ana, ['diezmo' => 20], 'USD');

        $salida = $this->analizar();

        $this->assertStringContainsString('- Sobres sin persona: **1**', $salida);
        $this->assertStringContainsString('- Sobres en USD sin tipo de cambio: **1**', $salida);
        $this->assertStringContainsString('el del día del culto (510.00)', $salida);
    }

    public function test_un_culto_con_sobres_y_vario_tiempo_abierto_sale_y_uno_reciente_no(): void
    {
        $viejo = $this->crearCulto('2026-10-04');
        $this->crearSobre($viejo, $this->ana, ['diezmo' => 1000]);
        $reciente = $this->crearCulto('2026-10-18');
        $this->crearSobre($reciente, $this->ana, ['diezmo' => 1000]);

        $salida = $this->analizar();

        $this->assertStringContainsString('| 04/10/2026 | domingo | 1 | 16 días |', $salida);
        $this->assertStringNotContainsString('| 18/10/2026 | domingo | 1 |', $salida);
        $this->assertStringContainsString('| Cultos sin cerrar | 2 | 1 | Aviso |', $salida);
    }

    public function test_los_compromisos_con_dado_viejo_prometido_distinto_o_sin_fila_se_detectan(): void
    {
        TipoCambio::create(['fecha' => '2026-10-01', 'compra' => 500, 'venta' => 510, 'source' => 'manual']);
        $culto = $this->cultoCerrado('2026-08-09');
        $this->crearSobre($culto, $this->ana, ['misiones' => 8000]);
        $this->cultoCerrado('2026-09-13');
        Promesa::create(['persona_id' => $this->ana->id, 'categoria' => 'misiones', 'monto' => 10000, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);
        Compromiso::create(['persona_id' => $this->ana->id, 'categoria' => 'misiones', 'año' => 2026, 'mes' => 8, 'monto_prometido' => 9000, 'monto_dado' => 5000,
            'moneda_promesa' => 'CRC', 'monto_prometido_crc' => 9000, 'monto_dado_crc' => 5000]);

        $salida = $this->analizar();

        $this->assertStringContainsString('| Ana Mora | misiones | 08/2026 | ₡5,000.00 | ₡8,000.00 | +₡3,000.00 |', $salida);
        $this->assertStringContainsString('| Ana Mora | misiones | 08/2026 | ₡9,000.00 | ₡10,000.00 |', $salida);
        $this->assertStringContainsString('| Ana Mora | misiones | 1 | 9 |', $salida);
        $this->assertStringContainsString('| Compromisos desincronizados (2026) | 1 | 3 | ALERTA |', $salida);
    }

    public function test_dos_promesas_en_la_misma_categoria_se_comparan_contra_su_suma(): void
    {
        $this->cultoCerrado('2026-08-09');
        Promesa::create(['persona_id' => $this->ana->id, 'categoria' => 'misiones', 'monto' => 10000, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);
        Promesa::create(['persona_id' => $this->ana->id, 'categoria' => 'misiones', 'monto' => 5000, 'moneda' => 'CRC', 'frecuencia' => 'quincenal']);
        Compromiso::create(['persona_id' => $this->ana->id, 'categoria' => 'misiones', 'año' => 2026, 'mes' => 8, 'monto_prometido' => 10000,
            'moneda_promesa' => 'CRC', 'monto_prometido_crc' => 10000, 'monto_dado_crc' => 0]);

        $salida = $this->analizar();

        $this->assertStringContainsString('| Ana Mora | misiones | 08/2026 | ₡10,000.00 | ₡20,000.00 (2 promesas) |', $salida);
        $this->assertStringContainsString('- Categorías con más de una promesa: **1**', $salida);
    }

    public function test_el_mes_en_curso_sin_fila_de_compromiso_no_es_un_fallo(): void
    {
        $this->cultoCerrado('2026-10-11');
        Promesa::create(['persona_id' => $this->ana->id, 'categoria' => 'misiones', 'monto' => 10000, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);

        $salida = $this->analizar();

        $this->assertStringContainsString('| Compromisos desincronizados (2026) | 0 | 0 | OK |', $salida);
    }

    public function test_guarda_el_informe_en_la_ruta_y_se_puede_acotar_por_iglesia_y_fechas(): void
    {
        $otra = $this->crearOtraIglesia();
        $this->crearSobre($this->crearCulto('2026-10-04', 'domingo', $otra->id), null, ['diezmo' => 1000]);
        $this->crearSobre($this->crearCulto('2026-09-06'), $this->ana, ['diezmo' => 1000]);
        $this->crearSobre($this->crearCulto('2026-10-11'), $this->ana, ['diezmo' => 1000]);
        $ruta = storage_path('framework/testing/ingresos-'.uniqid().'.md');

        $this->artisan('ingresos:analizar', ['--iglesia' => $this->iglesia->slug, '--desde' => '2026-10-01', '--salida' => $ruta])->assertExitCode(0);

        $md = file_get_contents($ruta);
        @unlink($ruta);
        $this->assertStringContainsString('## '.$this->iglesia->nombre, $md);
        $this->assertStringNotContainsString('## '.$otra->nombre, $md);
        $this->assertStringContainsString('desde el 01/10/2026', $md);
        $this->assertStringContainsString('| Sobres contra sus detalles | 1 | 0 | OK |', $md);
    }

    public function test_rechaza_fechas_malas_y_una_iglesia_que_no_existe(): void
    {
        $this->artisan('ingresos:analizar', ['--desde' => '10/2026'])->expectsOutputToContain('AAAA-MM-DD')->assertExitCode(1);
        $this->artisan('ingresos:analizar', ['--desde' => '2026-10-10', '--hasta' => '2026-10-01'])->expectsOutputToContain('no puede ser posterior')->assertExitCode(1);
        $this->artisan('ingresos:analizar', ['--iglesia' => 'no-existe'])->expectsOutputToContain('No hay ninguna iglesia')->assertExitCode(1);
    }
}

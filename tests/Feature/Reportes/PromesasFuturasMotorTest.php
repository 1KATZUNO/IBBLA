<?php

namespace Tests\Feature\Reportes;

use App\Models\Compromiso;
use App\Models\CortePromesas;
use App\Models\Persona;
use App\Models\PromesaHistorial;
use App\Models\PromesaProgramada;
use App\Models\TenantCategory;
use App\Services\InformePersonaService;
use App\Services\Promesas\ActivadorCortes;
use App\Services\ReporteGeneralService;
use App\Services\Reportes\CandidatasSelector;
use App\Services\Reportes\CumplimientoPromesasService;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\MotorReportes;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Reportes\ResumenPromesasService;
use App\Services\Reportes\SincronizadorCompromisos;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioReportes;
use Tests\TestCase;

/**
 * Promesas futuras, Equipo M (docs/plan-oct-2026/06-PROMESAS-FUTURAS.md 3.3): el motor usa, mes por
 * mes, las promesas que regian ese mes, y la historia no se toca.
 *
 * Escenario: el de los reportes (Ana, Beto, Carla en 2026) mas un corte que entra en vigor el
 * 01/07/2026: Ana pasa a misiones ₡20.000 mensual + Jóvenes (categoria nueva, inactiva hasta el corte)
 * ₡1.000 mensual; Nora (nueva) construcción ₡3.000 semanal; Carla y Beto no vienen en el corte.
 */
class PromesasFuturasMotorTest extends TestCase
{
    use CreaEscenarioReportes;
    use RefreshDatabase;

    private Persona $nora;

    private TenantCategory $jovenes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEscenarioReportes();
        $this->nora = $this->crearPersona('Nora Nueva', '6000-1111');
        $this->crearSobre($this->crearCulto('2026-08-09'), $this->nora, ['construccion' => 12000]);
        $this->crearSobre($this->crearCulto('2026-08-16'), $this->ana, ['misiones' => 20000, 'jovenes' => 1000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ------------------------------------------------------------------ escenario

    private function crearCorte(string $inicia, ?array $promesas = null): CortePromesas
    {
        $this->jovenes ??= TenantCategory::create([
            'tenant_id' => $this->iglesia->id, 'nombre' => 'Jóvenes', 'slug' => 'jovenes', 'tipo' => 'ambos',
            'excluir_de_promesas' => false, 'es_ofrenda_suelta' => false, 'orden' => 99, 'activa' => false,
        ]);

        $corte = CortePromesas::create([
            'tenant_id' => $this->iglesia->id,
            'nombre' => 'Promesas '.substr($inicia, 0, 4),
            'inicia_el' => $inicia,
            'categorias_nuevas' => [$this->jovenes->id],
        ]);

        $promesas ??= [
            [$this->ana, 'misiones', 20000, 'mensual'],
            [$this->ana, 'jovenes', 1000, 'mensual'],
            [$this->nora, 'construccion', 3000, 'semanal'],
        ];
        foreach ($promesas as [$persona, $categoria, $monto, $frecuencia]) {
            PromesaProgramada::create([
                'corte_id' => $corte->id, 'persona_id' => $persona->id, 'categoria' => $categoria,
                'monto' => $monto, 'moneda' => 'CRC', 'frecuencia' => $frecuencia,
            ]);
        }

        return $corte;
    }

    /** Todos los numeros por persona y mes que dan el motor y los reportes que lo usan. */
    private function huella(PeriodoReporte $periodo, bool $conInformes = false): array
    {
        $personas = Persona::where('tenant_id', $this->iglesia->id)->orderBy('id')->get(['id', 'nombre', 'activo']);
        $resultado = app(MotorReportes::class)->calcular($periodo, $personas);

        $huella = [];
        foreach ($resultado->cuentas() as $cuenta) {
            $estado = $cuenta->estado();
            $fila = [
                'tiene_promesa' => $cuenta->tienePromesa(),
                'categorias' => $cuenta->categoriasConPromesa(),
                'promesas' => array_map(fn ($p) => $p->descripcion(), $cuenta->promesas()),
                'estado' => [$estado->leCorrespondeCentimos, $estado->haDadoCentimos, $estado->etiqueta()],
                'ultimo' => $cuenta->ultimoAporteACompromisos()?->toDateString(),
                'meses' => [],
            ];
            foreach ($resultado->periodo->meses() as $mes) {
                $fila['meses'][$mes->clave] = [
                    'esperado' => $cuenta->esperadoCentimos($mes->clave),
                    'del_mes' => $cuenta->esperadoDelMesCentimos($mes->clave),
                    'dado_promesas' => $cuenta->dadoEnPromesasCentimos($mes->clave),
                    'dado' => $cuenta->dadoCentimos($mes->clave),
                    'categorias' => $cuenta->categoriasConPromesa($mes->clave),
                ];
            }
            $huella['motor'][$cuenta->nombre] = $fila;
        }

        $cumplimiento = app(CumplimientoPromesasService::class)->generar($periodo, FiltroPersonas::todas());
        foreach ($cumplimiento->filas as $fila) {
            $huella['cumplimiento'][$fila->nombre] = [$fila->estado, $fila->leCorrespondeCentimos, $fila->haDadoCentimos, $fila->promesa, $fila->observacion, $fila->meses, $fila->ultimoAporte?->toDateString()];
        }
        $huella['cumplimiento']['sin_movimiento'] = $cumplimiento->sinMovimiento;

        $resumen = app(ResumenPromesasService::class)->generar($periodo);
        $huella['resumen'] = (fn () => [$this->categorias, $this->meses, $this->personas])->call($resumen);
        array_walk_recursive($huella['resumen'], function (&$valor) {
            if (is_object($valor)) {
                $valor = $valor->clave ?? get_class($valor);
            }
        });

        $huella['selector'] = collect(app(CandidatasSelector::class)->datos($periodo)['personas'])->mapWithKeys(fn ($p) => [$p['nombre'] => $p['promesa']])->all();
        $huella['filtro_con'] = FiltroPersonas::desde(['grupo' => FiltroPersonas::CON_PROMESA])->personas($periodo)->pluck('nombre')->all();
        $huella['filtro_sin'] = FiltroPersonas::desde(['grupo' => 'filtros', 'estado' => 'todos', 'promesa' => 'no'])->personas($periodo)->pluck('nombre')->all();

        if ($periodo->desde->year === $periodo->hasta->year && $periodo->desde->month === 1) {
            $huella['reporte_general'] = app(ReporteGeneralService::class)->generar($periodo->desde->year, $periodo->hasta->month);
        }
        if ($conInformes) {
            foreach ([$this->ana, $this->beto, $this->carla, $this->nora] as $persona) {
                $informe = app(InformePersonaService::class)->datos($persona);
                unset($informe['persona']);
                $huella['informe'][$persona->nombre] = json_decode(json_encode($informe), true);
            }
        }

        return $huella;
    }

    /** @return array<string, array<string, mixed>> */
    private function compromisos(?array $meses = null): array
    {
        return Compromiso::query()->orderBy('persona_id')->orderBy('categoria')->orderBy('mes')
            ->when($meses !== null, fn ($q) => $q->whereIn('mes', $meses))
            ->get()->mapWithKeys(fn ($c) => [$c->persona_id.'|'.$c->categoria.'|'.$c->año.'|'.$c->mes => collect($c->getAttributes())->except(['id', 'created_at', 'updated_at'])->all()])
            ->all();
    }

    /** Quita una categoria del Reporte General de una huella, comprobando que solo tenia ceros. */
    private function sinCategoriaEnCero(array $huella, string $slug): array
    {
        if (! isset($huella['reporte_general'])) {
            return $huella;
        }
        $general = &$huella['reporte_general'];
        $general['categoriasConPromesa'] = array_values(array_diff($general['categoriasConPromesa'], [$slug]));
        foreach ($general['totalesGenerales']['meses'] as &$total) {
            if (isset($total['categorias'][$slug])) {
                $this->assertEquals(['dado' => 0, 'esperado' => 0], $total['categorias'][$slug]);
                unset($total['categorias'][$slug]);
            }
        }
        unset($total);
        foreach ($general['reporteData'] as &$persona) {
            foreach ($persona['meses'] as &$mes) {
                if (isset($mes['categorias'][$slug])) {
                    $this->assertEquals(['dado' => 0, 'esperado' => 0], $mes['categorias'][$slug]);
                    unset($mes['categorias'][$slug]);
                }
            }
        }

        return $huella;
    }

    private function hoy(string $fecha): void
    {
        Carbon::setTestNow($fecha.' 10:00:00');
        $this->actingAs($this->admin);
    }

    // ------------------------------------------------------------------ 1. programado: nada cambia

    public function test_un_corte_programado_no_cambia_ningun_numero(): void
    {
        $this->hoy('2026-10-15');
        app(SincronizadorCompromisos::class)->sincronizar($this->iglesia, 2026);
        $periodos = [PeriodoReporte::alCorte('2026-10-15'), PeriodoReporte::deMeses(2026, 1, 6), PeriodoReporte::deMeses(2026, 7, 10)];
        $antes = array_map(fn ($p) => $this->huella($p, true), $periodos);
        $compromisosAntes = $this->compromisos();

        // Corte programado para enero 2027 con promesas y una categoria nueva inactiva.
        $this->crearCorte('2027-01-01');
        app(SincronizadorCompromisos::class)->sincronizar($this->iglesia, 2026);

        $this->assertSame($antes, array_map(fn ($p) => $this->huella($p, true), $periodos));
        $this->assertSame($compromisosAntes, $this->compromisos());
        $this->assertNotContains('jovenes', tenant_categories()->pluck('slug')->all());
        $this->assertSame(0, app(ActivadorCortes::class)->activarPendientes());

        // Las pantallas tambien (y el disparo perezoso no activa un corte con la fecha por llegar).
        $this->get(route('reportes.cumplimiento'))->assertOk();
        $this->assertTrue(CortePromesas::first()->estaProgramado());
    }

    // ------------------------------------------------------------------ 2. activado

    public function test_activar_no_toca_la_historia_y_cada_mes_usa_sus_promesas(): void
    {
        $this->hoy('2026-10-15');
        app(SincronizadorCompromisos::class)->sincronizar($this->iglesia, 2026);
        $antes = $this->huella(PeriodoReporte::deMeses(2026, 1, 6));
        $cruzaAntes = $this->huella(PeriodoReporte::deMeses(2026, 1, 10));
        $compromisosAntes = $this->compromisos(range(1, 6));
        $promesasAnaAntes = DB::table('promesas')->where('persona_id', $this->ana->id)->count();

        $corte = $this->crearCorte('2026-07-01');
        $this->assertTrue(app(ActivadorCortes::class)->activar($corte, $this->admin));

        // Periodo totalmente anterior al corte: persona por persona y mes por mes, lo mismo. Unica
        // diferencia admitida: el Reporte General lista las categorias de compromiso ACTIVAS, asi que
        // trae la columna de Jóvenes (ya activa), toda en cero, como cualquier categoria creada hoy.
        $despuesDeActivar = $this->huella(PeriodoReporte::deMeses(2026, 1, 6));
        $this->assertSame($antes, $this->sinCategoriaEnCero($despuesDeActivar, 'jovenes'));
        $this->assertSame($compromisosAntes, $this->compromisos(range(1, 6)));
        app(SincronizadorCompromisos::class)->sincronizar($this->iglesia, 2026);
        $this->assertSame($compromisosAntes, $this->compromisos(range(1, 6)), 'resincronizar el anio no cambia los meses anteriores al corte');

        // Periodo posterior: las promesas nuevas. Carla no venia: sin esperado ni "con promesa"; Nora es nueva.
        $despues = $this->huella(PeriodoReporte::deMeses(2026, 7, 10));
        $this->assertFalse($despues['motor']['Carla Solano']['tiene_promesa']);
        $this->assertSame(0, $despues['motor']['Carla Solano']['estado'][0]);
        $this->assertSame('SIN PROMESA', $despues['cumplimiento']['Carla Solano'][0]);
        $this->assertFalse($despues['selector']['Carla Solano']);
        $this->assertNotContains('Carla Solano', $despues['filtro_con']);
        $this->assertContains('Carla Solano', $despues['filtro_sin']);
        $this->assertTrue($despues['motor']['Nora Nueva']['tiene_promesa']);
        $this->assertTrue($despues['selector']['Nora Nueva']);
        $this->assertContains('Nora Nueva', $despues['filtro_con']);
        // Nora: ₡3.000 x domingos de julio (4), agosto (5) y septiembre (4); octubre en curso no se cobra.
        $this->assertSame((4 + 5 + 4) * 3000 * 100, $despues['motor']['Nora Nueva']['estado'][0]);
        $this->assertSame(1200000, $despues['motor']['Nora Nueva']['estado'][1]);
        // Ana: misiones ₡20.000 + jovenes ₡1.000 por mes (jul-sep); dio 20.000 + 1.000 el 16/08.
        $this->assertSame(3 * 21000 * 100, $despues['motor']['Ana Mora']['estado'][0]);
        $this->assertSame(2100000, $despues['motor']['Ana Mora']['estado'][1]);
        $this->assertSame(['misiones', 'jovenes'], array_keys($despues['motor']['Ana Mora']['promesas']));

        // Periodo que cruza el corte: cada mes con sus promesas.
        $cruza = $this->huella(PeriodoReporte::deMeses(2026, 1, 10));
        foreach (['Ana Mora', 'Beto Rojas', 'Carla Solano', 'Nora Nueva'] as $nombre) {
            foreach ($cruza['motor'][$nombre]['meses'] as $clave => $mes) {
                $esperado = $clave < '2026-07' ? $cruzaAntes['motor'][$nombre]['meses'][$clave] : $despues['motor'][$nombre]['meses'][$clave];
                $this->assertSame($esperado, $mes, "$nombre en $clave");
            }
            $suma = array_sum(array_column($cruza['motor'][$nombre]['meses'], 'esperado'));
            $this->assertSame($suma, $cruza['motor'][$nombre]['estado'][0], "$nombre: le corresponde = suma de cada mes");
            $sumaDado = array_sum(array_column($cruza['motor'][$nombre]['meses'], 'dado_promesas'));
            if ($cruza['motor'][$nombre]['tiene_promesa']) {
                $this->assertSame($sumaDado, $cruza['motor'][$nombre]['estado'][1], "$nombre: ha dado = suma de cada mes");
            }
        }
        // Carla tuvo promesa hasta junio: en el periodo que cruza figura "con promesa" y lo que le
        // correspondia es solo enero a junio; su promesa a mostrar (la de octubre) esta vacia.
        $this->assertTrue($cruza['motor']['Carla Solano']['tiene_promesa']);
        $eneroAJunio = array_filter($cruzaAntes['motor']['Carla Solano']['meses'], fn ($clave) => $clave < '2026-07', ARRAY_FILTER_USE_KEY);
        $this->assertSame(array_sum(array_column($eneroAJunio, 'esperado')), $cruza['motor']['Carla Solano']['estado'][0]);
        $this->assertGreaterThan(0, $cruza['motor']['Carla Solano']['estado'][0]);
        $this->assertSame([], $cruza['motor']['Carla Solano']['promesas']);
        $this->assertTrue($cruza['selector']['Carla Solano']);
        $this->assertContains('Carla Solano', $cruza['filtro_con']);

        // Tablas: promesas = juego nuevo; historial = el viejo con su vigencia; categoria activa.
        $this->assertSame(2, DB::table('promesas')->where('persona_id', $this->ana->id)->count());
        $this->assertSame(0, DB::table('promesas')->where('persona_id', $this->carla->id)->count());
        $this->assertSame(1, DB::table('promesas')->where('persona_id', $this->nora->id)->count());
        $historial = PromesaHistorial::where('persona_id', $this->ana->id)->get();
        $this->assertCount($promesasAnaAntes, $historial);
        foreach ($historial as $fila) {
            $this->assertNull($fila->vigente_desde);
            $this->assertSame('2026-06-30', $fila->vigente_hasta->toDateString());
            $this->assertNull($fila->corte_id);
            $this->assertSame($this->iglesia->id, (int) $fila->tenant_id);
        }
        $this->assertSame(4, PromesaHistorial::where('persona_id', $this->carla->id)->count());
        $this->assertSame(3, PromesaProgramada::where('corte_id', $corte->id)->count(), 'las programadas se conservan');
        $this->assertContains('jovenes', tenant_categories()->pluck('slug')->all());
        $corte->refresh();
        $this->assertFalse($corte->estaProgramado());
        $this->assertSame($this->admin->id, (int) $corte->activado_por);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Activar corte de promesas', 'tenant_id' => $this->iglesia->id]);

        // La otra iglesia no se toca.
        $this->assertSame(1, DB::table('promesas')->join('personas', 'personas.id', '=', 'promesas.persona_id')->where('personas.nombre', 'Zoe Ajena')->count());
    }

    public function test_compromisos_coherentes_antes_y_despues_del_corte(): void
    {
        $this->hoy('2026-10-15');
        app(SincronizadorCompromisos::class)->sincronizar($this->iglesia, 2026);
        $antes = $this->compromisos();
        $corte = $this->crearCorte('2026-07-01');
        app(ActivadorCortes::class)->activar($corte);

        $despues = $this->compromisos();
        // Meses anteriores al corte: identicos.
        $this->assertSame(
            array_filter($antes, fn ($c) => $c['mes'] < 7),
            array_filter($despues, fn ($c) => $c['mes'] < 7),
        );
        // Desde julio: solo las promesas del corte (Carla no; Nora y la categoria nueva si).
        $desdeJulio = array_filter($despues, fn ($c) => $c['mes'] >= 7);
        $this->assertNotEmpty($desdeJulio);
        foreach ($desdeJulio as $c) {
            $this->assertContains((int) $c['persona_id'], [$this->ana->id, $this->nora->id]);
        }
        $this->assertArrayHasKey($this->ana->id.'|jovenes|2026|8', $despues);
        $this->assertEquals(20000, $despues[$this->ana->id.'|misiones|2026|8']['monto_prometido']);
        $this->assertEquals(15000, $despues[$this->nora->id.'|construccion|2026|8']['monto_prometido']);
        $this->assertArrayNotHasKey($this->carla->id.'|campa|2026|8', $despues);
        $this->assertArrayHasKey($this->carla->id.'|campa|2026|3', $despues);

        // Una segunda sincronizacion no cambia nada.
        $r = app(SincronizadorCompromisos::class)->sincronizar($this->iglesia, 2026);
        $this->assertSame(0, $r['creados'] + $r['actualizados'] + $r['borrados']);
    }

    // ------------------------------------------------------------------ 3. idempotencia, vacio, encadenados

    public function test_activar_dos_veces_no_cambia_nada(): void
    {
        $this->hoy('2026-10-15');
        $corte = $this->crearCorte('2026-07-01');
        $activador = app(ActivadorCortes::class);
        $this->assertTrue($activador->activar($corte));
        $foto = [DB::table('promesas')->orderBy('id')->get()->toArray(), DB::table('promesas_historial')->orderBy('id')->get()->toArray(), $this->compromisos()];

        $this->assertFalse($activador->activar($corte));
        $this->assertFalse($activador->activar(CortePromesas::find($corte->id)));
        $this->assertSame(0, $activador->activarPendientes());
        $this->assertEquals($foto, [DB::table('promesas')->orderBy('id')->get()->toArray(), DB::table('promesas_historial')->orderBy('id')->get()->toArray(), $this->compromisos()]);
    }

    public function test_un_corte_vacio_no_se_activa_solo(): void
    {
        $this->hoy('2026-10-15');
        $vacio = $this->crearCorte('2026-07-01', []);

        $this->assertSame(0, app(ActivadorCortes::class)->activarPendientes());
        $this->artisan('promesas:activar-cortes')->assertSuccessful();
        $this->get(route('reportes.cumplimiento'))->assertOk();

        $this->assertTrue($vacio->fresh()->estaProgramado());
        $this->assertSame(3, DB::table('promesas')->where('persona_id', $this->ana->id)->count());
        $this->assertSame(0, PromesaHistorial::count());
    }

    public function test_dos_cortes_seguidos_encadenan_los_tramos(): void
    {
        Carbon::setTestNow('2027-03-01 10:00:00');
        $this->actingAs($this->admin);
        $antes2026 = $this->huella(PeriodoReporte::deMeses(2026, 1, 12));

        $c2027 = $this->crearCorte('2027-01-01');
        $c2028 = $this->crearCorte('2028-01-01', [[$this->carla, 'micro', 500, 'mensual']]);
        $this->assertSame(1, app(ActivadorCortes::class)->activarPendientes(), 'el de 2028 todavia no');
        $huella2027 = $this->huella(PeriodoReporte::deMeses(2027, 1, 2));

        Carbon::setTestNow('2028-03-15 10:00:00');
        $this->assertSame(1, app(ActivadorCortes::class)->activarPendientes());

        // Tramos: los de siempre hasta el 31/12/2026, los del corte 2027 durante 2027, y hoy las de 2028.
        $this->assertSame(0, PromesaHistorial::whereNull('vigente_desde')->where('vigente_hasta', '!=', '2026-12-31')->count());
        $de2027 = PromesaHistorial::where('corte_id', $c2027->id)->get();
        $this->assertCount(3, $de2027);
        foreach ($de2027 as $fila) {
            $this->assertSame('2027-01-01', $fila->vigente_desde->toDateString());
            $this->assertSame('2027-12-31', $fila->vigente_hasta->toDateString());
        }
        $this->assertSame([$this->carla->id], DB::table('promesas')->join('personas', 'personas.id', '=', 'promesas.persona_id')->where('personas.tenant_id', $this->iglesia->id)->pluck('persona_id')->map(fn ($id) => (int) $id)->all());
        $this->assertFalse($c2028->fresh()->estaProgramado());

        // Cada anio con su juego, aun despues del segundo corte.
        $this->assertSame($antes2026['motor'], $this->huella(PeriodoReporte::deMeses(2026, 1, 12))['motor']);
        $this->assertSame($huella2027['motor'], $this->huella(PeriodoReporte::deMeses(2027, 1, 2))['motor']);
        $cruza = $this->huella(PeriodoReporte::deMeses(2026, 12, 2, 2028));
        $this->assertSame($antes2026['motor']['Ana Mora']['meses']['2026-12']['del_mes'], $cruza['motor']['Ana Mora']['meses']['2026-12']['del_mes']);
        $this->assertSame(2100000, $cruza['motor']['Ana Mora']['meses']['2027-06']['del_mes']);
        $this->assertSame(0, $cruza['motor']['Ana Mora']['meses']['2028-01']['del_mes']);
        $this->assertSame(50000, $cruza['motor']['Carla Solano']['meses']['2028-01']['del_mes']);
        $this->assertSame(0, $cruza['motor']['Carla Solano']['meses']['2027-05']['del_mes']);
        $this->assertSame(['micro'], array_keys($cruza['motor']['Carla Solano']['promesas']));
    }

    public function test_un_corte_anterior_a_uno_ya_activado_no_se_activa(): void
    {
        $this->hoy('2026-10-15');
        $septiembre = $this->crearCorte('2026-09-01');
        app(ActivadorCortes::class)->activar($septiembre);
        $julio = $this->crearCorte('2026-07-01');

        $this->assertFalse(app(ActivadorCortes::class)->activar($julio));
        $this->assertTrue($julio->fresh()->estaProgramado());
    }

    // ------------------------------------------------------------------ 4. disparadores y dos iglesias

    public function test_comando_activa_los_cortes_de_todas_las_iglesias_y_esta_en_el_planificador(): void
    {
        Carbon::setTestNow('2026-07-01 00:05:00');
        $this->crearCorte('2026-07-01');
        $otra = \App\Models\Tenant::where('slug', 'otra-iglesia')->firstOrFail();
        $this->crearCategoriasPorDefecto($otra->id);
        $zoe = Persona::withoutGlobalScopes()->where('nombre', 'Zoe Ajena')->firstOrFail();
        $corteOtra = CortePromesas::create(['tenant_id' => $otra->id, 'nombre' => 'Otra 2026', 'inicia_el' => '2026-07-01']);
        PromesaProgramada::create(['corte_id' => $corteOtra->id, 'persona_id' => $zoe->id, 'categoria' => 'misiones', 'monto' => 2500, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);
        app()->forgetInstance('current_tenant');

        $this->artisan('promesas:activar-cortes')->expectsOutputToContain('Se activaron 2 cortes')->assertSuccessful();

        $this->assertSame(0, CortePromesas::withoutGlobalScopes()->whereNull('activado_at')->count());
        // Cada iglesia con lo suyo: el historial de Zoe es de su iglesia y el de Ana de la nuestra.
        $this->assertSame([$otra->id], PromesaHistorial::withoutGlobalScopes()->where('persona_id', $zoe->id)->pluck('tenant_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(0, PromesaHistorial::withoutGlobalScopes()->where('persona_id', $this->ana->id)->where('tenant_id', $otra->id)->count());
        $this->assertEquals(2500, DB::table('promesas')->where('persona_id', $zoe->id)->value('monto'));
        $this->assertSame(2, DB::table('promesas')->where('persona_id', $this->ana->id)->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Activar corte de promesas')->where('tenant_id', $otra->id)->count());

        // Una iglesia no ve la frontera de la otra: un reporte de Zoe de enero a junio sigue igual.
        app()->instance('current_tenant', $otra);
        $cuenta = app(MotorReportes::class)->calcular(PeriodoReporte::deMeses(2026, 1, 6), [$zoe])->cuenta($zoe->id);
        $this->assertSame(100000, $cuenta->esperadoDelMesCentimos('2026-03'));

        $evento = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'promesas:activar-cortes'));
        $this->assertNotNull($evento, 'promesas:activar-cortes esta en el planificador');
        $this->assertSame('*/10 * * * *', $evento->expression);
    }

    public function test_disparo_perezoso_en_peticiones_web_con_candado_por_minuto(): void
    {
        Carbon::setTestNow('2026-07-01 08:00:00');
        $corte = $this->crearCorte('2026-07-01');

        // Sin sesion no hace nada.
        $this->get('/login');
        $this->assertTrue($corte->fresh()->estaProgramado());

        $this->actingAs($this->admin)->get(route('reportes.cumplimiento'))->assertOk();
        $this->assertFalse($corte->fresh()->estaProgramado());
        $this->assertSame(2, DB::table('promesas')->where('persona_id', $this->ana->id)->count());

        // Dentro del mismo minuto no vuelve a revisar (candado en cache)...
        $agosto = $this->crearCorte('2026-08-01');
        Carbon::setTestNow('2026-08-01 08:00:30');
        \Illuminate\Support\Facades\Cache::put(\App\Http\Middleware\ActivarCortesPromesas::claveCandado($this->iglesia->id), 'x', 60);
        $this->get(route('reportes.cumplimiento'))->assertOk();
        $this->assertTrue($agosto->fresh()->estaProgramado());

        // ...y cuando vence el candado, si.
        \Illuminate\Support\Facades\Cache::forget(\App\Http\Middleware\ActivarCortesPromesas::claveCandado($this->iglesia->id));
        $this->get(route('reportes.cumplimiento'))->assertOk();
        $this->assertFalse($agosto->fresh()->estaProgramado());
    }

    public function test_disparo_perezoso_sin_nada_que_activar_hace_una_sola_consulta(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->crearCorte('2027-01-01');
        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            if (str_contains($q->sql, 'cortes_promesas')) {
                $consultas[] = $q->sql;
            }
        });

        $this->actingAs($this->admin)->get(route('principal'));

        $this->assertCount(1, $consultas, implode("\n", $consultas));
    }

    // ------------------------------------------------------------------ ibbla:verificar

    public function test_verificar_avisa_de_un_corte_con_la_fecha_cumplida_sin_activar(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        \Illuminate\Support\Facades\Cache::forever(\App\Console\Commands\VerificarIbbla::CLAVE_LATIDO, now()->toIso8601String());
        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('Cortes de promesas: ninguno con la fecha cumplida sin activar')
            ->assertExitCode(0);

        $this->crearCorte('2026-07-01');
        $this->crearCorte('2026-09-01', []);
        $this->crearCorte('2027-01-01');
        $this->artisan('ibbla:verificar', ['--sin-reportes' => true])
            ->expectsOutputToContain('con la fecha cumplida y sin activar: «Promesas 2026» (iglesia '.$this->iglesia->id.', desde 01/07/2026)')
            ->expectsOutputToContain('con la fecha cumplida pero sin promesas (no se activan solos)')
            ->doesntExpectOutputToContain('desde 01/01/2027')
            ->assertExitCode(0);
    }
}

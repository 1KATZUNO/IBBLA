<?php

namespace Tests\Feature;

use App\Models\Culto;
use App\Models\Persona;
use App\Models\Promesa;
use App\Models\Sobre;
use App\Models\SobreDetalle;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReporteGeneralService;
use Carbon\Carbon;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Reporte General" de Gestion de Personas (dado vs esperado por mes).
 *
 * Usa MySQL (las migraciones no corren en SQLite):
 *   DB_CONNECTION=mysql DB_DATABASE=ibbla_x_test php vendor/bin/phpunit --filter ReporteGeneralTest
 */
class ReporteGeneralTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminUserSeeder::class);
        $this->admin = User::where('email', 'admin@ibbsc.com')->firstOrFail();

        // Reporte de enero a marzo de 2026. Domingos: enero 4, febrero 4, marzo 5.
        Carbon::setTestNow(Carbon::create(2026, 3, 20, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_calcula_dado_y_esperado_por_persona_y_mes(): void
    {
        $this->sembrarEscenario();
        $this->actingAs($this->admin);

        $reporte = app(ReporteGeneralService::class)->generar(2026, 3);

        $this->assertSame(['misiones', 'seminario', 'construccion', 'campa', 'prestamo', 'micro'], $reporte['categoriasConPromesa']);

        // Solo activas con algo dado o esperado, por nombre. Fuera: Carla (inactiva),
        // Diego (sin nada) y la persona del otro tenant.
        $this->assertSame(['Ana Mora', 'Beto Rojas', 'Elena Vargas'], array_column($reporte['reporteData'], 'nombre'));
        [$ana, $beto, $elena] = $reporte['reporteData'];

        // Ana: misiones semanal 1.000 (x domingos), construccion mensual 5.000 y campa
        // mensual (dos promesas: vale la ultima, 3.000). El diezmo no entra al reporte.
        $this->assertMes($ana['meses'][0], 'Enero', ['misiones' => 1500, 'construccion' => 5000], ['misiones' => 4000, 'construccion' => 5000, 'campa' => 3000]);
        $this->assertMes($ana['meses'][1], 'Febrero', ['misiones' => 4000], ['misiones' => 4000, 'construccion' => 5000, 'campa' => 3000]);
        $this->assertMes($ana['meses'][2], 'Marzo', ['construccion' => 2500.5, 'campa' => 3000], ['misiones' => 5000, 'construccion' => 5000, 'campa' => 3000]);
        $this->assertEquals(['dado' => 16000.5, 'esperado' => 37000, 'diferencia' => -20999.5], $ana['totales']);

        // Beto: sin promesa; su sobre del anio anterior no cuenta.
        $this->assertEquals(['dado' => 2000, 'esperado' => 0, 'diferencia' => 2000], $beto['totales']);
        $this->assertEquals(2000, $beto['meses'][1]['categorias']['seminario']['dado']);

        // Elena: promesa quincenal (x2 por mes) y nada dado.
        $this->assertEquals(['dado' => 0, 'esperado' => 6000, 'diferencia' => -6000], $elena['totales']);
        $this->assertEquals(2000, $elena['meses'][0]['categorias']['seminario']['esperado']);

        // Totales generales (el sobre anonimo no se atribuye a nadie).
        $totales = $reporte['totalesGenerales'];
        $this->assertEquals(18000.5, $totales['total_dado']);
        $this->assertEquals(43000, $totales['total_esperado']);
        $this->assertEquals(-24999.5, $totales['total_diferencia']);
        $this->assertEquals(6500, $totales['meses'][1]['total_dado']);
        $this->assertEquals(14000, $totales['meses'][1]['total_esperado']);
        $this->assertEquals(6000, $totales['meses'][2]['total_dado']);
        $this->assertEquals(5500.5, $totales['meses'][3]['total_dado']);
        $this->assertEquals(15000, $totales['meses'][3]['total_esperado']);
        $this->assertEquals(2000, $totales['meses'][2]['categorias']['seminario']['dado']);
    }

    public function test_el_endpoint_descarga_el_pdf_con_los_totales(): void
    {
        $this->sembrarEscenario();

        $respuesta = $this->actingAs($this->admin)
            ->get(route('personas.reporte-general', ['accion' => 'descargar']));

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $respuesta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('reporte-general-2026-03.pdf', $respuesta->headers->get('Content-Disposition'));

        $pdf = $respuesta->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);

        $texto = $this->textoDelPdf($pdf);
        $this->assertStringContainsString('Periodo: Enero - Marzo 2026', $texto);
        $this->assertStringContainsString('TOTAL ANA MORA', $texto);
        // number_format(x, 0) como la vista anterior: .5 se redondea alejandose de cero.
        $this->assertStringContainsString('16,001', $texto);       // total dado de Ana (16.000,5)
        $this->assertStringContainsString('-21,000', $texto);      // su diferencia (-20.999,5)
        $this->assertStringContainsString('+2,000', $texto);       // diferencia de Beto
        $this->assertStringContainsString('-25,000', $texto);      // diferencia del gran total (-24.999,5)
        $this->assertStringContainsString('TOTALES GENERALES - TODAS LAS PERSONAS', $texto);
        $this->assertStringNotContainsString('Carla', $texto);
    }

    public function test_ver_muestra_el_pdf_en_linea(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->get(route('personas.reporte-general', ['accion' => 'ver']));

        // Sin datos tambien genera el PDF (con el aviso de que no hay compromisos).
        $respuesta->assertOk();
        $this->assertStringContainsString('inline', $respuesta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('No hay datos de compromisos', $this->textoDelPdf($respuesta->getContent()));
    }

    public function test_exige_la_accion(): void
    {
        $this->actingAs($this->admin)
            ->get(route('personas.reporte-general'))
            ->assertSessionHasErrors('accion');
    }

    // ------------------------------------------------------------------

    private function sembrarEscenario(): void
    {
        $tenantId = $this->admin->tenant_id;

        $domingoEnero = $this->culto($tenantId, '2026-01-11');
        $domingoFebrero = $this->culto($tenantId, '2026-02-08');
        $domingoFebrero2 = $this->culto($tenantId, '2026-02-15');
        $miercolesMarzo = $this->culto($tenantId, '2026-03-04', 'miércoles');
        $diciembreAnterior = $this->culto($tenantId, '2025-12-28');

        $ana = $this->persona($tenantId, 'Ana Mora');
        $this->promesa($ana, 'misiones', 1000, 'semanal');
        $this->promesa($ana, 'construccion', 5000, 'mensual');
        $this->promesa($ana, 'campa', 2000, 'mensual');
        $this->promesa($ana, 'campa', 3000, 'mensual');
        $this->sobre($domingoEnero, $ana, ['misiones' => 1500, 'diezmo' => 10000, 'construccion' => 5000]);
        $this->sobre($domingoFebrero, $ana, ['misiones' => 4000]);
        $this->sobre($miercolesMarzo, $ana, ['construccion' => 2500.50, 'campa' => 3000]);

        $beto = $this->persona($tenantId, 'Beto Rojas');
        $this->sobre($domingoFebrero2, $beto, ['seminario' => 2000]);
        $this->sobre($diciembreAnterior, $beto, ['misiones' => 9999]);

        $carla = $this->persona($tenantId, 'Carla Inactiva', false);
        $this->promesa($carla, 'misiones', 5000, 'mensual');
        $this->sobre($domingoEnero, $carla, ['misiones' => 5000]);

        $this->persona($tenantId, 'Diego Sin Nada');

        $elena = $this->persona($tenantId, 'Elena Vargas');
        $this->promesa($elena, 'seminario', 1000, 'quincenal');

        // Sobre anonimo
        $this->sobre($domingoEnero, null, ['misiones' => 7000]);

        // Otra iglesia: no debe aparecer.
        $otro = Tenant::create(['nombre' => 'Otra Iglesia', 'siglas' => 'OTRA', 'slug' => 'otra', 'activo' => true]);
        $cultoOtro = $this->culto($otro->id, '2026-01-11');
        $ajena = $this->persona($otro->id, 'Zoe Ajena');
        $this->promesa($ajena, 'misiones', 1000, 'mensual');
        $this->sobre($cultoOtro, $ajena, ['misiones' => 1000]);
    }

    private function assertMes(array $mes, string $nombre, array $dado, array $esperado): void
    {
        $this->assertSame($nombre, $mes['nombre']);
        foreach ($mes['categorias'] as $cat => $valores) {
            $this->assertEquals($dado[$cat] ?? 0, $valores['dado'], "$nombre: dado de $cat");
            $this->assertEquals($esperado[$cat] ?? 0, $valores['esperado'], "$nombre: esperado de $cat");
        }
        $this->assertEquals(array_sum($dado), $mes['total_dado']);
        $this->assertEquals(array_sum($esperado), $mes['total_esperado']);
        $this->assertEquals(array_sum($dado) - array_sum($esperado), $mes['diferencia']);
    }

    private function culto(int $tenantId, string $fecha, string $tipo = 'domingo'): Culto
    {
        return Culto::create(['tenant_id' => $tenantId, 'fecha' => $fecha, 'hora' => '10:00', 'tipo_culto' => $tipo]);
    }

    private function persona(int $tenantId, string $nombre, bool $activo = true): Persona
    {
        return Persona::create(['tenant_id' => $tenantId, 'nombre' => $nombre, 'activo' => $activo]);
    }

    private function promesa(Persona $persona, string $categoria, float $monto, string $frecuencia): void
    {
        Promesa::create(['persona_id' => $persona->id, 'categoria' => $categoria, 'monto' => $monto, 'frecuencia' => $frecuencia]);
    }

    private function sobre(Culto $culto, ?Persona $persona, array $detalles): void
    {
        $sobre = Sobre::create([
            'culto_id' => $culto->id,
            'persona_id' => $persona?->id,
            'metodo_pago' => 'efectivo',
            'total_declarado' => array_sum($detalles),
        ]);
        foreach ($detalles as $categoria => $monto) {
            SobreDetalle::create(['sobre_id' => $sobre->id, 'categoria' => $categoria, 'monto' => $monto]);
        }
    }

    /**
     * Texto visible del PDF: descomprime los streams y decodifica los TJ
     * (CPDF escribe el texto en UTF-16BE con ( ) \ escapados).
     */
    private function textoDelPdf(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
        $texto = '';
        foreach ($streams[1] as $stream) {
            $contenido = @gzuncompress($stream);
            if ($contenido === false || ! str_contains($contenido, 'TJ')) {
                continue;
            }
            preg_match_all('/\[\((.*?)(?<!\\\\)\)\] TJ/s', $contenido, $partes);
            foreach ($partes[1] as $parte) {
                $crudo = preg_replace_callback('/\\\\([nrtbf()\\\\])/', function ($m) {
                    return ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f"][$m[1]] ?? $m[1];
                }, $parte);
                $texto .= mb_convert_encoding($crudo, 'UTF-8', 'UTF-16BE')."\n";
            }
        }

        return $texto;
    }
}

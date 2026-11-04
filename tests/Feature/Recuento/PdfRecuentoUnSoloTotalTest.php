<?php

namespace Tests\Feature\Recuento;

use App\Models\Sobre;
use App\Models\SobreDetalle;
use App\Models\Tenant;
use App\Models\TipoCambio;
use App\Support\ResumenCulto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * QA-026: el PDF de recuento tenia dos totales (la tabla sumaba dolares como colones). Ahora todo
 * va en colones convertidos (tipo de cambio del sobre; si no tiene, el de la fecha del culto; si no,
 * el de hoy), con el USD entre parentesis, y cabecera, tabla y pantalla dan el mismo total.
 */
class PdfRecuentoUnSoloTotalTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
    }

    private function sobreUsd($culto, array $detalles, ?float $tc): Sobre
    {
        $sobre = Sobre::create([
            'culto_id' => $culto->id,
            'persona_id' => null,
            'metodo_pago' => 'efectivo',
            'total_declarado' => array_sum($detalles),
            'moneda' => 'USD',
            'tipo_cambio_venta' => $tc,
        ]);
        foreach ($detalles as $categoria => $monto) {
            SobreDetalle::create(['sobre_id' => $sobre->id, 'categoria' => $categoria, 'monto' => $monto]);
        }

        return $sobre;
    }

    public function test_un_solo_total_con_sobres_en_dolares(): void
    {
        TipoCambio::create(['fecha' => '2026-10-11', 'compra' => 500, 'venta' => 510, 'source' => 'test']);
        $culto = $this->crearCulto($this->iglesia, '2026-10-11');
        $this->crearSobreEn($culto, null, ['diezmo' => 10000]);                 // ₡10.000
        $this->sobreUsd($culto, ['diezmo' => 32, 'misiones' => 8], 500.0);       // $40 x 500 = ₡20.000
        $this->sobreUsd($culto, ['diezmo' => 10], null);                         // $10 x 510 (fecha del culto) = ₡5.100

        $categorias = tenant_categories(['es_ofrenda_suelta' => false]);
        $culto->load(['sobres.detalles']);
        $resumen = ResumenCulto::calcular($culto->sobres, collect(), collect(), $categorias);

        $this->assertEquals(35100, $resumen->total());
        $this->assertEquals(10000 + 16000 + 5100, $resumen->porCategoria['diezmo']);
        $this->assertEquals(4000, $resumen->porCategoria['misiones']);
        $this->assertEquals(50, $resumen->totalUsdSobres());
        $this->assertEquals($resumen->total(), array_sum($resumen->porCategoria));

        $html = view('pdfs.recuento-individual', [
            'culto' => $culto->load(['sobres.persona', 'ofrendasSueltas', 'egresos']),
            'categories' => $categorias,
            'resumen' => $resumen,
            'transferenciasOnly' => false,
        ])->render();

        // Cabecera y fila TOTALES con el mismo numero; el USD solo entre parentesis
        $this->assertSame(3, substr_count($html, '35,100.00'), 'Total General, TOTALES y Total Efectivo');
        $this->assertStringContainsString('(USD $40.00 × 500.00)', $html);
        $this->assertStringContainsString('(USD $10.00 × 510.00)', $html);
        $this->assertStringContainsString('incluye USD $50.00', $html);
    }
}

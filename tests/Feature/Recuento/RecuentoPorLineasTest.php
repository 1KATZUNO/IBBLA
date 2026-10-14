<?php

namespace Tests\Feature\Recuento;

use App\Models\Culto;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * HU-REC-1: Recuento muestra los cultos por lineas (sin los cuadros de "Cultos cerrados"),
 * con buscador por fecha y filtros por mes, tipo y estado; cada linea abre la pantalla del
 * culto, que solo muestra ese culto.
 */
class RecuentoPorLineasTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    private User $tesorero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
        $this->tesorero = $this->crearUsuario($this->iglesia, 'tesorero');
    }

    public function test_la_lista_muestra_una_linea_por_culto_y_ya_no_los_cuadros_de_cerrados(): void
    {
        $abierto = $this->crearCulto($this->iglesia, '2026-10-18');
        $cerrado = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true, $this->tesorero);
        $this->crearSobreEn($cerrado);
        $this->crearSobreEn($cerrado);

        $respuesta = $this->actingAs($this->tesorero)->get('/recuento')->assertOk();

        $respuesta->assertSee('18/10/2026')
            ->assertSee('11/10/2026')
            ->assertSee(route('recuento.culto', $abierto), false)
            ->assertSee(route('recuento.culto', $cerrado), false)
            ->assertSee('Abierto')
            ->assertSee('Cerrado')
            ->assertDontSee('Cultos Cerrados (Archivo)');
        // Una fila por culto con su enlace
        $this->assertSame(2, substr_count($respuesta->getContent(), 'data-href='));
    }

    public function test_buscar_por_fecha_deja_solo_los_cultos_de_ese_dia(): void
    {
        $buscado = $this->crearCulto($this->iglesia, '2026-10-11');
        $otro = $this->crearCulto($this->iglesia, '2026-10-04');

        $html = $this->actingAs($this->tesorero)->get('/recuento?fecha=2026-10-11')->assertOk()->getContent();

        $this->assertStringContainsString(route('recuento.culto', $buscado), $html);
        $this->assertStringNotContainsString(route('recuento.culto', $otro), $html);
    }

    public function test_filtra_por_mes_tipo_y_estado(): void
    {
        $septiembre = $this->crearCulto($this->iglesia, '2026-09-27');
        $miercoles = $this->crearCulto($this->iglesia, '2026-10-07', 'miercoles');
        $domingoCerrado = $this->crearCulto($this->iglesia, '2026-10-11', 'domingo', true);

        $porMes = $this->actingAs($this->tesorero)->get('/recuento?mes=2026-09')->getContent();
        $this->assertStringContainsString(route('recuento.culto', $septiembre), $porMes);
        $this->assertStringNotContainsString(route('recuento.culto', $miercoles), $porMes);

        $porTipo = $this->actingAs($this->tesorero)->get('/recuento?tipo=miercoles')->getContent();
        $this->assertStringContainsString(route('recuento.culto', $miercoles), $porTipo);
        $this->assertStringNotContainsString(route('recuento.culto', $domingoCerrado), $porTipo);

        $cerrados = $this->actingAs($this->tesorero)->get('/recuento?estado=cerrados')->getContent();
        $this->assertStringContainsString(route('recuento.culto', $domingoCerrado), $cerrados);
        $this->assertStringNotContainsString(route('recuento.culto', $septiembre), $cerrados);
    }

    public function test_filtros_con_valores_raros_no_dan_error(): void
    {
        $this->crearCulto($this->iglesia, '2026-10-11');

        $this->actingAs($this->tesorero)
            ->get('/recuento?fecha=no-es-fecha&mes=2026-13&tipo=inventado&estado=x')
            ->assertOk()
            ->assertSee('11/10/2026');
    }

    public function test_el_enlace_viejo_con_culto_id_abre_la_pantalla_del_culto(): void
    {
        $culto = $this->crearCulto($this->iglesia, '2026-10-11');

        $this->actingAs($this->tesorero)->get('/recuento?culto_id='.$culto->id)
            ->assertRedirect(route('recuento.culto', $culto));
        $this->actingAs($this->tesorero)->get('/recuento/culto-cerrado/'.$culto->id)
            ->assertRedirect(route('recuento.culto', $culto));
    }

    public function test_la_pantalla_del_culto_solo_muestra_ese_culto(): void
    {
        $culto = $this->crearCulto($this->iglesia, '2026-10-11');
        $otro = $this->crearCulto($this->iglesia, '2026-10-04', 'domingo', true);
        $persona = $this->crearPersonaDe($this->iglesia, 'Marta Solano');
        $this->crearSobreEn($culto, $persona, ['diezmo' => 25000, 'misiones' => 5000]);
        $this->crearSobreEn($otro, $this->crearPersonaDe($this->iglesia, 'Otro Hermano'));

        $this->actingAs($this->tesorero)->get(route('recuento.culto', $culto))
            ->assertOk()
            ->assertSee('11/10/2026')
            ->assertSee('Marta Solano')
            ->assertSee('₡30,000.00')
            ->assertSee('Todos los cultos')
            ->assertDontSee('04/10/2026')
            ->assertDontSee('Otro Hermano')
            ->assertDontSee('Cultos Cerrados (Archivo)');
    }

    public function test_un_culto_de_otra_iglesia_no_se_puede_abrir(): void
    {
        $otraIglesia = $this->crearIglesia('otra', 'OTRA');
        $ajeno = $this->crearCulto($otraIglesia, '2026-10-11');

        $this->actingAs($this->tesorero)->get(route('recuento.culto', $ajeno))->assertNotFound();
    }

    public function test_al_guardar_un_sobre_se_vuelve_a_la_pantalla_del_culto(): void
    {
        $culto = $this->crearCulto($this->iglesia, '2026-10-18');

        $this->actingAs($this->tesorero)->post(route('recuento.store'), [
            'culto_id' => $culto->id,
            'metodo_pago' => 'efectivo',
            'detalles' => [['categoria' => 'diezmo', 'monto' => 15000]],
        ])->assertRedirect(route('recuento.culto', $culto));

        $this->assertSame(1, $culto->sobres()->count());
    }

    public function test_el_detalle_de_gestionar_cultos_lleva_a_la_pantalla_del_culto(): void
    {
        $admin = $this->crearUsuario($this->iglesia, 'admin');
        $culto = $this->crearCulto($this->iglesia, '2026-10-11');

        $this->actingAs($admin)->get(route('cultos.show', $culto))
            ->assertRedirect(route('recuento.culto', $culto));
    }

    public function test_el_asistente_no_entra_a_recuento(): void
    {
        $asistente = $this->crearUsuario($this->iglesia, 'asistente');
        $culto = $this->crearCulto($this->iglesia, '2026-10-11');

        $this->actingAs($asistente)->get('/recuento')->assertForbidden();
        $this->actingAs($asistente)->get(route('recuento.culto', $culto))->assertForbidden();
    }

    public function test_lista_vacia_explica_que_hacer(): void
    {
        $this->actingAs($this->tesorero)->get('/recuento')
            ->assertOk()
            ->assertSee('Todavía no hay cultos');

        $this->crearCulto($this->iglesia, '2026-10-11');
        $this->actingAs($this->tesorero)->get('/recuento?fecha=2026-01-01')
            ->assertOk()
            ->assertSee('No hay cultos con estos filtros');

        $this->assertSame(1, Culto::withoutGlobalScopes()->count());
    }
}

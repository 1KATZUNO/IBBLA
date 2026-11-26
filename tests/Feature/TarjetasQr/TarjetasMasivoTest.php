<?php

namespace Tests\Feature\TarjetasQr;

use App\Models\ClaseAsistencia;
use App\Models\CortePromesas;
use App\Models\Persona;
use App\Models\Promesa;
use App\Models\PromesaProgramada;
use App\Services\Tarjetas\TarjetasAcceso;
use App\Services\Tarjetas\TarjetasPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioTarjetas;
use Tests\TestCase;

/**
 * Descarga masiva de tarjetas: quienes entran con cada filtro, el orden (clase y nombre), 8 por hoja carta y
 * el tiempo con el volumen del servidor real (255 personas) por debajo del corte de Hostinger (10 s).
 */
class TarjetasMasivoTest extends TestCase
{
    use CreaEscenarioTarjetas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarIglesia();
        $this->actingAs($this->admin);
    }

    private function clase(string $nombre, int $orden): ClaseAsistencia
    {
        return ClaseAsistencia::create(['nombre' => $nombre, 'slug' => \Illuminate\Support\Str::slug($nombre), 'orden' => $orden, 'activa' => true, 'tenant_id' => $this->iglesia->id]);
    }

    private function promesa(Persona $persona): void
    {
        Promesa::create(['persona_id' => $persona->id, 'categoria' => 'diezmo', 'monto' => 5000, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);
    }

    private function programada(Persona $persona, bool $activado): void
    {
        $corte = CortePromesas::firstOrCreate(
            ['tenant_id' => $this->iglesia->id, 'inicia_el' => $activado ? '2026-01-01' : '2027-01-01'],
            ['nombre' => $activado ? 'Promesas 2026' : 'Promesas 2027', 'activado_at' => $activado ? now() : null],
        );
        PromesaProgramada::create(['corte_id' => $corte->id, 'persona_id' => $persona->id, 'categoria' => 'misiones', 'monto' => 2000, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);
    }

    private function nombres(string $filtro, ?int $clase = null, ?string $claseNombre = null): array
    {
        return app(TarjetasAcceso::class)->tarjetas($filtro, $clase, $claseNombre)->pluck('nombre')->all();
    }

    public function test_por_defecto_trae_a_quien_tiene_promesa_vigente_o_programada_en_un_corte_futuro(): void
    {
        $this->promesa($this->persona('Vigente'));
        $this->programada($this->persona('Programada 2027'), false);
        $this->programada($this->persona('Solo de un corte ya activado'), true);
        $this->persona('Sin promesa');
        $this->promesa($this->persona('Inactiva con promesa', ['activo' => false]));

        $this->assertEqualsCanonicalizing(['Vigente', 'Programada 2027', 'Inactiva con promesa'], $this->nombres(TarjetasAcceso::FILTRO_PROMESA));

        // A las que entran se les genero su QR; a las demas no
        $this->assertNotNull(Persona::where('nombre', 'Programada 2027')->value('token_acceso'));
        $this->assertNull(Persona::where('nombre', 'Sin promesa')->value('token_acceso'));

        $this->assertEqualsCanonicalizing(['Vigente', 'Programada 2027', 'Solo de un corte ya activado', 'Sin promesa'], $this->nombres(TarjetasAcceso::FILTRO_ACTIVAS));
    }

    public function test_ordena_por_clase_y_nombre_y_filtra_por_clase(): void
    {
        $damas = $this->clase('Damas', 1);
        $jovenes = $this->clase('Jóvenes', 2);
        $sinClase = $this->persona('Zoila Sin Clase');
        $beatriz = $this->persona('Beatriz');
        $ana = $this->persona('Ángela');
        $carlos = $this->persona('Carlos');
        $beatriz->clasesAsistencia()->attach($damas->id, ['es_maestro' => false]);
        $ana->clasesAsistencia()->attach($damas->id, ['es_maestro' => false]);
        // Carlos esta en Damas como visita y en Jovenes como miembro: su clase principal es Jovenes
        $carlos->clasesAsistencia()->attach($damas->id, ['es_maestro' => false, 'tipo' => 'visita']);
        $carlos->clasesAsistencia()->attach($jovenes->id, ['es_maestro' => false, 'tipo' => 'miembro']);

        $tarjetas = app(TarjetasAcceso::class)->tarjetas(TarjetasAcceso::FILTRO_ACTIVAS);
        $this->assertSame(['Ángela', 'Beatriz', 'Carlos', 'Zoila Sin Clase'], $tarjetas->pluck('nombre')->all());
        $this->assertSame(['Damas', 'Damas', 'Jóvenes', null], $tarjetas->pluck('clase')->all());
        $this->assertStringStartsWith(url('/t/'), $tarjetas[0]['url']);

        // Una clase: sus personas activas y la tarjeta muestra esa clase
        $deDamas = app(TarjetasAcceso::class)->tarjetas(TarjetasAcceso::FILTRO_CLASE, $damas->id, 'Damas');
        $this->assertSame(['Ángela', 'Beatriz', 'Carlos'], $deDamas->pluck('nombre')->all());
        $this->assertSame(['Damas'], $deDamas->pluck('clase')->unique()->values()->all());
        $this->assertNotContains($sinClase->nombre, $deDamas->pluck('nombre'));
    }

    public function test_la_pantalla_cuenta_a_quienes_entran_y_ofrece_las_tres_opciones(): void
    {
        $this->promesa($this->persona('Vigente'));
        $this->persona('Otra');
        $clase = $this->clase('Damas', 1);

        $this->get(route('personas.tarjetas.index'))
            ->assertOk()
            ->assertSee('Personas con promesa')
            ->assertSee('Todas las personas activas')
            ->assertSee('Una clase')
            ->assertSee('Damas')
            ->assertSee('8 tarjetas por hoja carta');

        $this->get(route('personas.tarjetas.index', ['filtro' => 'clase', 'clase' => $clase->id]))->assertOk();
    }

    public function test_el_pdf_lleva_8_tarjetas_por_hoja_carta(): void
    {
        for ($i = 1; $i <= 17; $i++) {
            $this->persona('Persona '.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }

        $respuesta = $this->get(route('personas.tarjetas.pdf', ['filtro' => 'activas', 'accion' => 'descargar']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename="tarjetas-personas-activas.pdf"', $respuesta->headers->get('Content-Disposition'));

        $pdf = $respuesta->getContent();
        $this->assertSame(3, preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf), '17 tarjetas = 3 hojas');
        $this->assertSame(1, preg_match('#/MediaBox \[0\.0+ 0\.0+ 612\.0+ 792\.0+\]#', $pdf), 'Hoja carta vertical (612 x 792 pt)');
        $this->assertSame(8, TarjetasPdf::POR_PAGINA);
    }

    public function test_el_pdf_de_255_personas_sale_muy_por_debajo_de_10_segundos(): void
    {
        $ahora = now();
        $filas = [];
        for ($i = 1; $i <= 255; $i++) {
            $filas[] = ['nombre' => 'Hermano Número '.$i.' Apellido Largo', 'activo' => true, 'tenant_id' => $this->iglesia->id, 'created_at' => $ahora, 'updated_at' => $ahora];
        }
        DB::table('personas')->insert($filas);

        $inicio = microtime(true);
        $this->get(route('personas.tarjetas.pdf', ['filtro' => 'activas']))->assertOk();
        $segundos = microtime(true) - $inicio;

        // Primera vez (sin ningun QR en cache, el peor caso): ~4 s en el equipo de desarrollo
        $this->assertLessThan(10, $segundos, "El PDF masivo de 255 tarjetas tardó {$segundos} s");
        $this->assertSame(255, Persona::whereNotNull('token_acceso')->count());

        // Con los QR ya calculados es casi inmediato
        $inicio = microtime(true);
        $this->get(route('personas.tarjetas.pdf', ['filtro' => 'activas']))->assertOk();
        $this->assertLessThan(3, microtime(true) - $inicio);
    }

    public function test_filtro_desconocido_o_clase_inexistente_vuelve_al_de_promesa(): void
    {
        $this->promesa($this->persona('Vigente'));
        $this->persona('Sin promesa');

        $respuesta = $this->get(route('personas.tarjetas.pdf', ['filtro' => 'clase', 'clase' => 999999]))->assertOk();
        $this->assertStringContainsString('tarjetas-con-promesa.pdf', $respuesta->headers->get('Content-Disposition'));
        $this->get(route('personas.tarjetas.pdf', ['filtro' => 'cualquiera']))->assertOk();
    }
}

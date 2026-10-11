<?php

namespace Tests\Feature\Ministerios;

use App\Models\Ministerio;
use App\Models\Tenant;
use Database\Seeders\MinisterioSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * MinisterioSeeder: lee config/ministerios.php y es idempotente (por iglesia + nombre).
 */
class MinisterioSeederTest extends TestCase
{
    use ConIglesia, RefreshDatabase;

    private Tenant $iglesia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iglesia = $this->crearIglesia();
    }

    private function sembrar(): void
    {
        $this->seed(MinisterioSeeder::class);
    }

    private function nombres(?Tenant $iglesia = null): array
    {
        return Ministerio::withoutGlobalScopes()
            ->where('tenant_id', ($iglesia ?? $this->iglesia)->id)
            ->orderBy('orden')
            ->pluck('nombre')
            ->all();
    }

    public function test_la_configuracion_real_siembra_una_lista_valida_y_sin_repetidos(): void
    {
        // Esta prueba sigue valiendo cuando el cliente entregue sus 30 ministerios y se
        // reemplace la lista de config/ministerios.php (acepta líneas "Nombre" o arreglos).
        $this->assertNotEmpty(config('ministerios.lista'));

        $this->sembrar();

        $nombres = $this->nombres();
        $this->assertNotEmpty($nombres);
        $this->assertCount(count($nombres), array_unique(array_map('mb_strtolower', $nombres)));
        $this->assertSame($nombres, array_values(array_filter($nombres, fn ($n) => trim($n) !== '')));
    }

    public function test_siembra_toda_la_lista_de_la_configuracion_en_orden(): void
    {
        config(['ministerios.lista' => [
            'Alabanza',
            ['nombre' => 'Transmisión', 'descripcion' => 'Sonido y video', 'color' => '#F59E0B'],
            'Ujieres',
        ]]);

        $this->sembrar();

        $this->assertSame(['Alabanza', 'Transmisión', 'Ujieres'], $this->nombres());

        $transmision = Ministerio::withoutGlobalScopes()->where('nombre', 'Transmisión')->first();
        $this->assertSame('transmision', $transmision->slug);
        $this->assertSame('#F59E0B', $transmision->color);
        $this->assertSame('Sonido y video', $transmision->descripcion);
        $this->assertSame(2, $transmision->orden);
        $this->assertTrue($transmision->activo);

        // Los que no traen color reciben uno de la paleta.
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', Ministerio::withoutGlobalScopes()->where('nombre', 'Alabanza')->value('color'));
    }

    public function test_es_idempotente_correrlo_varias_veces_no_duplica(): void
    {
        config(['ministerios.lista' => ['Alabanza', 'Transmisión', 'Ujieres']]);

        $this->sembrar();
        $this->sembrar();
        $this->sembrar();

        $this->assertSame(3, Ministerio::withoutGlobalScopes()->count());
    }

    public function test_agregar_nombres_a_la_lista_solo_crea_los_nuevos(): void
    {
        config(['ministerios.lista' => ['Alabanza', 'Ujieres']]);
        $this->sembrar();

        config(['ministerios.lista' => ['Alabanza', 'Ujieres', 'Intercesión', 'Danza']]);
        $this->sembrar();

        $this->assertSame(['Alabanza', 'Ujieres', 'Intercesión', 'Danza'], $this->nombres());
    }

    public function test_no_pisa_lo_que_el_administrador_cambio_desde_la_pantalla(): void
    {
        config(['ministerios.lista' => [['nombre' => 'Alabanza', 'color' => '#111111', 'descripcion' => 'original']]]);
        $this->sembrar();

        Ministerio::withoutGlobalScopes()->where('nombre', 'Alabanza')->update([
            'color' => '#999999', 'descripcion' => 'editada a mano', 'activo' => false, 'orden' => 40,
        ]);

        $this->sembrar();

        $alabanza = Ministerio::withoutGlobalScopes()->where('nombre', 'Alabanza')->first();
        $this->assertSame('#999999', $alabanza->color);
        $this->assertSame('editada a mano', $alabanza->descripcion);
        $this->assertFalse($alabanza->activo, 'Un ministerio desactivado no se reactiva al volver a sembrar.');
        $this->assertSame(40, $alabanza->orden);
        $this->assertSame(1, Ministerio::withoutGlobalScopes()->count());
    }

    public function test_reconoce_los_ministerios_creados_a_mano_aunque_cambien_mayusculas(): void
    {
        Ministerio::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'alabanza']);

        config(['ministerios.lista' => ['Alabanza', 'Ujieres']]);
        $this->sembrar();

        $this->assertSame(2, Ministerio::withoutGlobalScopes()->count());
    }

    public function test_ignora_lineas_vacias_y_nombres_repetidos_de_la_lista(): void
    {
        config(['ministerios.lista' => ['Alabanza', '', '  ', 'alabanza', ['nombre' => 'Ujieres'], ['descripcion' => 'sin nombre']]]);

        $this->sembrar();

        $this->assertSame(['Alabanza', 'Ujieres'], $this->nombres());
    }

    public function test_siembra_cada_iglesia_y_respeta_tenant_slugs(): void
    {
        $otra = $this->crearIglesia('otra', 'OTRA');
        config(['ministerios.lista' => ['Alabanza']]);

        $this->sembrar();
        $this->assertSame(['Alabanza'], $this->nombres($this->iglesia));
        $this->assertSame(['Alabanza'], $this->nombres($otra));

        // Solo la iglesia indicada.
        Ministerio::withoutGlobalScopes()->delete();
        config(['ministerios.tenant_slugs' => ['otra'], 'ministerios.lista' => ['Alabanza', 'Ujieres']]);
        $this->sembrar();
        $this->assertSame([], $this->nombres($this->iglesia));
        $this->assertSame(['Alabanza', 'Ujieres'], $this->nombres($otra));
    }

    public function test_una_lista_vacia_de_tenant_slugs_no_siembra_ninguna_iglesia(): void
    {
        config(['ministerios.lista' => ['Alabanza'], 'ministerios.tenant_slugs' => []]);

        $this->sembrar();

        $this->assertSame(0, Ministerio::withoutGlobalScopes()->count());
    }

    public function test_funciona_bajo_without_model_events_como_lo_corre_database_seeder(): void
    {
        // DatabaseSeeder usa WithoutModelEvents: el hook creating del modelo no corre,
        // así que el seeder tiene que fijar slug y orden por su cuenta.
        config(['ministerios.lista' => ['Alabanza', 'Niños / Escuela Dominical']]);

        Model::withoutEvents(fn () => $this->sembrar());

        $this->assertSame(
            ['alabanza' => 1, 'ninos-escuela-dominical' => 2],
            Ministerio::withoutGlobalScopes()->orderBy('orden')->pluck('orden', 'slug')->all()
        );
    }

    public function test_si_el_slug_ya_lo_usa_otro_ministerio_agrega_sufijo_en_vez_de_fallar(): void
    {
        // Ministerio creado a mano con nombre distinto pero que ocupa el slug "ninos".
        Ministerio::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Niños / Escuela Dominical', 'slug' => 'ninos']);

        config(['ministerios.lista' => ['Niños']]);
        $this->sembrar();

        $this->assertEqualsCanonicalizing(['ninos', 'ninos-2'], Ministerio::withoutGlobalScopes()->pluck('slug')->all());
    }
}

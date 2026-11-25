<?php

namespace Tests\Feature\Personas;

use App\Models\Persona;
use App\Models\Promesa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/** QA-036: guardar la ficha actualiza las promesas en sitio; "Reiniciar compromisos" con el mes en español. */
class PromesasEnSitioTest extends TestCase
{
    use ConIglesia, ConRecuento, RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    private Persona $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');
        $this->ana = $this->crearPersonaDe($this->iglesia, 'Ana Mora');
    }

    private function prometer(string $categoria, float $monto, string $moneda = 'CRC'): Promesa
    {
        return Promesa::create(['persona_id' => $this->ana->id, 'categoria' => $categoria, 'monto' => $monto, 'moneda' => $moneda, 'frecuencia' => 'mensual']);
    }

    private function guardar(array $promesas)
    {
        return $this->actingAs($this->admin)->put(route('personas.update', $this->ana), [
            'nombre' => 'Ana Mora',
            'telefono' => '8888-7777',
            'activo' => 1,
            'promesas' => $promesas,
        ]);
    }

    public function test_guardar_sin_cambios_conserva_ids_y_fechas(): void
    {
        $diezmo = $this->prometer('diezmo', 10000);
        $misiones = $this->prometer('misiones', 5, 'USD');
        $this->travel(2)->days();

        $this->guardar([
            ['categoria' => 'diezmo', 'monto' => 10000, 'moneda' => 'CRC', 'frecuencia' => 'mensual'],
            ['categoria' => 'misiones', 'monto' => 5, 'moneda' => 'USD', 'frecuencia' => 'mensual'],
            ['categoria' => 'construccion', 'monto' => 0, 'moneda' => 'CRC', 'frecuencia' => 'mensual'],
        ])->assertRedirect(route('personas.index'));

        $this->assertEqualsCanonicalizing([$diezmo->id, $misiones->id], Promesa::pluck('id')->all());
        $this->assertTrue($diezmo->fresh()->created_at->equalTo($diezmo->created_at));
        $this->assertTrue($diezmo->fresh()->updated_at->equalTo($diezmo->updated_at), 'Sin cambios no se toca');
    }

    public function test_cambia_la_existente_crea_la_nueva_y_borra_solo_la_quitada(): void
    {
        $diezmo = $this->prometer('diezmo', 10000);
        $misiones = $this->prometer('misiones', 5000);

        $this->guardar([
            ['categoria' => 'diezmo', 'monto' => 12000, 'moneda' => 'CRC', 'frecuencia' => 'mensual'],
            ['categoria' => 'misiones', 'monto' => 0, 'moneda' => 'CRC', 'frecuencia' => 'mensual'],
            ['categoria' => 'construccion', 'monto' => 20, 'moneda' => 'USD', 'frecuencia' => 'quincenal'],
        ])->assertRedirect(route('personas.index'));

        $this->assertSame(12000.0, (float) Promesa::find($diezmo->id)->monto, 'Misma promesa, monto nuevo');
        $this->assertNull(Promesa::find($misiones->id));
        $nueva = Promesa::where('categoria', 'construccion')->sole();
        $this->assertSame('USD', $nueva->moneda);
        $this->assertSame('quincenal', $nueva->frecuencia);
        $this->assertSame(2, Promesa::count());
    }

    public function test_duplicados_viejos_de_una_categoria_quedan_en_una(): void
    {
        $primera = $this->prometer('diezmo', 10000);
        $this->prometer('diezmo', 3000);

        $this->guardar([['categoria' => 'diezmo', 'monto' => 10000, 'moneda' => 'CRC', 'frecuencia' => 'mensual']]);

        $this->assertSame([$primera->id], Promesa::pluck('id')->all());
    }

    public function test_reiniciar_compromisos_dice_el_mes_en_espanol(): void
    {
        $this->actingAs($this->admin)->post(route('personas.reiniciar-compromisos', $this->ana), ['fecha_inicio' => '2026-10-01'])
            ->assertRedirect(route('personas.edit', $this->ana))
            ->assertSessionHas('success', 'Compromisos reiniciados correctamente desde octubre de 2026.');
    }
}

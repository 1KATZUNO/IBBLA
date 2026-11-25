<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Culto;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioAsistencia;
use Tests\Support\ConIglesia;
use Tests\Support\ConRecuento;
use Tests\TestCase;

/**
 * QE-018: "Miercoles", "Sabado", "Construccion", "Prestamo" (datos de la iglesia), "Nino", "Nina",
 * "Asignalos" y "eliminado con sus 1 sobres".
 */
class TildesYGramaticaTest extends TestCase
{
    use ConIglesia, ConRecuento, CreaEscenarioAsistencia, RefreshDatabase {
        ConRecuento::crearCulto insteadof CreaEscenarioAsistencia;
        CreaEscenarioAsistencia::crearCulto as crearCultoAsistencia;
    }

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_24_100000_corregir_tildes_tipos_culto_y_categorias.php');
    }

    public function test_la_migracion_corrige_solo_los_nombres_exactos_por_su_slug(): void
    {
        $iglesia = $this->crearIglesia();
        $otra = $this->crearIglesia('otra', 'OTRA');
        // Datos como estan hoy en produccion
        DB::table('tenant_service_types')->where('slug', 'miercoles')->update(['nombre' => 'Miercoles']);
        DB::table('tenant_service_types')->where('slug', 'sabado')->update(['nombre' => 'Sabado']);
        DB::table('tenant_categories')->where('slug', 'construccion')->update(['nombre' => 'Construccion']);
        DB::table('tenant_categories')->where('slug', 'prestamo')->update(['nombre' => 'Prestamo']);
        // Una iglesia que ya lo renombro a su gusto no se toca
        DB::table('tenant_categories')->where('tenant_id', $otra->id)->where('slug', 'prestamo')->update(['nombre' => 'Préstamo del templo']);

        $this->migracion()->up();

        $nombre = fn (string $tabla, int $tenant, string $slug) => DB::table($tabla)->where('tenant_id', $tenant)->where('slug', $slug)->value('nombre');
        $this->assertSame('Miércoles', $nombre('tenant_service_types', $iglesia->id, 'miercoles'));
        $this->assertSame('Sábado', $nombre('tenant_service_types', $iglesia->id, 'sabado'));
        $this->assertSame('Construcción', $nombre('tenant_categories', $iglesia->id, 'construccion'));
        $this->assertSame('Préstamo', $nombre('tenant_categories', $iglesia->id, 'prestamo'));
        $this->assertSame('Préstamo del templo', $nombre('tenant_categories', $otra->id, 'prestamo'));
        // Los slugs (el enlace con sobres, promesas, compromisos y totales) no cambian
        $this->assertSame(2, DB::table('tenant_categories')->where('slug', 'construccion')->count());
        $this->assertSame('Domingo AM', $nombre('tenant_service_types', $iglesia->id, 'domingo'));

        $this->migracion()->down();
        $this->assertSame('Miercoles', $nombre('tenant_service_types', $iglesia->id, 'miercoles'));
        $this->assertSame('Préstamo del templo', $nombre('tenant_categories', $otra->id, 'prestamo'));
    }

    public function test_los_tipos_de_culto_nuevos_y_los_heredados_llevan_tilde(): void
    {
        $iglesia = $this->crearIglesia();
        $this->assertSame('Miércoles', DB::table('tenant_service_types')->where('tenant_id', $iglesia->id)->where('slug', 'miercoles')->value('nombre'));

        $legado = new Culto(['tipo_culto' => 'sabado']);
        $this->assertSame('Sábado', $legado->tipo_nombre);
    }

    public function test_eliminar_un_culto_con_un_sobre_lo_dice_en_singular_y_con_varios_en_plural(): void
    {
        $iglesia = $this->crearIglesia();
        $admin = $this->crearUsuario($iglesia, 'admin');

        $uno = $this->crearCulto($iglesia, '2026-11-08');
        $this->crearSobreEn($uno);
        $this->actingAs($admin)->delete(route('cultos.destroy', $uno), ['motivo' => 'Culto duplicado por error', 'confirmo' => '1'])
            ->assertSessionHas('success', 'Culto del 08/11/2026 eliminado con 1 sobre. Quedó registrado en la auditoría.');

        $dos = $this->crearCulto($iglesia, '2026-11-15');
        $this->crearSobreEn($dos);
        $this->crearSobreEn($dos);
        $this->actingAs($admin)->delete(route('cultos.destroy', $dos), ['motivo' => 'Culto duplicado por error', 'confirmo' => '1'])
            ->assertSessionHas('success', 'Culto del 15/11/2026 eliminado con sus 2 sobres. Quedó registrado en la auditoría.');
    }

    public function test_asistencia_dice_nino_nina_y_asignalos_con_tilde(): void
    {
        $this->crearIglesiaConAdmin();
        $this->crearClase('Clase 2-6 Años', 'clase_2_6');
        $this->crearCultoAsistencia('2026-11-01');

        $this->actingAs($this->admin)->get(route('asistencia.create'))
            ->assertOk()
            ->assertSee('>Niño</label>', false)
            ->assertSee('>Niña</label>', false)
            ->assertSee('Asígnalos en Gestionar Personas')
            ->assertDontSee('>Nino</label>', false);
    }
}

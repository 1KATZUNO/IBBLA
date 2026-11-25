<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * QE-019: los subtítulos de los accesos rápidos de Principal salían cortados con "…" a 1366 px
 * ("Recuento del culto a…", "Capilla, clases, salv…"). Ahora pasan a una segunda línea.
 */
class PrincipalAccesosTest extends TestCase
{
    use ConIglesia, RefreshDatabase;

    public function test_los_subtitulos_de_los_accesos_se_ven_completos_sin_cortar(): void
    {
        $iglesia = $this->crearIglesia();
        $html = $this->actingAs($this->crearUsuario($iglesia, 'admin'))
            ->get(route('principal'))
            ->assertOk()
            ->assertSee('Recuento del culto abierto')
            ->assertSee('Capilla, clases, salvos y visitas')
            ->assertSee('Buscar, agregar y editar')
            ->getContent();

        preg_match_all('/<span class="([^"]*)" data-detalle-acceso>/', $html, $subtitulos);
        $this->assertNotEmpty($subtitulos[1]);
        foreach ($subtitulos[1] as $clases) {
            $this->assertStringNotContainsString('truncate', $clases, 'Un subtítulo cortado con "…" no se entiende');
            $this->assertStringNotContainsString('line-clamp', $clases);
        }
    }
}

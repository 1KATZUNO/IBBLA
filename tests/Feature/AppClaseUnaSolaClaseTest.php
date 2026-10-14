<?php

namespace Tests\Feature;

use App\Models\ClaseAsistencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * QA-020: la App de Clases daba error 500 cuando el usuario veia una sola clase (el caso normal de
 * una maestra): el metodo declaraba que siempre devolvia una vista y con una clase redirige.
 */
class AppClaseUnaSolaClaseTest extends TestCase
{
    use ConIglesia, RefreshDatabase;

    public function test_con_una_sola_clase_entra_directo_a_esa_clase(): void
    {
        $iglesia = $this->crearIglesia();
        $clase = ClaseAsistencia::create([
            'tenant_id' => $iglesia->id,
            'nombre' => 'Niños 2-6',
            'slug' => 'ninos_2_6',
            'activa' => true,
            'orden' => 1,
        ]);
        $maestra = $this->crearUsuario($iglesia, 'asistente', ['clase_asistencia_id' => $clase->id]);
        $admin = $this->crearUsuario($iglesia, 'admin');

        $this->actingAs($maestra)->get(route('clase-app.index'))
            ->assertRedirect(route('clase-app.shell', 'ninos_2_6'));
        $this->actingAs($admin)->get(route('clase-app.index'))
            ->assertRedirect(route('clase-app.shell', 'ninos_2_6'));
    }

    public function test_con_varias_clases_muestra_el_selector(): void
    {
        $iglesia = $this->crearIglesia();
        foreach (['ninos_2_6' => 'Niños 2-6', 'jovenes' => 'Jóvenes'] as $slug => $nombre) {
            ClaseAsistencia::create(['tenant_id' => $iglesia->id, 'nombre' => $nombre, 'slug' => $slug, 'activa' => true, 'orden' => 1]);
        }

        $this->actingAs($this->crearUsuario($iglesia, 'admin'))->get(route('clase-app.index'))
            ->assertOk()
            ->assertSee('Jóvenes');
    }
}

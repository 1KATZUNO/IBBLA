<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\ClaseAsistencia;
use App\Models\Culto;
use App\Models\RegistroExtraTipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioAsistencia;
use Tests\TestCase;

/**
 * El cliente pidió pasar de 100 personas por casilla. El tope real estaba en los formularios
 * (desplegables con las opciones 0..100), no en las reglas ni en la base. Ahora el tope único es
 * Asistencia::MAX_CANTIDAD (999) en la casilla, en la validación y en la app de asistencia.
 */
class AsistenciaTopeCantidadTest extends TestCase
{
    use CreaEscenarioAsistencia;
    use RefreshDatabase;

    private ClaseAsistencia $claseNinos;

    private RegistroExtraTipo $transmision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearIglesiaConAdmin();
        $this->claseNinos = $this->crearClase('Clase 2-6 Años', 'clase_2_6');
        $this->transmision = $this->crearTipoExtra('transmision', ['miembros']);
    }

    /** Payload completo del formulario: todas las casillas en 0 salvo lo que se pase en $cambios. */
    private function payload(Culto $culto, array $cambios = []): array
    {
        $payload = [
            'culto_id' => $culto->id,
            'chapel_adultos_hombres' => 0,
            'chapel_adultos_mujeres' => 0,
            'total_asistencia' => 0,
            'clase' => [$this->claseNinos->id => ['hombres' => 0, 'mujeres' => 0]],
        ];

        foreach (['salvos', 'bautismos', 'visitas'] as $grupo) {
            foreach (['adulto_hombre', 'adulto_mujer', 'joven_hombre', 'joven_mujer', 'nino', 'nina'] as $sub) {
                $payload["{$grupo}_{$sub}"] = 0;
            }
        }

        return array_replace_recursive($payload, $cambios);
    }

    public function test_el_formulario_de_nueva_asistencia_usa_casillas_numericas_con_tope_999(): void
    {
        $this->crearCulto('2026-10-04');

        $html = $this->actingAs($this->admin)
            ->get(route('asistencia.create'))
            ->assertOk()
            ->getContent();

        // Ya no hay desplegables 0..100 para las cantidades
        $this->assertStringNotContainsString('<select name="chapel_adultos_hombres"', $html);
        $this->assertStringNotContainsString('<option value="100"', $html);

        $claseId = $this->claseNinos->id;
        $tipoId = $this->transmision->id;
        foreach ([
            'chapel_adultos_hombres',
            "clase[{$claseId}][hombres]",
            'salvos_nino',
            'visitas_adulto_mujer',
            "registro_extra[{$tipoId}][miembros]",
        ] as $nombre) {
            $this->assertMatchesRegularExpression(
                '/<input type="number"\s+name="'.preg_quote($nombre, '/').'"[^>]*\smax="999"/',
                $html,
                "La casilla {$nombre} debería ser numérica con max=\"999\".",
            );
        }
    }

    public function test_el_formulario_de_edicion_muestra_las_cantidades_guardadas_sobre_100(): void
    {
        $culto = $this->crearCulto('2026-10-04');
        $asistencia = Asistencia::create([
            'culto_id' => $culto->id,
            'tenant_id' => $this->iglesia->id,
            'chapel_adultos_hombres' => 150,
            'total_asistencia' => 150,
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('asistencia.edit', $asistencia))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<input type="number"\s+name="chapel_adultos_hombres"\s+value="150"[^>]*\smax="999"/',
            $html,
        );
    }

    public function test_se_guarda_una_asistencia_con_mas_de_100_por_casilla(): void
    {
        $culto = $this->crearCulto('2026-10-04');

        $this->actingAs($this->admin)
            ->post(route('asistencia.store'), $this->payload($culto, [
                'chapel_adultos_hombres' => 150,
                'chapel_adultos_mujeres' => 180,
                'total_asistencia' => 750,
                'salvos_nino' => 150,
                'bautismos_adulto_mujer' => 101,
                'visitas_joven_hombre' => 999,
                'clase' => [$this->claseNinos->id => ['hombres' => 150, 'mujeres' => 120]],
                'registro_extra' => [$this->transmision->id => ['miembros' => 700]],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('asistencia.index'));

        $this->assertDatabaseHas('asistencia', [
            'culto_id' => $culto->id,
            'chapel_adultos_hombres' => 150,
            'chapel_adultos_mujeres' => 180,
            'total_asistencia' => 750,
            'salvos_nino' => 150,
            'bautismos_adulto_mujer' => 101,
            'visitas_joven_hombre' => 999,
        ]);
        $this->assertDatabaseHas('asistencia_clase_detalle', [
            'clase_asistencia_id' => $this->claseNinos->id,
            'hombres' => 150,
            'mujeres' => 120,
        ]);
        $this->assertSame(
            700,
            Asistencia::where('culto_id', $culto->id)->first()->registrosExtra()->first()->valores['miembros'],
        );
    }

    public function test_el_tope_es_999_y_mil_se_rechaza_en_cada_tipo_de_casilla(): void
    {
        $culto = $this->crearCulto('2026-10-04');
        $claseId = $this->claseNinos->id;

        $this->actingAs($this->admin)
            ->post(route('asistencia.store'), $this->payload($culto, [
                'chapel_adultos_hombres' => 1000,
                'salvos_nina' => 1000,
                'clase' => [$claseId => ['hombres' => 1000, 'mujeres' => 1000]],
                'registro_extra' => [$this->transmision->id => ['miembros' => 1000]],
            ]))
            ->assertSessionHasErrors([
                'chapel_adultos_hombres',
                'salvos_nina',
                "clase.{$claseId}.hombres",
                "clase.{$claseId}.mujeres",
                "registro_extra.{$this->transmision->id}.miembros",
            ]);

        $this->assertDatabaseCount('asistencia', 0);

        // 999 sí pasa
        $this->actingAs($this->admin)
            ->post(route('asistencia.store'), $this->payload($culto, ['chapel_adultos_hombres' => 999]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('asistencia', ['culto_id' => $culto->id, 'chapel_adultos_hombres' => 999]);
    }

    public function test_el_total_de_asistencia_puede_pasar_de_999_porque_es_la_suma_de_las_casillas(): void
    {
        $culto = $this->crearCulto('2026-10-04');

        $this->actingAs($this->admin)
            ->post(route('asistencia.store'), $this->payload($culto, ['total_asistencia' => 2500]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('asistencia', ['culto_id' => $culto->id, 'total_asistencia' => 2500]);
    }

    public function test_un_cero_a_la_izquierda_no_hace_rebotar_el_formulario(): void
    {
        // <input type="number"> manda "05" si se escribe un 5 detrás del 0 inicial
        $culto = $this->crearCulto('2026-10-04');

        $this->actingAs($this->admin)
            ->post(route('asistencia.store'), $this->payload($culto, [
                'chapel_adultos_hombres' => '05',
                'clase' => [$this->claseNinos->id => ['hombres' => '007', 'mujeres' => '00']],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('asistencia', ['culto_id' => $culto->id, 'chapel_adultos_hombres' => 5]);
        $this->assertDatabaseHas('asistencia_clase_detalle', [
            'clase_asistencia_id' => $this->claseNinos->id,
            'hombres' => 7,
            'mujeres' => 0,
        ]);
    }

    public function test_se_actualiza_una_asistencia_con_mas_de_100_y_se_rechaza_mas_de_999(): void
    {
        $culto = $this->crearCulto('2026-10-04');
        $asistencia = Asistencia::create(['culto_id' => $culto->id, 'tenant_id' => $this->iglesia->id, 'total_asistencia' => 0]);

        $this->actingAs($this->admin)
            ->put(route('asistencia.update', $asistencia), $this->payload($culto, ['chapel_adultos_mujeres' => 150]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('asistencia.index'));
        $this->assertSame(150, $asistencia->fresh()->chapel_adultos_mujeres);

        $this->actingAs($this->admin)
            ->put(route('asistencia.update', $asistencia), $this->payload($culto, ['chapel_adultos_mujeres' => 1000]))
            ->assertSessionHasErrors('chapel_adultos_mujeres');
        $this->assertSame(150, $asistencia->fresh()->chapel_adultos_mujeres);
    }

    public function test_la_app_de_asistencia_acepta_150_y_rechaza_mas_de_999(): void
    {
        $culto = $this->crearCulto('2026-10-04');
        $claseId = $this->claseNinos->id;
        // La app guarda primero la clase y luego la capilla; con la capilla primero, el endpoint falla
        // porque total_asistencia no tiene valor por defecto (falla previa, ajena al tope).
        Asistencia::create(['culto_id' => $culto->id, 'tenant_id' => $this->iglesia->id, 'total_asistencia' => 0]);

        $this->actingAs($this->admin)
            ->postJson('/asistencia-app/asistencia/capilla', [
                'culto_id' => $culto->id,
                'chapel_adultos_hombres' => 150,
                'chapel_adultos_mujeres' => 120,
                'visitas_nino' => 101,
            ])
            ->assertOk();
        $this->assertDatabaseHas('asistencia', ['culto_id' => $culto->id, 'chapel_adultos_hombres' => 150, 'visitas_nino' => 101]);

        $this->actingAs($this->admin)
            ->postJson('/asistencia-app/asistencia/clase', [
                'culto_id' => $culto->id,
                'clase_asistencia_id' => $claseId,
                'hombres' => 150,
                'mujeres' => 0,
            ])
            ->assertOk();
        $this->assertDatabaseHas('asistencia_clase_detalle', ['clase_asistencia_id' => $claseId, 'hombres' => 150]);

        $this->actingAs($this->admin)
            ->postJson('/asistencia-app/asistencia/clase', [
                'culto_id' => $culto->id,
                'clase_asistencia_id' => $claseId,
                'hombres' => 1000,
                'mujeres' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('hombres');
    }
}

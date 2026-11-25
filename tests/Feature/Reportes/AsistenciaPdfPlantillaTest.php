<?php

namespace Tests\Feature\Reportes;

use App\Models\Asistencia;
use App\Models\Culto;
use App\Models\RegistroExtraTipo;
use App\Services\Reportes\Exportaciones\AsistenciaPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioAsistencia;
use Tests\TestCase;

/**
 * HU-R14: los PDF de asistencia por rango y por mes pasan a la plantilla unica sin cambiar ninguna
 * cifra (mismos metodos de Asistencia y de los registros extra que el PDF anterior).
 */
class AsistenciaPdfPlantillaTest extends TestCase
{
    use CreaEscenarioAsistencia;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearIglesiaConAdmin();
        $clase = $this->crearClase('Clase 2-6 Años', 'clase_2_6', 1);
        $this->registrarClase($this->crearCulto('2026-08-02'), $clase, 10, 5, 1);
        $this->registrarClase($this->crearCulto('2026-08-09'), $clase, 7, 3, 2);
        $capilla = $this->crearCulto('2026-08-16');
        Asistencia::create(['culto_id' => $capilla->id, 'tenant_id' => $this->iglesia->id, 'chapel_adultos_hombres' => 40, 'chapel_adultos_mujeres' => 35, 'total_asistencia' => 75]);
        $this->crearCulto('2026-08-23'); // sin asistencia: no sale
        $this->crearTipoExtra('transmision', ['miembros']);
    }

    public function test_los_totales_son_los_mismos_que_daban_los_metodos_de_asistencia(): void
    {
        $this->actingAs($this->admin);
        $cultos = Culto::with(['asistencia.detallesClases.claseAsistencia', 'asistencia.registrosExtra.tipo'])->orderBy('fecha')->get();
        $tabla = AsistenciaPdf::tabla($cultos, RegistroExtraTipo::activos()->ordenados()->get());

        $conAsistencia = $cultos->filter->asistencia;
        $this->assertCount(3, $tabla['filas']);
        $this->assertSame([
            $conAsistencia->sum(fn ($c) => (int) $c->asistencia->total_asistencia),
            $conAsistencia->sum(fn ($c) => $c->asistencia->getTotalHombres()),
            $conAsistencia->sum(fn ($c) => $c->asistencia->getTotalMujeres()),
            $conAsistencia->sum(fn ($c) => $c->asistencia->getTotalNinos()),
            $conAsistencia->sum(fn ($c) => $c->asistencia->getTotalCapilla()),
            $conAsistencia->sum(fn ($c) => $c->asistencia->getTotalVisitas()),
            $conAsistencia->sum(fn ($c) => $c->asistencia->getTotalSalvos()),
            $conAsistencia->sum(fn ($c) => $c->asistencia->getTotalBautismos()),
        ], array_slice($tabla['totales'], 0, 8));
        $this->assertSame('Transmision', end($tabla['columnas']), 'Columna del registro extra (un solo subcampo: el nombre del tipo)');
        $this->assertSame(75, $tabla['filas'][2][2], 'Total de la capilla del 16/08');
    }

    public function test_pdf_por_rango_y_por_mes_salen_con_la_plantilla(): void
    {
        $rango = $this->actingAs($this->admin)->get(route('ingresos-asistencia.pdf-asistencia', ['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-31']))->assertOk();
        $this->assertSame('application/pdf', $rango->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $rango->getContent());
        $this->assertStringContainsString('attachment', $rango->headers->get('Content-Disposition'));

        $mes = $this->actingAs($this->crearUsuarioConRol('tesorero'))->get(route('ingresos-asistencia.pdf-asistencia-mes', ['mes' => 8, 'año' => 2026]))->assertOk();
        $this->assertStringStartsWith('%PDF', $mes->getContent());
        $this->assertStringContainsString('asistencia_agosto_2026.pdf', $mes->headers->get('Content-Disposition'));

        // Excel: hoja Resumen y hoja Asistencia con los mismos totales
        $excel = $this->actingAs($this->admin)->get(route('ingresos-asistencia.excel-asistencia', ['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-31']))->assertOk();
        $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($archivo, $excel->getContent());
        $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load($archivo);
        @unlink($archivo);
        $this->assertSame(['Resumen', 'Asistencia'], $libro->getSheetNames());
        $hoja = $libro->getSheetByName('Asistencia');
        $this->assertSame('Fecha', $hoja->getCell('A1')->getValue());
        $this->assertSame('TOTALES', $hoja->getCell('A5')->getValue());
        $this->assertSame((int) Asistencia::sum('total_asistencia'), (int) $hoja->getCell('C5')->getValue(), 'Total de asistencia igual al guardado');
        $this->assertSame(75, (int) $hoja->getCell('G5')->getValue(), 'Capilla');
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.excel-asistencia-mes', ['mes' => 8, 'año' => 2026]))->assertOk();

        // Sin asistencia en el periodo: PDF con el aviso, no error
        $this->actingAs($this->admin)->get(route('ingresos-asistencia.pdf-asistencia', ['fecha_inicio' => '2020-01-01', 'fecha_fin' => '2020-01-31']))->assertOk();
    }
}

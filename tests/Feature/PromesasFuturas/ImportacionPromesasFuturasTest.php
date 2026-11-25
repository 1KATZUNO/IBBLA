<?php

namespace Tests\Feature\PromesasFuturas;

use App\Models\AuditLog;
use App\Models\ClaseAsistencia;
use App\Models\Compromiso;
use App\Models\CortePromesas;
use App\Models\Persona;
use App\Models\Promesa;
use App\Models\PromesaProgramada;
use App\Models\Tenant;
use App\Models\TenantCategory;
use App\Models\User;
use App\Services\Promesas\Importacion\LectorExcelPromesas;
use App\Services\Promesas\Importacion\PlantillaPromesas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\ConIglesia;
use Tests\TestCase;

/**
 * Importacion del Excel de promesas futuras (06-PROMESAS-FUTURAS.md, 4.2 y 4.3). Archivos sinteticos:
 * nunca el archivo real del cliente.
 */
class ImportacionPromesasFuturasTest extends TestCase
{
    use ConIglesia;
    use RefreshDatabase;

    private Tenant $iglesia;

    private User $admin;

    private CortePromesas $corte;

    /** @var list<string> */
    private array $archivos = [];

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-24 10:00:00');
        $this->travelTo('2026-11-24 10:00:00');
        $this->iglesia = $this->crearIglesia();
        $this->admin = $this->crearUsuario($this->iglesia, 'admin');
        $this->corte = CortePromesas::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Promesas 2027', 'inicia_el' => '2027-01-01', 'categorias_nuevas' => []]);
    }

    protected function tearDown(): void
    {
        foreach ($this->archivos as $a) {
            @unlink($a);
        }
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @param  list<list<mixed>>  $filas  (la primera es la de titulos) */
    private function excel(array $filas): string
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray($filas, null, 'A1', true);
        $ruta = tempnam(sys_get_temp_dir(), 'pf').'.xlsx';
        (new Xlsx($libro))->save($ruta);
        $this->archivos[] = $ruta;

        return $ruta;
    }

    private function subir(string $ruta, string $frecuencia = 'semanal', string $moneda = 'CRC')
    {
        return $this->actingAs($this->admin)->post(route('promesas-futuras.importar.subir', $this->corte), [
            'archivo' => new UploadedFile($ruta, 'promesas.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'frecuencia' => $frecuencia,
            'moneda' => $moneda,
        ]);
    }

    private function persona(string $nombre): Persona
    {
        return Persona::create(['nombre' => $nombre, 'activo' => true, 'tenant_id' => $this->iglesia->id]);
    }

    /** Escenario: lo que hay en la iglesia y un archivo con cada caso. */
    private function escenario(): array
    {
        $jose = $this->persona('José Pérez');
        $ana = $this->persona('Ana Mora Solís');
        $marta = $this->persona('Marta Rojas');
        ClaseAsistencia::create(['tenant_id' => $this->iglesia->id, 'nombre' => 'Jóvenes', 'slug' => 'jovenes', 'activa' => true, 'orden' => 1]);
        // Marta ya esta en el corte: no se le cambia ni se le duplica nada
        PromesaProgramada::create(['corte_id' => $this->corte->id, 'persona_id' => $marta->id, 'categoria' => 'misiones', 'monto' => 999, 'moneda' => 'CRC', 'frecuencia' => 'mensual']);
        // Lo que rige hoy
        Promesa::create(['persona_id' => $jose->id, 'categoria' => 'misiones', 'monto' => 2000, 'moneda' => 'CRC', 'frecuencia' => 'semanal']);

        $ruta = $this->excel([
            ['Miembro', 'Clase', 'Misiones', 'Construccion', 'Alcance Local'],
            ['jose perez', 'Adultos', 230.769230769231, '1,500.50', null],
            ['Ana Mora', '', 1000, null, 500],
            ['Pedro Nuevo', 'jovenes', 3000, 0, 250],
            ['Marta Rojas', '', 5000, 5000, 5000],
            ['Luis Sin Montos', '', null, 0, ''],
            ['José Pérez', '', 99999, null, null],
        ]);

        return compact('jose', 'ana', 'marta', 'ruta');
    }

    public function test_plantilla_trae_los_titulos_y_las_instrucciones(): void
    {
        $r = $this->actingAs($this->admin)->get(route('promesas-futuras.plantilla'))->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'pl').'.xlsx';
        $this->archivos[] = $ruta;
        file_put_contents($ruta, $r->getContent());
        $libro = IOFactory::load($ruta);

        $titulos = $libro->getSheet(0)->rangeToArray('A1:J1')[0];
        $this->assertSame(['Miembro', 'Clase', 'Frecuencia', 'Misiones', 'Seminario', 'Construcción', 'CAMPA', 'Préstamo', 'Micro', null], $titulos);
        // La columna Frecuencia trae su lista desplegable
        $lista = $libro->getSheet(0)->getCell('C2')->getDataValidation();
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST, $lista->getType());
        $this->assertSame('"Semanal,Quincenal,Mensual"', $lista->getFormula1());
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST, $libro->getSheet(0)->getCell('C500')->getDataValidation()->getType());
        $this->assertSame('Instrucciones', $libro->getSheet(1)->getTitle());
        $instrucciones = implode(' ', array_column($libro->getSheet(1)->toArray(), 0));
        $this->assertStringContainsString('categoría nueva', $instrucciones);
        $this->assertStringContainsString('Columna C: Frecuencia', $instrucciones);
        $this->assertStringContainsString('De la columna D en adelante', $instrucciones);

        // La plantilla vacia la entiende el lector (solo le faltan personas)
        $this->assertSame(['El archivo no trae personas: escribe una persona por fila desde la fila 2.'], (new LectorExcelPromesas)->leer($ruta)['errores']);
    }

    public function test_revision_empareja_categorias_y_personas_sin_guardar_nada(): void
    {
        ['ruta' => $ruta] = $this->escenario();
        $promesasAntes = Promesa::count();

        $this->subir($ruta)->assertRedirect(route('promesas-futuras.importar.revisar', $this->corte));
        $html = $this->actingAs($this->admin)->get(route('promesas-futuras.importar.revisar', $this->corte))->assertOk();
        $html->assertSee('Calza con Misiones')->assertSee('Calza con Construcción')
            ->assertSee('Crear la categoría nueva «Alcance Local»', false)
            ->assertSee('Nombre parecido')->assertSee('Ana Mora Solís')
            ->assertSee('No existe')->assertSee('Pedro Nuevo')
            ->assertSee('Ya está en este corte: se deja como está')
            ->assertSee('Sin montos: se ignora')
            ->assertSee('Repetida en el archivo: se toma la primera');

        $this->assertSame(1, PromesaProgramada::count());
        $this->assertSame($promesasAntes, Promesa::count());
        $this->assertFalse(TenantCategory::where('nombre', 'Alcance Local')->exists());
    }

    public function test_aplicar_sin_decidir_el_nombre_parecido_no_guarda_nada(): void
    {
        ['ruta' => $ruta] = $this->escenario();
        $this->subir($ruta);
        $this->actingAs($this->admin)->from(route('promesas-futuras.importar.revisar', $this->corte))
            ->post(route('promesas-futuras.importar.aplicar', $this->corte), [])
            ->assertSessionHasErrors('filas.3');
        $this->assertSame(1, PromesaProgramada::count());
        $this->assertSame(3, Persona::count());
        $this->assertFalse(TenantCategory::where('nombre', 'Alcance Local')->exists());
    }

    public function test_aplicar_crea_lo_nuevo_inactivo_y_no_toca_a_quien_ya_estaba(): void
    {
        ['jose' => $jose, 'ana' => $ana, 'marta' => $marta, 'ruta' => $ruta] = $this->escenario();
        $promesasHoy = Promesa::orderBy('id')->get(['persona_id', 'categoria', 'monto'])->toArray();
        $compromisos = Compromiso::count();
        $categoriasHoy = tenant_categories(['excluir_de_promesas' => false])->pluck('slug')->all();

        $this->subir($ruta);
        $this->actingAs($this->admin)->post(route('promesas-futuras.importar.aplicar', $this->corte), [
            'filas' => [3 => 'persona:'.$ana->id],
        ])->assertRedirect(route('promesas-futuras.show', $this->corte))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '3 personas agregadas') && str_contains($m, '1 ya estaba y se dejó igual')
                && str_contains($m, '2 omitidas') && str_contains($m, 'Alcance Local'));

        // Categoria nueva: inactiva, anotada en el corte, fuera de tenant_categories()
        $nueva = TenantCategory::where('tenant_id', $this->iglesia->id)->where('nombre', 'Alcance Local')->firstOrFail();
        $this->assertFalse($nueva->activa);
        $this->assertFalse($nueva->excluir_de_promesas);
        $this->assertSame('ambos', $nueva->tipo);
        $this->assertSame('alcance_local', $nueva->slug);
        $this->assertSame([$nueva->id], $this->corte->fresh()->categorias_nuevas);
        $this->assertSame($categoriasHoy, tenant_categories(['excluir_de_promesas' => false])->pluck('slug')->all());

        // José: decimales a dos, "1,500.50" leido bien, sin columna vacia
        $deJose = PromesaProgramada::where('persona_id', $jose->id)->orderBy('categoria')->get();
        $this->assertSame([['construccion', '1500.50'], ['misiones', '230.77']], $deJose->map(fn ($p) => [$p->categoria, $p->monto])->all());
        $this->assertSame('semanal', $deJose->first()->frecuencia);

        // Ana (nombre parecido, elegida) y Pedro (nuevo, con su clase)
        $this->assertSame(2, PromesaProgramada::where('persona_id', $ana->id)->count());
        $pedro = Persona::where('nombre', 'Pedro Nuevo')->firstOrFail();
        $this->assertSame(['Jóvenes'], $pedro->clasesAsistencia()->pluck('nombre')->all());
        $this->assertSame(['alcance_local' => '250.00', 'misiones' => '3000.00'], PromesaProgramada::where('persona_id', $pedro->id)->orderBy('categoria')->pluck('monto', 'categoria')->all());

        // Marta ya estaba: se dejo como estaba
        $this->assertSame([['misiones', '999.00', 'mensual']], PromesaProgramada::where('persona_id', $marta->id)->get()->map(fn ($p) => [$p->categoria, $p->monto, $p->frecuencia])->all());
        // José no se repite por la fila repetida; José no cambia de clase
        $this->assertSame(0, $jose->clasesAsistencia()->count());
        $this->assertSame(4, Persona::count(), 'solo se creó Pedro');

        // Lo que rige hoy, intacto
        $this->assertSame($promesasHoy, Promesa::orderBy('id')->get(['persona_id', 'categoria', 'monto'])->toArray());
        $this->assertSame($compromisos, Compromiso::count());
        $this->assertTrue(AuditLog::where('action', 'Promesas futuras importadas de Excel')->exists());

        // Volver a subir el mismo archivo no agrega nada
        $antes = PromesaProgramada::orderBy('id')->get()->toArray();
        $this->subir($ruta);
        $this->actingAs($this->admin)->get(route('promesas-futuras.importar.revisar', $this->corte))->assertOk()
            ->assertSee('Calza con Alcance Local')->assertDontSee('Crear la categoría nueva', false);
        $this->actingAs($this->admin)->post(route('promesas-futuras.importar.aplicar', $this->corte), [
            'filas' => [3 => 'persona:'.$ana->id],
        ])->assertSessionHas('success', fn ($m) => str_contains($m, '0 personas agregadas') && str_contains($m, '4 ya estaban'));
        $this->assertSame($antes, PromesaProgramada::orderBy('id')->get()->toArray());
        $this->assertSame(4, Persona::count());
        $this->assertSame(1, TenantCategory::where('nombre', 'Alcance Local')->count());
    }

    public function test_los_reportes_existentes_dan_lo_mismo_antes_y_despues_de_importar(): void
    {
        ['ana' => $ana, 'ruta' => $ruta] = $this->escenario();
        $pantallas = [
            route('ingresos-asistencia.promesas', ['anio' => 2026]),
            route('reportes.cumplimiento', ['corte' => '2026-11-24']),
            route('dashboard.promesas'),
            route('personas.reporte-general', ['accion' => 'ver']),
            route('personas.index'),
        ];
        // Fuera lo que cambia en cada peticion: token CSRF y fecha / id del PDF
        $quitarToken = fn (string $html) => preg_replace(['/(name="_token" value="|csrf-token" content=")[^"]+"/', '/\/(CreationDate|ModDate) \([^)]*\)/', '/\/ID\[<[0-9a-f]+><[0-9a-f]+>\]/'], ['$1"', '/$1', '/ID'], $html);
        $antes = [];
        foreach ($pantallas as $url) {
            $antes[$url] = $quitarToken($this->actingAs($this->admin)->get($url)->assertOk()->getContent());
        }

        $this->subir($ruta);
        $this->actingAs($this->admin)->post(route('promesas-futuras.importar.aplicar', $this->corte), ['filas' => [3 => 'persona:'.$ana->id, 4 => 'omitir']])->assertSessionHas('success');
        $this->assertSame(3, PromesaProgramada::distinct()->count('persona_id'));
        $this->actingAs($this->admin)->get(route('promesas-futuras.show', $this->corte))->assertOk(); // consume el aviso de exito
        // Se omite a Pedro (persona nueva): una persona nueva sale en las listas como cualquier alta

        foreach ($antes as $url => $html) {
            $this->assertSame($html, $quitarToken($this->actingAs($this->admin)->get($url)->getContent()), "Cambió {$url}");
        }
    }

    public function test_reimportar_con_nombre_parecido_ya_emparejado_no_pide_decidir_ni_duplica(): void
    {
        $ana = $this->persona('Ana Mora Solís');
        $ruta = $this->excel([
            ['Miembro', 'Clase', 'Misiones'],
            ['Ana Mora', '', 1000],
        ]);

        // Primera vez: el nombre solo se parece y se empareja a mano
        $this->subir($ruta);
        $this->actingAs($this->admin)->get(route('promesas-futuras.importar.revisar', $this->corte))->assertOk()
            ->assertDontSee('Ya está en este corte: se deja como está (elegida', false);
        $this->actingAs($this->admin)->post(route('promesas-futuras.importar.aplicar', $this->corte), ['filas' => [2 => 'persona:'.$ana->id]])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 persona agregada'));
        $antes = PromesaProgramada::orderBy('id')->get()->toArray();

        // Segunda vez, sin tocar nada en la revision: la candidata que ya esta en el corte viene elegida
        $this->subir($ruta);
        $this->actingAs($this->admin)->get(route('promesas-futuras.importar.revisar', $this->corte))->assertOk()
            ->assertSee('Ya está en este corte: se deja como está (elegida Ana Mora Solís)', false)
            ->assertSee('value="persona:'.$ana->id.'" class="h-5 w-5" checked', false);
        $this->actingAs($this->admin)->post(route('promesas-futuras.importar.aplicar', $this->corte), [])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, '0 personas agregadas') && str_contains($m, '1 ya estaba y se dejó igual') && ! str_contains($m, 'persona nueva'));

        $this->assertSame($antes, PromesaProgramada::orderBy('id')->get()->toArray());
        $this->assertSame(1, Persona::count());
    }

    public function test_columna_frecuencia_cada_fila_con_la_suya_y_vacia_toma_la_del_selector(): void
    {
        $ana = $this->persona('Ana Mora');
        $beto = $this->persona('Beto Rojas');
        $carla = $this->persona('Carla Solano');
        $ruta = $this->excel([
            ['Miembro', 'Clase', 'frecuencia', 'Misiones', 'Construcción'],
            ['Ana Mora', '', 'Mensual', 10000, 2000],
            ['Beto Rojas', '', '  QUINCENAL ', 5000, null],
            ['Carla Solano', '', '', 1500, 300],
        ]);

        $lectura = (new LectorExcelPromesas)->leer($ruta);
        $this->assertTrue($lectura['con_frecuencia']);
        $this->assertSame(['Misiones', 'Construcción'], array_column($lectura['categorias'], 'titulo'), 'Frecuencia no es una categoría');
        $this->assertSame(['mensual', 'quincenal', null], array_column($lectura['filas'], 'frecuencia'));

        $this->subir($ruta, 'semanal');
        $this->actingAs($this->admin)->get(route('promesas-futuras.importar.revisar', $this->corte))->assertOk()
            ->assertSee('Mensual')->assertSee('Quincenal')->assertSee('Semanal (vacía: la elegida al subir)')
            ->assertDontSee('Crear la categoría nueva «frecuencia»', false);
        $this->actingAs($this->admin)->post(route('promesas-futuras.importar.aplicar', $this->corte), [])
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, '3 personas agregadas'));

        $frec = fn (Persona $p) => PromesaProgramada::where('persona_id', $p->id)->orderBy('categoria')->get()->map(fn ($x) => [$x->categoria, $x->monto, $x->frecuencia])->all();
        $this->assertSame([['construccion', '2000.00', 'mensual'], ['misiones', '10000.00', 'mensual']], $frec($ana));
        $this->assertSame([['misiones', '5000.00', 'quincenal']], $frec($beto));
        $this->assertSame([['construccion', '300.00', 'semanal'], ['misiones', '1500.00', 'semanal']], $frec($carla));
        $this->assertFalse(TenantCategory::where('tenant_id', $this->iglesia->id)->where('activa', false)->exists());
    }

    public function test_frecuencia_invalida_queda_con_error_y_no_se_aplica_hasta_omitirla(): void
    {
        $ana = $this->persona('Ana Mora');
        $beto = $this->persona('Beto Rojas');
        $ruta = $this->excel([
            ['Miembro', 'Clase', 'Frecuencia', 'Misiones'],
            ['Ana Mora', '', 'Semanal', 1000],
            ['Beto Rojas', '', 'cada mes', 5000],
        ]);

        $this->subir($ruta, 'mensual');
        $this->actingAs($this->admin)->get(route('promesas-futuras.importar.revisar', $this->corte))->assertOk()
            ->assertSee('Frecuencia no válida: «cada mes»', false)->assertSee('Filas con la frecuencia mal escrita (1)', false);

        // Sin decidir: no se guarda nada
        $this->actingAs($this->admin)->from(route('promesas-futuras.importar.revisar', $this->corte))
            ->post(route('promesas-futuras.importar.aplicar', $this->corte), [])
            ->assertSessionHasErrors('filas.3');
        // Elegirle una persona tampoco vale: solo omitir o corregir el archivo
        $this->actingAs($this->admin)->from(route('promesas-futuras.importar.revisar', $this->corte))
            ->post(route('promesas-futuras.importar.aplicar', $this->corte), ['filas' => [3 => 'persona:'.$beto->id]])
            ->assertSessionHasErrors('filas.3');
        $this->assertSame(0, PromesaProgramada::count());

        $this->actingAs($this->admin)->post(route('promesas-futuras.importar.aplicar', $this->corte), ['filas' => [3 => 'omitir']])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 persona agregada') && str_contains($m, '1 omitida'));
        $this->assertSame([[$ana->id, 'semanal']], PromesaProgramada::get()->map(fn ($p) => [$p->persona_id, $p->frecuencia])->all());
    }

    public function test_archivo_sin_columna_frecuencia_se_lee_como_siempre(): void
    {
        $ana = $this->persona('Ana Mora');
        $ruta = $this->excel([
            ['Miembro', 'Clase', 'Misiones', 'Construcción'],
            ['Ana Mora', 'Adultos', 1000, 230.769230769231],
        ]);
        $lectura = (new LectorExcelPromesas)->leer($ruta);
        $this->assertFalse($lectura['con_frecuencia']);
        $this->assertSame(['C', 'D'], array_column($lectura['categorias'], 'col'));
        $this->assertNull($lectura['filas'][0]['frecuencia']);

        $this->subir($ruta, 'quincenal');
        $this->actingAs($this->admin)->get(route('promesas-futuras.importar.revisar', $this->corte))->assertOk()
            ->assertSee('Montos quincenales')->assertDontSee('vacía: la elegida al subir');
        $this->actingAs($this->admin)->post(route('promesas-futuras.importar.aplicar', $this->corte), [])->assertSessionHas('success');
        $this->assertSame([['construccion', '230.77', 'quincenal'], ['misiones', '1000.00', 'quincenal']],
            PromesaProgramada::where('persona_id', $ana->id)->orderBy('categoria')->get()->map(fn ($p) => [$p->categoria, $p->monto, $p->frecuencia])->all());
    }

    public function test_columna_a_una_categoria_existente_u_omitida_y_buscar_a_alguien(): void
    {
        $beto = $this->persona('Roberto Rojas');
        $ruta = $this->excel([
            ['Nombre', 'Clase', 'Campamento', 'Otra'],
            ['Beto R.', '', 2000, 100],
        ]);
        $this->subir($ruta, 'mensual', 'USD');
        $this->actingAs($this->admin)->post(route('promesas-futuras.importar.aplicar', $this->corte), [
            'categorias' => ['C' => 'campa', 'D' => 'omitir'],
            'filas' => [2 => 'buscar'],
            'buscar' => [2 => 'Roberto Rojas #'.$beto->id],
        ])->assertRedirect(route('promesas-futuras.show', $this->corte));

        $this->assertSame([['campa', '2000.00', 'USD', 'mensual']], PromesaProgramada::where('persona_id', $beto->id)->get()->map(fn ($p) => [$p->categoria, $p->monto, $p->moneda, $p->frecuencia])->all());
        $this->assertSame(0, TenantCategory::where('tenant_id', $this->iglesia->id)->where('activa', false)->count());
        $this->assertSame(1, Persona::count());
    }

    public function test_errores_de_formato(): void
    {
        $malo = $this->excel([['Persona', 'Grupo', 'Misiones'], ['Ana', '', 100]]);
        $this->subir($malo)->assertOk()->assertSee('La celda A1 debe decir', false)->assertSee('La celda B1 debe decir', false);

        $texto = $this->excel([['Miembro', 'Clase', 'Misiones'], ['Ana', '', 'mil']]);
        $this->subir($texto)->assertOk()->assertSee('Hay montos que no son números en la celda C2', false);

        $repetida = $this->excel([['Miembro', 'Clase', 'Misiones', 'misiones'], ['Ana', '', 1, 2]]);
        $this->subir($repetida)->assertOk()->assertSee('está repetida', false);

        $sinCategorias = $this->excel([['Miembro', 'Clase'], ['Ana', '']]);
        $this->subir($sinCategorias)->assertOk()->assertSee('No hay columnas de categorías', false);

        $this->assertSame(0, PromesaProgramada::count());
        $this->actingAs($this->admin)->get(route('promesas-futuras.importar.revisar', $this->corte))->assertRedirect(route('promesas-futuras.importar', $this->corte));
    }

    public function test_no_se_importa_a_un_corte_ya_activado(): void
    {
        $this->corte->update(['activado_at' => now()]);
        $ruta = $this->excel([['Miembro', 'Clase', 'Misiones'], ['Ana', '', 100]]);
        $this->subir($ruta)->assertRedirect(route('promesas-futuras.show', $this->corte));
        $this->assertSame(0, Persona::count());
    }

    public function test_ciento_cincuenta_filas_rapido(): void
    {
        $filas = [['Miembro', 'Clase', 'Misiones', 'Construcción', 'Campamento', 'Seminario', 'Alcance Local']];
        for ($i = 1; $i <= 150; $i++) {
            $nombre = 'P '.md5("persona-{$i}"); // nombres que no se parecen entre si
            if ($i % 2 === 0) {
                $this->persona($nombre);
            }
            $filas[] = [$nombre, 'Adultos', 1000 / 4.333, 500, 0, null, 250.5];
        }
        $ruta = $this->excel($filas);
        $inicio = microtime(true);
        $this->subir($ruta);
        $this->actingAs($this->admin)->get(route('promesas-futuras.importar.revisar', $this->corte))->assertOk();
        $this->actingAs($this->admin)->post(route('promesas-futuras.importar.aplicar', $this->corte), ['categorias' => ['E' => 'nueva']])
            ->assertSessionHas('success');
        $segundos = microtime(true) - $inicio;

        $this->assertSame(150, PromesaProgramada::distinct()->count('persona_id'));
        $this->assertLessThan(8, $segundos, "Subir, revisar y aplicar 150 filas tardó {$segundos} s");
    }
}

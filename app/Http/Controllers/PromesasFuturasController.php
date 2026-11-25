<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CortePromesas;
use App\Models\Persona;
use App\Models\PromesaProgramada;
use App\Models\TenantCategory;
use App\Services\Promesas\Importacion\AnalizadorImportacion;
use App\Services\Promesas\Importacion\AplicadorImportacion;
use App\Services\Promesas\Importacion\CategoriasCorte;
use App\Services\Promesas\Importacion\LectorExcelPromesas;
use App\Services\Promesas\Importacion\Normalizador;
use App\Services\Promesas\Importacion\PlantillaPromesas;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apartado «Promesas futuras» (solo Administrador): cortes de promesas programados, su detalle por
 * persona, alta / edicion / baja a mano, importacion de Excel con revision y plantilla.
 *
 * Solo escribe cortes_promesas (programados), promesas_programadas y, al aplicar una importacion o
 * agregar a alguien nuevo, categorias inactivas del corte y personas nuevas. Nunca toca promesas ni
 * compromisos, y no activa cortes: eso lo hace App\Services\Promesas\ActivadorCortes al llegar la fecha.
 */
class PromesasFuturasController extends Controller
{
    public const PALABRA_CONFIRMACION = 'CONFIRMAR';

    public const FRECUENCIAS = ['semanal' => 'Semanal', 'quincenal' => 'Quincenal', 'mensual' => 'Mensual'];

    public const MONEDAS = ['CRC' => 'Colones (₡)', 'USD' => 'Dólares ($)'];

    /** Minutos que se guarda un Excel subido mientras se revisa. */
    private const MINUTOS_REVISION = 120;

    // ---------------------------------------------------------------- Cortes

    public function index(): View
    {
        $cortes = CortePromesas::query()->orderByDesc('inicia_el')->get();
        $vigenteId = $cortes->whereNotNull('activado_at')->sortByDesc('inicia_el')->first()?->id;
        $programadas = PromesaProgramada::whereIn('corte_id', $cortes->pluck('id'))->get(['corte_id', 'persona_id', 'categoria', 'monto', 'moneda', 'frecuencia'])->groupBy('corte_id');
        $nombres = $this->nombresCategorias();

        $filas = $cortes->map(fn (CortePromesas $c) => [
            'corte' => $c,
            'estado' => $c->estaProgramado() ? CortePromesas::PROGRAMADO : ($c->id === $vigenteId ? CortePromesas::VIGENTE : CortePromesas::ANTERIOR),
            'personas' => ($programadas[$c->id] ?? collect())->pluck('persona_id')->unique()->count(),
            'promesas' => ($programadas[$c->id] ?? collect())->count(),
            'totales' => $this->totalesPorCategoria($programadas[$c->id] ?? collect(), $nombres),
        ]);

        return view('promesas-futuras.index', ['filas' => $filas]);
    }

    public function create(): View
    {
        return view('promesas-futuras.form', ['corte' => new CortePromesas, 'mesSugerido' => CarbonImmutable::today()->addYear()->startOfYear()->format('Y-m')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarCorte($request);
        $corte = CortePromesas::create($datos + [
            'tenant_id' => $request->user()->tenant_id,
            'creado_por' => $request->user()->id,
            'categorias_nuevas' => [],
        ]);
        AuditLog::registrar('Corte de promesas creado', ['corte_id' => $corte->id, 'nombre' => $corte->nombre, 'inicia_el' => $corte->inicia_el->toDateString()]);

        return redirect()->route('promesas-futuras.show', $corte)
            ->with('success', "Corte «{$corte->nombre}» creado. Ahora agrega las personas a mano o importa el Excel.");
    }

    public function show(Request $request, CortePromesas $corte): View
    {
        $programadas = $corte->programadas()->with(['persona:id,nombre,pin,activo', 'persona.clasesAsistencia:id,nombre'])->get();
        $categorias = $this->columnasDe($corte, $programadas);
        $q = Normalizador::clave($request->query('q'));

        $personas = $programadas->groupBy('persona_id')->map(function (Collection $suyas) {
            $persona = $suyas->first()->persona;

            return [
                'persona' => $persona,
                'clase' => $persona?->clasesAsistencia->pluck('nombre')->implode(', '),
                'promesas' => $suyas->groupBy('categoria'),
            ];
        })->filter(fn ($p) => $p['persona'] !== null)
            ->sortBy(fn ($p) => Normalizador::clave($p['persona']->nombre))
            ->values();
        $total = $personas->count();
        if ($q !== '') {
            $personas = $personas->filter(fn ($p) => str_contains(Normalizador::clave($p['persona']->nombre), $q) || (string) $p['persona']->pin === $q)->values();
        }

        return view('promesas-futuras.show', [
            'corte' => $corte,
            'categorias' => $categorias,
            'personas' => $personas,
            'totalPersonas' => $total,
            'totales' => $this->totalesPorCategoria($programadas, $categorias->pluck('nombre', 'slug')->all()),
            'categoriasNuevas' => TenantCategory::whereIn('id', $corte->categorias_nuevas ?? [])->where('activa', false)->pluck('nombre'),
            'q' => $request->query('q'),
            'hayRevision' => Cache::has($this->claveRevision($corte)),
        ]);
    }

    public function edit(CortePromesas $corte): View|RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }

        return view('promesas-futuras.form', ['corte' => $corte, 'mesSugerido' => $corte->inicia_el->format('Y-m')]);
    }

    public function update(Request $request, CortePromesas $corte): RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }
        $antes = ['nombre' => $corte->nombre, 'inicia_el' => $corte->inicia_el->toDateString()];
        $corte->update($this->validarCorte($request, $corte));
        AuditLog::registrar('Corte de promesas editado', ['corte_id' => $corte->id, 'antes' => $antes, 'despues' => ['nombre' => $corte->nombre, 'inicia_el' => $corte->inicia_el->toDateString()]]);

        return redirect()->route('promesas-futuras.show', $corte)->with('success', 'Corte actualizado.');
    }

    public function confirmarEliminar(CortePromesas $corte): View|RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }

        return view('promesas-futuras.eliminar', [
            'corte' => $corte,
            'personas' => $corte->programadas()->distinct()->count('persona_id'),
            'promesas' => $corte->programadas()->count(),
            'categorias' => TenantCategory::whereIn('id', $corte->categorias_nuevas ?? [])->where('activa', false)->pluck('nombre'),
        ]);
    }

    public function destroy(Request $request, CortePromesas $corte): RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }
        $request->validate(['confirmacion' => ['required', 'in:'.self::PALABRA_CONFIRMACION]], [
            'confirmacion.required' => 'Escribe '.self::PALABRA_CONFIRMACION.' para eliminar el corte.',
            'confirmacion.in' => 'Escribe '.self::PALABRA_CONFIRMACION.' en mayúsculas, tal cual, para eliminar el corte.',
        ]);

        $detalle = DB::transaction(function () use ($corte) {
            $promesas = $corte->programadas()->count();
            $corte->programadas()->delete();
            // Solo las categorias inactivas creadas para este corte (nunca una que ya rige)
            $categorias = TenantCategory::where('tenant_id', $corte->tenant_id)
                ->whereIn('id', $corte->categorias_nuevas ?? [])->where('activa', false)->get();
            $nombres = $categorias->pluck('nombre')->all();
            TenantCategory::whereIn('id', $categorias->pluck('id'))->delete();
            $corte->delete();

            return ['promesas' => $promesas, 'categorias_borradas' => $nombres];
        });
        Cache::forget($this->claveRevision($corte));
        AuditLog::registrar('Corte de promesas eliminado', ['corte_id' => $corte->id, 'nombre' => $corte->nombre, 'inicia_el' => $corte->inicia_el->toDateString()] + $detalle);

        return redirect()->route('promesas-futuras.index')->with('success', "Corte «{$corte->nombre}» eliminado con sus promesas programadas.");
    }

    // ---------------------------------------------------------------- Personas a mano

    public function agregarPersona(Request $request, CortePromesas $corte): View|RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }
        $q = trim((string) $request->query('q'));
        $resultados = collect();
        if (mb_strlen($q) >= 2) {
            $clave = Normalizador::clave($q);
            $digitos = preg_replace('/\D/', '', $q);
            $resultados = Persona::where('tenant_id', $corte->tenant_id)->orderBy('nombre')->get(['id', 'nombre', 'pin', 'telefono', 'activo'])
                ->filter(function (Persona $p) use ($clave, $digitos) {
                    $nombre = Normalizador::clave($p->nombre);
                    $palabras = array_filter(explode(' ', $clave));

                    return collect($palabras)->every(fn ($w) => str_contains($nombre, $w))
                        || ($digitos !== '' && ((string) $p->pin === $digitos || str_contains(preg_replace('/\D/', '', (string) $p->telefono), $digitos)));
                })->take(30)->values();
        }
        $enCorte = $corte->programadas()->distinct()->pluck('persona_id')->flip();

        return view('promesas-futuras.agregar', compact('corte', 'q', 'resultados', 'enCorte'));
    }

    /** Alta de una persona nueva (misma logica y auditoria que el alta rapida de Personas) y a su formulario. */
    public function crearPersona(Request $request, CortePromesas $corte): RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }
        $v = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'correo' => 'nullable|email|max:255',
        ]);
        $nombre = Normalizador::limpio($v['nombre']);
        $igual = Persona::where('tenant_id', $corte->tenant_id)->get(['id', 'nombre'])
            ->first(fn ($p) => Normalizador::clave($p->nombre) === Normalizador::clave($nombre));
        if ($igual && ! $request->boolean('crear_igual')) {
            return back()->withInput()->with('warning', "Ya existe «{$igual->nombre}». Búscala arriba y elígela; si de verdad es otra persona con el mismo nombre, marca «Es otra persona» y vuelve a guardar.");
        }

        $persona = Persona::create([
            'nombre' => $nombre,
            'telefono' => $v['telefono'] ?? null,
            'correo' => $v['correo'] ?? null,
            'activo' => true,
            'tenant_id' => $corte->tenant_id,
        ]);
        AuditLog::registrar('Alta rápida de persona', ['persona_id' => $persona->id, 'nombre' => $persona->nombre, 'desde' => 'Promesas futuras', 'corte_id' => $corte->id]);

        return redirect()->route('promesas-futuras.personas.edit', [$corte, $persona])
            ->with('success', "Persona agregada: {$persona->nombre}. Ahora escribe lo que promete.");
    }

    public function editarPersona(CortePromesas $corte, Persona $persona): View|RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }
        $this->mismaIglesia($corte, $persona);
        $suyas = $corte->programadas()->where('persona_id', $persona->id)->get();

        return view('promesas-futuras.persona', [
            'corte' => $corte,
            'persona' => $persona,
            'categorias' => CategoriasCorte::de($corte),
            'suyas' => $suyas->groupBy('categoria'),
            'yaEsta' => $suyas->isNotEmpty(),
        ]);
    }

    public function guardarPersona(Request $request, CortePromesas $corte, Persona $persona): RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }
        $this->mismaIglesia($corte, $persona);
        $slugs = CategoriasCorte::de($corte)->pluck('slug')->all();
        $v = $request->validate([
            'promesas' => 'array',
            'promesas.*.categoria' => ['required', Rule::in($slugs)],
            'promesas.*.monto' => 'nullable|numeric|min:0|max:99999999',
            'promesas.*.frecuencia' => ['required', Rule::in(array_keys(self::FRECUENCIAS))],
            'promesas.*.moneda' => ['required', Rule::in(array_keys(self::MONEDAS))],
        ], [
            'promesas.*.monto.numeric' => 'Los montos deben ser números (por ejemplo 5000 o 2500.50).',
            'promesas.*.monto.min' => 'Los montos no pueden ser negativos.',
        ]);
        $filas = collect($v['promesas'] ?? [])->filter(fn ($p) => (float) ($p['monto'] ?? 0) > 0)->values();
        $antes = $corte->programadas()->where('persona_id', $persona->id)->get(['categoria', 'monto', 'moneda', 'frecuencia'])->toArray();

        if ($filas->isEmpty()) {
            return back()->withInput()->with('error', $antes === []
                ? 'Escribe al menos un monto para agregar a la persona al corte.'
                : 'Para quitar a la persona del corte usa el botón «Quitar del corte».');
        }

        DB::transaction(function () use ($corte, $persona, $filas) {
            $corte->programadas()->where('persona_id', $persona->id)->delete();
            foreach ($filas as $p) {
                $corte->programadas()->create([
                    'persona_id' => $persona->id,
                    'categoria' => $p['categoria'],
                    'monto' => round((float) $p['monto'], 2),
                    'moneda' => $p['moneda'],
                    'frecuencia' => $p['frecuencia'],
                ]);
            }
        });
        AuditLog::registrar($antes === [] ? 'Persona agregada a un corte de promesas' : 'Promesas programadas editadas', [
            'corte_id' => $corte->id, 'persona_id' => $persona->id, 'nombre' => $persona->nombre,
            'antes' => $antes, 'despues' => $filas->all(),
        ]);

        return redirect()->route('promesas-futuras.show', $corte)
            ->with('success', ($antes === [] ? 'Agregada al corte: ' : 'Promesas actualizadas: ').$persona->nombre.'.');
    }

    public function quitarPersona(CortePromesas $corte, Persona $persona): RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }
        $this->mismaIglesia($corte, $persona);
        $antes = $corte->programadas()->where('persona_id', $persona->id)->get(['categoria', 'monto', 'moneda', 'frecuencia'])->toArray();
        $corte->programadas()->where('persona_id', $persona->id)->delete();
        AuditLog::registrar('Persona quitada de un corte de promesas', ['corte_id' => $corte->id, 'persona_id' => $persona->id, 'nombre' => $persona->nombre, 'antes' => $antes]);

        return redirect()->route('promesas-futuras.show', $corte)->with('success', "{$persona->nombre} ya no está en este corte.");
    }

    // ---------------------------------------------------------------- Excel

    public function plantilla(): Response
    {
        $iglesia = request()->user()->tenant_id;
        $categorias = TenantCategory::where('tenant_id', $iglesia)->where('activa', true)->where('excluir_de_promesas', false)
            ->orderBy('orden')->orderBy('id')->pluck('nombre')->all();

        return (new PlantillaPromesas)->descargar($categorias);
    }

    public function importar(CortePromesas $corte): View|RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }

        return view('promesas-futuras.importar', ['corte' => $corte]);
    }

    public function subir(Request $request, CortePromesas $corte, LectorExcelPromesas $lector): View|RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }
        $v = $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:2048',
            'frecuencia' => ['required', Rule::in(array_keys(self::FRECUENCIAS))],
            'moneda' => ['required', Rule::in(array_keys(self::MONEDAS))],
        ], [
            'archivo.required' => 'Elige el archivo de Excel.',
            'archivo.mimes' => 'El archivo debe ser de Excel (.xlsx o .xls).',
            'archivo.max' => 'El archivo pesa más de 2 MB. Quita hojas o imágenes que no hagan falta.',
        ]);

        $lectura = $lector->leer($v['archivo']->getRealPath());
        if ($lectura['errores'] !== []) {
            return view('promesas-futuras.importar', ['corte' => $corte, 'erroresArchivo' => $lectura['errores'], 'archivoNombre' => $v['archivo']->getClientOriginalName()]);
        }

        Cache::put($this->claveRevision($corte), [
            'lectura' => $lectura,
            'frecuencia' => $v['frecuencia'],
            'moneda' => $v['moneda'],
            'archivo' => $v['archivo']->getClientOriginalName(),
        ], now()->addMinutes(self::MINUTOS_REVISION));

        return redirect()->route('promesas-futuras.importar.revisar', $corte);
    }

    public function revisar(CortePromesas $corte, AnalizadorImportacion $analizador): View|RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }
        $lote = Cache::get($this->claveRevision($corte));
        if (! $lote) {
            return redirect()->route('promesas-futuras.importar', $corte)->with('warning', 'No hay un archivo en revisión (o pasaron más de dos horas). Súbelo de nuevo.');
        }

        return view('promesas-futuras.revisar', [
            'corte' => $corte,
            'lote' => $lote,
            'analisis' => $analizador->analizar($corte, $lote['lectura']),
            'categorias' => CategoriasCorte::de($corte),
            'todas' => Persona::where('tenant_id', $corte->tenant_id)->orderBy('nombre')->get(['id', 'nombre', 'pin']),
        ]);
    }

    public function aplicar(Request $request, CortePromesas $corte, AnalizadorImportacion $analizador, AplicadorImportacion $aplicador): RedirectResponse
    {
        if ($r = $this->soloProgramado($corte)) {
            return $r;
        }
        $lote = Cache::get($this->claveRevision($corte));
        if (! $lote) {
            return redirect()->route('promesas-futuras.importar', $corte)->with('warning', 'No hay un archivo en revisión (o pasaron más de dos horas). Súbelo de nuevo.');
        }

        // "Buscar a alguien" en una fila: el texto elegido de la lista termina en "#id"
        $decFilas = (array) $request->input('filas', []);
        foreach ((array) $request->input('buscar', []) as $n => $texto) {
            if (($decFilas[$n] ?? null) === 'buscar') {
                $decFilas[$n] = preg_match('/#(\d+)\s*$/', (string) $texto, $m) ? 'persona:'.$m[1] : 'sin-elegir';
            }
        }

        $analisis = $analizador->analizar($corte, $lote['lectura']);
        $r = $aplicador->aplicar($corte, $analisis, (array) $request->input('categorias', []), $decFilas, $lote['frecuencia'], $lote['moneda']);
        Cache::forget($this->claveRevision($corte));

        AuditLog::registrar('Promesas futuras importadas de Excel', ['corte_id' => $corte->id, 'archivo' => $lote['archivo'], 'frecuencia' => $lote['frecuencia'], 'moneda' => $lote['moneda']] + $r);

        $partes = [
            $r['agregadas'].' '.($r['agregadas'] === 1 ? 'persona agregada' : 'personas agregadas'),
            $r['ya_estaban'].' ya '.($r['ya_estaban'] === 1 ? 'estaba y se dejó' : 'estaban y se dejaron').' igual',
            $r['omitidas'].' '.($r['omitidas'] === 1 ? 'omitida' : 'omitidas'),
        ];
        if ($r['personas_creadas'] > 0) {
            $partes[] = $r['personas_creadas'].' '.($r['personas_creadas'] === 1 ? 'persona nueva creada' : 'personas nuevas creadas');
        }
        $mensaje = 'Importación aplicada: '.implode(', ', $partes).'.';
        if ($r['categorias_creadas'] !== []) {
            $mensaje .= ' Categorías nuevas (se activan cuando el corte entre en vigor): '.implode(', ', $r['categorias_creadas']).'.';
        }

        return redirect()->route('promesas-futuras.show', $corte)->with('success', $mensaje);
    }

    public function descartar(CortePromesas $corte): RedirectResponse
    {
        Cache::forget($this->claveRevision($corte));

        return redirect()->route('promesas-futuras.show', $corte)->with('info', 'Se descartó el archivo: no se guardó nada.');
    }

    // ---------------------------------------------------------------- Apoyo

    /** @return array{nombre: string, inicia_el: string} */
    private function validarCorte(Request $request, ?CortePromesas $corte = null): array
    {
        $request->validate([
            'nombre' => 'required|string|max:120',
            'mes' => ['required', 'date_format:Y-m'],
        ], [
            'nombre.required' => 'Ponle un nombre al corte (por ejemplo «Promesas 2027»).',
            'mes.required' => 'Elige el mes en que entran en vigor.',
            'mes.date_format' => 'Elige el mes en que entran en vigor.',
        ]);
        $inicio = CarbonImmutable::createFromFormat('Y-m-d', $request->input('mes').'-01')->startOfDay();
        $errores = [];
        if ($inicio->lte(CarbonImmutable::today())) {
            $errores['mes'] = 'El corte tiene que empezar el día 1 de un mes que todavía no llegó.';
        } elseif (CortePromesas::where('tenant_id', $request->user()->tenant_id)->whereDate('inicia_el', $inicio->toDateString())
            ->when($corte, fn ($q) => $q->where('id', '!=', $corte->id))->exists()) {
            $errores['mes'] = 'Ya hay un corte que empieza el '.$inicio->format('d/m/Y').'. Edita ese o elige otro mes.';
        }
        if ($errores !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errores);
        }

        return ['nombre' => Normalizador::limpio($request->input('nombre')), 'inicia_el' => $inicio->toDateString()];
    }

    private function soloProgramado(CortePromesas $corte): ?RedirectResponse
    {
        if ($corte->estaProgramado()) {
            return null;
        }

        return redirect()->route('promesas-futuras.show', $corte)->with('warning', 'Este corte ya entró en vigor: se puede ver pero no cambiar. Para cambiar una promesa de hoy, usa la ficha de la persona.');
    }

    private function mismaIglesia(CortePromesas $corte, Persona $persona): void
    {
        abort_unless((int) $persona->tenant_id === (int) $corte->tenant_id, 404);
    }

    private function claveRevision(CortePromesas $corte): string
    {
        return 'promesas-futuras.revision.'.request()->user()->id.'.'.$corte->id;
    }

    /** @return array<string, string> slug => nombre de todas las categorias de la iglesia */
    private function nombresCategorias(): array
    {
        return TenantCategory::where('tenant_id', request()->user()->tenant_id)->pluck('nombre', 'slug')->all();
    }

    /**
     * Columnas del detalle: las categorias del corte y cualquier otra que tenga promesas en el.
     *
     * @return Collection<int, TenantCategory>
     */
    private function columnasDe(CortePromesas $corte, Collection $programadas): Collection
    {
        $cats = CategoriasCorte::de($corte);
        $faltan = $programadas->pluck('categoria')->unique()->diff($cats->pluck('slug'));
        if ($faltan->isNotEmpty()) {
            $cats = $cats->concat(TenantCategory::where('tenant_id', $corte->tenant_id)->whereIn('slug', $faltan)->get());
        }

        return $cats->values();
    }

    /**
     * Totales por categoria, separados por moneda y frecuencia ("₡120.000 semanal").
     *
     * @param  array<string, string>  $nombres
     * @return list<array{categoria: string, nombre: string, moneda: string, frecuencia: string, total: float, personas: int}>
     */
    private function totalesPorCategoria(Collection $programadas, array $nombres): array
    {
        return $programadas->groupBy(fn ($p) => $p->categoria.'|'.$p->moneda.'|'.$p->frecuencia)
            ->map(function (Collection $g) use ($nombres) {
                $p = $g->first();

                return [
                    'categoria' => $p->categoria,
                    'nombre' => $nombres[$p->categoria] ?? $p->categoria,
                    'moneda' => $p->moneda,
                    'frecuencia' => $p->frecuencia,
                    'total' => (float) $g->sum('monto'),
                    'personas' => $g->pluck('persona_id')->unique()->count(),
                ];
            })->sortBy('nombre')->values()->all();
    }
}

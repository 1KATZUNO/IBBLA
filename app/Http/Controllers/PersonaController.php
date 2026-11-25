<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Persona;
use App\Models\Promesa;
use App\Models\Compromiso;
use App\Models\ClaseAsistencia;
use App\Models\Ministerio;
use App\Models\User;
use App\Services\ReporteGeneralPdf;
use App\Services\ReporteGeneralService;
use App\Services\Reportes\Dinero;
use App\Services\Reportes\MotorReportes;
use App\Services\Reportes\PeriodoReporte;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PersonaController extends Controller
{
    /**
     * Regla "el ministerio existe", limitada a la iglesia del usuario: con un simple
     * exists:ministerios,id se podría asignar un ministerio de otra iglesia con solo
     * conocer su id. El super admin (sin iglesia) no tiene ese límite.
     */
    private function reglaMinisterioExiste(): \Illuminate\Validation\Rules\Exists
    {
        $regla = \Illuminate\Validation\Rule::exists('ministerios', 'id');

        if ($tenantId = auth()->user()->tenant_id) {
            $regla->where('tenant_id', $tenantId);
        }

        return $regla;
    }

    /**
     * QA-018: el usuario que nace desde la ficha de la persona lleva el mismo rol dinámico que los
     * creados en Usuarios (antes quedaba con tenant_role_id nulo).
     */
    private function rolMiembroId(): ?int
    {
        $id = tenant()?->roles()->where('slug', 'miembro')->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function index(Request $request)
    {
        $query = Persona::withCount(['sobres', 'promesas']);
        
        // Búsqueda por nombre, pin o teléfono
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', '%' . $buscar . '%')
                  ->orWhere('pin', $buscar)
                  ->orWhere('telefono', 'like', '%' . $buscar . '%');
            });
        }

        // Activas primero y por orden alfabético (antes salían por orden de alta, con las inactivas arriba)
        $personas = $query->orderByDesc('activo')->orderBy('nombre')->paginate(20);
        $personasInactivas = Persona::where('activo', false)->count();
        return view('personas.index', compact('personas', 'personasInactivas'));
    }

    public function create()
    {
        $categorias = tenant_categories(['excluir_de_promesas' => false]);
        $clases = ClaseAsistencia::activas()->ordenadas()->get();
        $clasesAsignadas = [];
        $ministerios = Ministerio::activos()->ordenados()->get();
        $ministeriosAsignados = [];
        return view('personas.create', compact('categorias', 'clases', 'clasesAsignadas', 'ministerios', 'ministeriosAsignados'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:255|unique:users,email',
            'fecha_nacimiento' => 'nullable|date',
            'pin' => 'nullable|string|max:20',
            'clases' => 'nullable|array|max:4',
            'clases.*.clase_id' => 'required|exists:clases_asistencia,id',
            'clases.*.es_maestro' => 'boolean',
            'ministerios' => 'nullable|array',
            'ministerios.*.ministerio_id' => ['required', $this->reglaMinisterioExiste()],
            'ministerios.*.es_lider' => 'boolean',
            'password' => 'required_with:correo|nullable|string|min:8',
            'activo' => 'boolean',
            'notas' => 'nullable|string',
            'promesas' => 'nullable|array',
            'promesas.*.categoria' => 'required|string',
            'promesas.*.monto' => 'required|numeric|min:0',
            'promesas.*.frecuencia' => 'required|in:semanal,quincenal,mensual',
        ], [
            'correo.unique' => 'Este correo electrónico ya está registrado en el sistema. Por favor, usa otro correo.',
            'correo.email' => 'El formato del correo electrónico no es válido.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.required_with' => 'Debes proporcionar una contraseña cuando ingresas un correo electrónico.',
        ]);

        // Si hay correo y contraseña, crear usuario miembro
        $user = null;
        if (!empty($validated['correo']) && !empty($validated['password'])) {
            $user = User::create([
                'name' => $validated['nombre'],
                'email' => $validated['correo'],
                'password' => Hash::make($validated['password']),
                'rol' => 'miembro',
                'tenant_id' => auth()->user()->tenant_id,
                'tenant_role_id' => $this->rolMiembroId(),
            ]);
        }

        // Crear persona
        $personaData = [
            'nombre' => $validated['nombre'],
            'telefono' => $validated['telefono'] ?? null,
            'correo' => $validated['correo'] ?? null,
            'fecha_nacimiento' => $validated['fecha_nacimiento'] ?? null,
            'pin' => $validated['pin'] ?? null,
            'password' => !empty($validated['password']) ? Hash::make($validated['password']) : null,
            'user_id' => $user ? $user->id : null,
            'activo' => $validated['activo'] ?? true,
            'notas' => $validated['notas'] ?? null,
        ];

        $persona = Persona::create($personaData);

        // Sync clases via pivot
        if (!empty($validated['clases'])) {
            $syncData = [];
            foreach ($validated['clases'] as $claseEntry) {
                $syncData[$claseEntry['clase_id']] = [
                    'es_maestro' => !empty($claseEntry['es_maestro']),
                ];
            }
            $persona->clasesAsistencia()->sync($syncData);
        }

        // Sync ministerios via pivot
        if (!empty($validated['ministerios'])) {
            $syncMin = [];
            foreach ($validated['ministerios'] as $m) {
                $syncMin[$m['ministerio_id']] = [
                    'es_lider' => !empty($m['es_lider']),
                    'desde' => now()->toDateString(),
                ];
            }
            $persona->ministerios()->sync($syncMin);
        }

        // Guardar promesas si existen
        if ($request->has('promesas')) {
            foreach ($request->promesas as $promesaData) {
                if (!empty($promesaData['monto']) && $promesaData['monto'] > 0) {
                    $persona->promesas()->create($promesaData);
                }
            }
        }

        return redirect()->route('personas.index')
            ->with('success', 'Persona registrada correctamente.' . ($user ? ' Se creó acceso como miembro.' : ''));
    }

    public function show(Request $request, Persona $persona)
    {
        // Query de sobres con filtros
        $sobresQuery = $persona->sobres()->with(['detalles', 'culto']);
        
        // Aplicar filtros
        if ($request->filled('mes') && $request->mes !== 'todos') {
            $sobresQuery->whereHas('culto', function($q) use ($request) {
                $q->whereMonth('fecha', $request->mes);
            });
        }
        
        if ($request->filled('año') && $request->año !== 'todos') {
            $sobresQuery->whereHas('culto', function($q) use ($request) {
                $q->whereYear('fecha', $request->año);
            });
        }
        
        if ($request->filled('fecha_inicio')) {
            $sobresQuery->whereHas('culto', function($q) use ($request) {
                $q->where('fecha', '>=', $request->fecha_inicio);
            });
        }
        
        if ($request->filled('fecha_fin')) {
            $sobresQuery->whereHas('culto', function($q) use ($request) {
                $q->where('fecha', '<=', $request->fecha_fin);
            });
        }
        
        $persona->setRelation('sobres', $sobresQuery->get());
        $persona->load('promesas');
        
        // Obtener años disponibles de los sobres
        $añosDisponibles = $persona->sobres()->whereHas('culto')
            ->join('cultos', 'sobres.culto_id', '=', 'cultos.id')
            ->selectRaw('YEAR(cultos.fecha) as año')
            ->groupBy('año')
            ->orderBy('año', 'desc')
            ->pluck('año');
        
        // Lo dado en el año y el estado de compromisos: las mismas cifras del estado de cuenta (motor
        // único, corte hoy, dólares convertidos). Antes la ficha sumaba total_declarado de todos los
        // años sin convertir dólares (QA-033).
        $estadoCuenta = app(\App\Services\InformePersonaService::class)->datos($persona);

        return view('personas.show', compact('persona', 'estadoCuenta', 'añosDisponibles'));
    }

    public function edit(Persona $persona)
    {
        $persona->load(['promesas', 'clasesAsistencia', 'ministerios']);
        $categorias = tenant_categories(['excluir_de_promesas' => false]);
        $clases = ClaseAsistencia::activas()->ordenadas()->get();
        $clasesAsignadas = $persona->clasesAsistencia->map(function ($clase) {
            return ['clase_id' => $clase->id, 'es_maestro' => $clase->pivot->es_maestro];
        })->values()->toArray();
        // Un ministerio desactivado deja de ofrecerse, salvo a quienes ya están en él: si no
        // apareciera en su selector, el <select> quedaría vacío, fallaría "ministerio_id
        // required" y no se podría guardar la ficha de esa persona.
        $ministerios = Ministerio::where(function ($q) use ($persona) {
            $q->where('activo', true)->orWhereIn('id', $persona->ministerios->pluck('id'));
        })->ordenados()->get();
        $ministerios->each(function ($m) {
            if (! $m->activo) {
                $m->nombre .= ' (inactivo)'; // solo para mostrar: no se guarda
            }
        });
        $ministeriosAsignados = $persona->ministerios->map(function ($m) {
            return ['ministerio_id' => $m->id, 'es_lider' => (bool) $m->pivot->es_lider];
        })->values()->toArray();
        return view('personas.edit', compact('persona', 'categorias', 'clases', 'clasesAsignadas', 'ministerios', 'ministeriosAsignados'));
    }

    public function update(Request $request, Persona $persona)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|unique:users,email,' . ($persona->user_id ?? 'NULL'),
            'fecha_nacimiento' => 'nullable|date',
            'pin' => 'nullable|string|max:20',
            'clases' => 'nullable|array|max:4',
            'clases.*.clase_id' => 'required|exists:clases_asistencia,id',
            'clases.*.es_maestro' => 'boolean',
            'ministerios' => 'nullable|array',
            'ministerios.*.ministerio_id' => ['required', $this->reglaMinisterioExiste()],
            'ministerios.*.es_lider' => 'boolean',
            'password' => $persona->user_id ? 'nullable|string|min:8' : 'required_with:correo|nullable|string|min:8',
            'activo' => 'boolean',
            'notas' => 'nullable|string',
            'promesas' => 'nullable|array',
            'promesas.*.categoria' => 'required|string',
            'promesas.*.monto' => 'required|numeric|min:0',
            'promesas.*.frecuencia' => 'required|in:semanal,quincenal,mensual',
        ], [
            'correo.unique' => 'Este correo electrónico ya está registrado en el sistema. Por favor, usa otro correo.',
            'correo.email' => 'El formato del correo electrónico no es válido.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.required_with' => 'Debes proporcionar una contraseña cuando ingresas un correo electrónico.',
        ]);

        // Manejar cambios en correo y usuario
        $updateUser = false;
        $createUser = false;
        
        // Si hay correo nuevo y no tiene usuario, crear usuario
        if (!empty($validated['correo']) && !$persona->user_id && !empty($validated['password'])) {
            $user = User::create([
                'name' => $validated['nombre'],
                'email' => $validated['correo'],
                'password' => Hash::make($validated['password']),
                'rol' => 'miembro',
                'tenant_id' => auth()->user()->tenant_id,
                'tenant_role_id' => $this->rolMiembroId(),
            ]);
            $persona->user_id = $user->id;
            $createUser = true;
        }
        // Si tiene usuario y cambió el correo o contraseña, actualizar usuario
        elseif ($persona->user_id) {
            $user = $persona->user;
            if ($user) {
                $userUpdates = [];
                if (!empty($validated['correo']) && $user->email !== $validated['correo']) {
                    $userUpdates['email'] = $validated['correo'];
                }
                if (!empty($validated['password'])) {
                    $userUpdates['password'] = Hash::make($validated['password']);
                }
                if (!empty($validated['nombre']) && $user->name !== $validated['nombre']) {
                    $userUpdates['name'] = $validated['nombre'];
                }
                if (!empty($userUpdates)) {
                    $user->update($userUpdates);
                    $updateUser = true;
                }
            }
        }

        // Actualizar persona
        $personaData = [
            'nombre' => $validated['nombre'],
            'telefono' => $validated['telefono'] ?? null,
            'correo' => $validated['correo'] ?? null,
            'fecha_nacimiento' => $validated['fecha_nacimiento'] ?? null,
            'pin' => $validated['pin'] ?? null,
            'activo' => $validated['activo'] ?? true,
            'notas' => $validated['notas'] ?? null,
        ];

        // Solo actualizar password si se proporcionó uno nuevo
        if (!empty($validated['password'])) {
            $personaData['password'] = Hash::make($validated['password']);
        }

        $persona->update($personaData);

        // Sync clases via pivot
        $syncData = [];
        if (!empty($validated['clases'])) {
            foreach ($validated['clases'] as $claseEntry) {
                $syncData[$claseEntry['clase_id']] = [
                    'es_maestro' => !empty($claseEntry['es_maestro']),
                ];
            }
        }
        $persona->clasesAsistencia()->sync($syncData);

        // Sync ministerios via pivot. Mantener `desde` original si ya existía.
        $syncMin = [];
        $existingMin = $persona->ministerios()->get()->keyBy('id');
        if (! empty($validated['ministerios'])) {
            foreach ($validated['ministerios'] as $m) {
                $id = $m['ministerio_id'];
                $prev = $existingMin->get($id);
                $syncMin[$id] = [
                    'es_lider' => ! empty($m['es_lider']),
                    'desde' => $prev?->pivot->desde ?? now()->toDateString(),
                    'hasta' => null,
                ];
            }
        }
        $persona->ministerios()->sync($syncMin);

        // QA-036: las promesas se actualizan en sitio (conservan id e historial)
        $this->sincronizarPromesas($persona, (array) $request->input('promesas', []));

        return redirect()->route('personas.index')
            ->with('success', 'Persona actualizada correctamente.' . 
                ($createUser ? ' Se creó acceso como miembro.' : '') . 
                ($updateUser ? ' Se actualizó el acceso de miembro.' : ''));
    }

    /**
     * QA-036: guardar la ficha ya no borra y recrea las promesas. Por categoría: si la persona ya
     * prometía, se actualiza esa misma promesa (solo si cambió); si no, se crea; las categorías que
     * quedaron sin monto se eliminan. Si había dos promesas de la misma categoría (datos viejos) se
     * conserva la primera, como antes quedaba una sola.
     *
     * @param  array<int|string, array<string, mixed>>  $enviadas
     */
    private function sincronizarPromesas(Persona $persona, array $enviadas): void
    {
        $existentes = $persona->promesas()->orderBy('id')->get()->toBase()->groupBy('categoria');
        $conservadas = [];
        $idsConservados = [];

        foreach ($enviadas as $datos) {
            $categoria = (string) ($datos['categoria'] ?? '');
            $monto = (float) ($datos['monto'] ?? 0);
            if ($categoria === '' || $monto <= 0 || in_array($categoria, $conservadas, true)) {
                continue;
            }

            $valores = [
                'monto' => $monto,
                'moneda' => in_array($datos['moneda'] ?? null, ['CRC', 'USD'], true) ? $datos['moneda'] : 'CRC',
                'frecuencia' => $datos['frecuencia'] ?? 'mensual',
            ];

            $promesa = $existentes->get($categoria)?->first();
            if ($promesa) {
                $promesa->fill($valores);
                if ($promesa->isDirty()) {
                    $promesa->save();
                }
            } else {
                $promesa = $persona->promesas()->create($valores + ['categoria' => $categoria]);
            }
            $conservadas[] = $categoria;
            $idsConservados[] = $promesa->id;
        }

        $persona->promesas()->whereNotIn('id', $idsConservados)->get()->each->delete();
    }

    public function destroy(Persona $persona)
    {
        // Eliminar todas las promesas de la persona
        $persona->promesas()->delete();
        
        // Eliminar todos los compromisos de la persona
        $persona->compromisos()->delete();
        
        // Los sobres NO se eliminan porque el dinero ya fue dado y debe quedar registrado
        // Solo se rompe la relación
        $persona->sobres()->update(['persona_id' => null]);
        
        // Si tiene usuario asociado, eliminarlo también
        if ($persona->user_id) {
            $user = $persona->user;
            if ($user) {
                $user->delete();
            }
        }
        
        // Finalmente eliminar la persona
        $persona->delete();

        return redirect()->route('personas.index')
            ->with('success', 'Persona y sus promesas eliminadas correctamente. Los sobres quedan registrados.');
    }

    /**
     * Pantalla de alta rapida (nombre, telefono y correo opcionales) para quien registra sobres: el
     * tesorero agrega personas sin ver la lista ni las fichas (decision del cliente 13/10).
     */
    public function altaRapida()
    {
        return view('personas.alta-rapida');
    }

    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'correo' => 'nullable|email|max:255',
        ]);

        $persona = Persona::create([
            'nombre' => trim($validated['nombre']),
            'telefono' => $validated['telefono'] ?? null,
            'correo' => $validated['correo'] ?? null,
            'activo' => true,
        ]);

        AuditLog::registrar('Alta rápida de persona', ['persona_id' => $persona->id, 'nombre' => $persona->nombre]);

        if (! $request->expectsJson()) {
            return back()->with('success', "Persona agregada: {$persona->nombre}.");
        }

        return response()->json([
            'success' => true,
            'persona' => $persona->only(['id', 'nombre', 'pin', 'telefono']),
        ]);
    }

    /** Texto que hay que escribir para ejecutar una acción masiva (QA-034). */
    public const PALABRA_CONFIRMACION = 'CONFIRMAR';

    /**
     * QA-034: pantalla de confirmación de las acciones masivas de Mantenimiento (solo admin). Dice qué se
     * va a borrar y cuánto, y pide escribir CONFIRMAR; reemplaza el confirm() del navegador.
     */
    public function confirmarMantenimiento(string $accion)
    {
        return view('personas.mantenimiento', ['accion' => $accion] + $this->resumenMantenimiento($accion));
    }

    /** @return array<string, mixed> lo que afectaría la acción, con los mismos filtros que la ejecución */
    private function resumenMantenimiento(string $accion): array
    {
        if ($accion === 'inactivas') {
            $personas = Persona::where('activo', false)->orderBy('nombre')->get(['id', 'nombre', 'user_id']);
            $ids = $personas->pluck('id');

            return [
                'personas' => $personas,
                'cantidadPersonas' => $personas->count(),
                'cantidadPromesas' => Promesa::whereIn('persona_id', $ids)->count(),
                'cantidadCompromisos' => Compromiso::whereIn('persona_id', $ids)->count(),
                'cantidadUsuarios' => $personas->whereNotNull('user_id')->count(),
                'cantidadSobres' => \App\Models\Sobre::whereIn('persona_id', $ids)->count(),
            ];
        }

        $ids = Persona::pluck('id');

        return [
            'personas' => collect(),
            'cantidadPersonas' => Promesa::whereIn('persona_id', $ids)->distinct()->count('persona_id'),
            'cantidadPromesas' => Promesa::whereIn('persona_id', $ids)->count(),
            'cantidadCompromisos' => Compromiso::whereIn('persona_id', $ids)->count(),
            'cantidadUsuarios' => 0,
            'cantidadSobres' => 0,
        ];
    }

    /** Valida la palabra de confirmación (QA-034). */
    private function validarConfirmacion(Request $request): void
    {
        $request->validate([
            'confirmacion' => ['required', 'in:'.self::PALABRA_CONFIRMACION],
        ], [
            'confirmacion.required' => 'Escriba '.self::PALABRA_CONFIRMACION.' para continuar.',
            'confirmacion.in' => 'Escriba '.self::PALABRA_CONFIRMACION.' en mayúsculas, tal cual, para continuar.',
        ]);
    }

    /**
     * Elimina las personas inactivas con sus promesas, compromisos y usuarios (los sobres quedan, sin
     * persona). QA-034: solo con la palabra de confirmación y con auditoría de quiénes se borraron.
     */
    public function limpiarInactivas(Request $request)
    {
        $this->validarConfirmacion($request);
        $resumen = $this->resumenMantenimiento('inactivas');
        $personasInactivas = Persona::where('activo', false)->get();
        $count = $personasInactivas->count();

        DB::transaction(function () use ($personasInactivas) {
            foreach ($personasInactivas as $persona) {
                $persona->promesas()->delete();
                $persona->compromisos()->delete();
                // Desvincular sobres (no eliminarlos)
                $persona->sobres()->update(['persona_id' => null]);
                if ($persona->user_id && ($user = $persona->user)) {
                    $user->delete();
                }
                $persona->delete();
            }
        });

        AuditLog::registrar('Eliminar personas inactivas', [
            'personas' => $resumen['personas']->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre])->values()->all(),
            'cantidad_personas' => $count,
            'promesas_borradas' => $resumen['cantidadPromesas'],
            'compromisos_borrados' => $resumen['cantidadCompromisos'],
            'usuarios_borrados' => $resumen['cantidadUsuarios'],
            'sobres_sin_persona' => $resumen['cantidadSobres'],
        ], 'personas.limpiar-inactivas');

        return redirect()->route('personas.index')
            ->with('success', "Se eliminaron {$count} ".($count === 1 ? 'persona inactiva' : 'personas inactivas').' con sus promesas y compromisos. Los sobres quedan registrados. Quedó en la auditoría.');
    }

    /**
     * Borra todas las promesas y compromisos de la iglesia (los sobres se mantienen como historial).
     * QA-034: solo con la palabra de confirmación y con auditoría de cuánto se borró.
     */
    public function resetearPromesas(Request $request)
    {
        $this->validarConfirmacion($request);
        $resumen = $this->resumenMantenimiento('promesas');

        // Solo personas de la iglesia actual (scope por iglesia)
        $personaIds = Persona::pluck('id');
        DB::transaction(function () use ($personaIds) {
            Compromiso::whereIn('persona_id', $personaIds)->delete();
            Promesa::whereIn('persona_id', $personaIds)->delete();
        });

        AuditLog::registrar('Borrar todas las promesas', [
            'personas_con_promesa' => $resumen['cantidadPersonas'],
            'promesas_borradas' => $resumen['cantidadPromesas'],
            'compromisos_borrados' => $resumen['cantidadCompromisos'],
        ], 'personas.resetear-promesas');

        return redirect()->route('personas.index')
            ->with('success', 'Se borraron todas las promesas y compromisos. Los sobres dados se mantienen como historial. Quedó en la auditoría.');
    }

    /**
     * Reinicia los compromisos de una persona desde una fecha específica
     * Borra todo el historial anterior y comienza de cero desde el mes/año seleccionado
     */
    public function reiniciarCompromisos(Request $request, Persona $persona)
    {
        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
        ]);

        $fechaInicio = \Carbon\Carbon::parse($validated['fecha_inicio']);
        
        // Eliminar todos los compromisos anteriores a la fecha de inicio
        $persona->compromisos()
            ->where(function($query) use ($fechaInicio) {
                $query->where('año', '<', $fechaInicio->year)
                    ->orWhere(function($q) use ($fechaInicio) {
                        $q->where('año', '=', $fechaInicio->year)
                          ->where('mes', '<', $fechaInicio->month);
                    });
            })
            ->delete();

        // Resetear el compromiso del mes de inicio (si existe) para que saldo_anterior = 0
        $persona->compromisos()
            ->where('año', $fechaInicio->year)
            ->where('mes', $fechaInicio->month)
            ->update(['saldo_anterior' => 0, 'saldo_actual' => 0]);

        return redirect()->route('personas.edit', $persona)
            ->with('success', 'Compromisos reiniciados correctamente desde ' . $fechaInicio->locale('es')->translatedFormat('F \d\e Y') . '.');
    }

    /**
     * Limpia completamente todo: promesas, compromisos e historial
     * Deja a la persona en estado "limpio" sin ninguna deuda ni compromiso
     */
    public function limpiarTodo(Persona $persona)
    {
        // Eliminar todas las promesas
        $persona->promesas()->delete();
        
        // Eliminar todos los compromisos
        $persona->compromisos()->delete();

        return redirect()->route('personas.edit', $persona)
            ->with('success', 'Todas las promesas, compromisos e historial han sido eliminados.');
    }

    /**
     * Genera un reporte PDF de contribuciones acumuladas desde enero hasta el mes actual: una fila
     * por persona activa que dio algo, una columna por categoria de la iglesia.
     *
     * Las cifras salen del motor unico (Reportes\MotorReportes): antes este reporte sumaba los
     * montos de los sobres en dolares como si fueran colones; ahora van convertidos con el tipo de
     * cambio del sobre, igual que en el estado de cuenta y el Reporte General.
     */
    public function reportePdf(Request $request, MotorReportes $motor, \App\Services\Reportes\Exportaciones\ContribucionesPdf $pdf)
    {
        $validated = $request->validate([
            'accion' => 'required|in:ver,descargar',
        ]);

        // Desde enero del anio actual hasta hoy (el motor no cuenta nada posterior a hoy)
        $anioActual = now()->year;
        $mesActual = now()->month;
        $personas = Persona::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'activo']);
        $resultado = $motor->calcular(PeriodoReporte::deMeses($anioActual, 1, $mesActual), $personas);

        $contenido = $pdf->generar($resultado, $personas);
        $nombreArchivo = 'reporte-contribuciones-' . $anioActual . '-' . str_pad($mesActual, 2, '0', STR_PAD_LEFT) . '.pdf';
        $disposicion = $validated['accion'] === 'descargar' ? 'attachment' : 'inline';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposicion, $nombreArchivo),
            'Content-Length' => strlen($contenido),
        ]);
    }

    /**
     * Genera el reporte general (dado vs esperado por mes y categoria) de enero
     * al mes actual.
     *
     * Antes la vista pdfs/reporte-general se maquetaba con DomPDF: con un anio de
     * datos (~140 personas) tardaba ~4 minutos y nginx respondia 504. Los datos
     * los arma ReporteGeneralService (las mismas reglas de siempre) y el PDF lo
     * dibuja ReporteGeneralPdf directamente sobre el canvas de DomPDF.
     */
    public function reporteGeneral(Request $request, ReporteGeneralService $servicio, ReporteGeneralPdf $pdf)
    {
        $validated = $request->validate([
            'accion' => 'required|in:ver,descargar',
        ]);

        $ahora = now();
        $anioActual = $ahora->year;
        $mesActual = $ahora->month;

        $reporte = $servicio->generar($anioActual, $mesActual);
        $contenido = $pdf->render($reporte, $anioActual, $mesActual, tenant_pdf_data(), $ahora);

        $nombreArchivo = 'reporte-general-' . $anioActual . '-' . str_pad($mesActual, 2, '0', STR_PAD_LEFT) . '.pdf';
        $disposicion = $validated['accion'] === 'descargar' ? 'attachment' : 'inline';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposicion, $nombreArchivo),
            'Content-Length' => strlen($contenido),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Promesa;
use App\Models\Compromiso;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class PersonaController extends Controller
{
    public function index(Request $request)
    {
        $query = Persona::withCount(['sobres', 'promesas']);
        
        // Búsqueda por nombre
        if ($request->filled('buscar')) {
            $query->where('nombre', 'like', '%' . $request->buscar . '%');
        }
        
        $personas = $query->paginate(20);
        $personasInactivas = Persona::where('activo', false)->count();
        return view('personas.index', compact('personas', 'personasInactivas'));
    }

    public function create()
    {
        return view('personas.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:255|unique:users,email',
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
            ]);
        }

        // Crear persona
        $personaData = [
            'nombre' => $validated['nombre'],
            'telefono' => $validated['telefono'] ?? null,
            'correo' => $validated['correo'] ?? null,
            'password' => !empty($validated['password']) ? Hash::make($validated['password']) : null,
            'user_id' => $user ? $user->id : null,
            'activo' => $validated['activo'] ?? true,
            'notas' => $validated['notas'] ?? null,
        ];

        $persona = Persona::create($personaData);

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
        
        // Calcular cumplimiento de promesas
        $promesasConEstatus = $persona->promesas->map(function ($promesa) use ($persona) {
            $montoPagado = $persona->sobres()
                ->whereHas('detalles', function ($query) use ($promesa) {
                    $query->where('categoria', $promesa->categoria);
                })
                ->whereMonth('created_at', Carbon::now()->month)
                ->get()
                ->sum(function ($sobre) use ($promesa) {
                    return $sobre->detalles()
                        ->where('categoria', $promesa->categoria)
                        ->sum('monto');
                });

            return [
                'promesa' => $promesa,
                'pagado' => $montoPagado,
                'faltante' => max(0, $promesa->monto - $montoPagado),
                'cumplido' => $montoPagado >= $promesa->monto,
            ];
        });

        return view('personas.show', compact('persona', 'promesasConEstatus', 'añosDisponibles'));
    }

    public function edit(Persona $persona)
    {
        $persona->load('promesas');
        return view('personas.edit', compact('persona'));
    }

    public function update(Request $request, Persona $persona)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|unique:users,email,' . ($persona->user_id ?? 'NULL'),
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
            'activo' => $validated['activo'] ?? true,
            'notas' => $validated['notas'] ?? null,
        ];

        // Solo actualizar password si se proporcionó uno nuevo
        if (!empty($validated['password'])) {
            $personaData['password'] = Hash::make($validated['password']);
        }

        $persona->update($personaData);

        // Sincronizar promesas
        $persona->promesas()->delete(); // Eliminar promesas anteriores
        if ($request->has('promesas')) {
            foreach ($request->promesas as $promesaData) {
                if (!empty($promesaData['monto']) && $promesaData['monto'] > 0) {
                    $persona->promesas()->create($promesaData);
                }
            }
        }

        return redirect()->route('personas.index')
            ->with('success', 'Persona actualizada correctamente.' . 
                ($createUser ? ' Se creó acceso como miembro.' : '') . 
                ($updateUser ? ' Se actualizó el acceso de miembro.' : ''));
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

    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255'
        ]);

        $persona = Persona::create([
            'nombre' => $validated['nombre'],
            'activo' => true
        ]);

        return response()->json([
            'success' => true,
            'persona' => $persona
        ]);
    }

    /**
     * Limpia todas las personas inactivas y sus promesas/compromisos
     */
    public function limpiarInactivas()
    {
        // Obtener personas inactivas
        $personasInactivas = Persona::where('activo', false)->get();
        $count = $personasInactivas->count();
        
        foreach ($personasInactivas as $persona) {
            // Eliminar promesas
            $persona->promesas()->delete();
            
            // Eliminar compromisos
            $persona->compromisos()->delete();
            
            // Desvincular sobres (no eliminarlos)
            $persona->sobres()->update(['persona_id' => null]);
            
            // Eliminar usuario asociado si existe
            if ($persona->user_id) {
                $user = $persona->user;
                if ($user) {
                    $user->delete();
                }
            }
            
            // Eliminar persona
            $persona->delete();
        }
        
        return redirect()->route('personas.index')
            ->with('success', "Se eliminaron {$count} personas inactivas y sus promesas/compromisos.");
    }

    /**
     * Resetea todas las promesas y compromisos de todas las personas
     * Los sobres dados se mantienen como historial
     */
    public function resetearPromesas()
    {
        // Eliminar todos los compromisos
        Compromiso::truncate();
        
        // Eliminar todas las promesas
        Promesa::truncate();
        
        return redirect()->route('personas.index')
            ->with('success', 'Todas las promesas y compromisos han sido reseteados. Los sobres dados se mantienen como historial.');
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
            ->with('success', 'Compromisos reiniciados correctamente desde ' . $fechaInicio->format('F Y'));
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
     * Genera un reporte PDF de contribuciones acumuladas desde enero hasta el mes actual
     */
    public function reportePdf(Request $request)
    {
        $validated = $request->validate([
            'accion' => 'required|in:ver,descargar',
        ]);

        // Calcular desde enero del anio actual hasta el mes actual
        $anioActual = date('Y');
        $mesActual = date('n'); // Mes sin cero inicial (1-12)

        $fechaInicio = Carbon::create($anioActual, 1, 1)->startOfMonth();
        $fechaFin = Carbon::now()->endOfMonth();

        // Titulo del periodo
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $tituloPeriodo = "Enero - {$meses[$mesActual]} {$anioActual}";

        // Categorías fijas en orden
        $categorias = ['diezmo', 'misiones', 'seminario', 'campa', 'construccion', 'micro'];

        // Obtener personas activas con sus sobres del período
        $personas = Persona::where('activo', true)
            ->with(['sobres' => function($query) use ($fechaInicio, $fechaFin) {
                $query->whereHas('culto', function($q) use ($fechaInicio, $fechaFin) {
                    $q->whereBetween('fecha', [$fechaInicio, $fechaFin]);
                })->with('detalles');
            }])
            ->orderBy('nombre')
            ->get();

        // Calcular totales por categoría
        $totalesPorCategoria = array_fill_keys($categorias, 0);
        $totalGeneral = 0;

        foreach ($personas as $persona) {
            // Inicializar contribuciones por categoría
            $contribuciones = array_fill_keys($categorias, 0);
            $totalPersona = 0;

            foreach ($persona->sobres as $sobre) {
                foreach ($sobre->detalles as $detalle) {
                    $cat = $detalle->categoria;
                    if (isset($contribuciones[$cat])) {
                        $contribuciones[$cat] += $detalle->monto;
                        $totalesPorCategoria[$cat] += $detalle->monto;
                        $totalPersona += $detalle->monto;
                        $totalGeneral += $detalle->monto;
                    }
                }
            }

            $persona->contribuciones = $contribuciones;
            $persona->total_dado = $totalPersona;
        }

        // Filtrar solo personas que han dado algo
        $personas = $personas->filter(function($persona) {
            return $persona->total_dado > 0;
        });

        $pdf = \PDF::loadView('pdfs.reporte-contribuciones', [
            'personas' => $personas,
            'tituloPeriodo' => $tituloPeriodo,
            'categorias' => $categorias,
            'totalesPorCategoria' => $totalesPorCategoria,
            'totalGeneral' => $totalGeneral,
        ]);

        $pdf->setPaper('letter', 'landscape');

        $nombreArchivo = 'reporte-contribuciones-' . $anioActual . '-' . str_pad($mesActual, 2, '0', STR_PAD_LEFT) . '.pdf';

        if ($validated['accion'] === 'descargar') {
            return $pdf->download($nombreArchivo);
        } else {
            return $pdf->stream($nombreArchivo);
        }
    }

    /**
     * Genera un reporte general detallado tipo Excel con dado/esperado por mes
     */
    public function reporteGeneral(Request $request)
    {
        $validated = $request->validate([
            'accion' => 'required|in:ver,descargar',
        ]);

        $anioActual = date('Y');
        $mesActual = (int) date('n');

        $mesesNombres = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        // Categorias con subdivision (dado/esperado) - sin diezmo ni ofrenda
        $categoriasConPromesa = ['misiones', 'seminario', 'campa', 'construccion', 'micro'];

        // Obtener personas activas con promesas y sobres
        $personas = Persona::where('activo', true)
            ->with(['promesas', 'sobres' => function($query) use ($anioActual) {
                $query->whereHas('culto', function($q) use ($anioActual) {
                    $q->whereYear('fecha', $anioActual);
                })->with(['detalles', 'culto']);
            }])
            ->orderBy('nombre')
            ->get();

        // Procesar datos por persona
        $reporteData = [];

        foreach ($personas as $persona) {
            $datosPersona = [
                'nombre' => $persona->nombre,
                'meses' => [],
                'totales' => [
                    'dado' => 0,
                    'esperado' => 0,
                    'diferencia' => 0,
                ],
            ];

            // Construir mapa de promesas por categoría
            $promesasMap = [];
            foreach ($persona->promesas as $promesa) {
                $promesasMap[$promesa->categoria] = $promesa;
            }

            // Procesar cada mes desde enero hasta el mes actual
            for ($mes = 1; $mes <= $mesActual; $mes++) {
                $mesDatos = [
                    'nombre' => $mesesNombres[$mes],
                    'categorias' => [],
                    'total_dado' => 0,
                    'total_esperado' => 0,
                    'diferencia' => 0,
                ];

                // Calcular lo dado por categoría en este mes
                $dadoPorCategoria = array_fill_keys($categoriasConPromesa, 0);

                foreach ($persona->sobres as $sobre) {
                    if ($sobre->culto && Carbon::parse($sobre->culto->fecha)->month === $mes) {
                        foreach ($sobre->detalles as $detalle) {
                            $cat = $detalle->categoria;
                            if (isset($dadoPorCategoria[$cat])) {
                                $dadoPorCategoria[$cat] += $detalle->monto;
                            }
                        }
                    }
                }

                // Calcular esperado y procesar cada categoría con promesa
                foreach ($categoriasConPromesa as $cat) {
                    $dado = $dadoPorCategoria[$cat];
                    $esperado = 0;

                    if (isset($promesasMap[$cat])) {
                        $promesa = $promesasMap[$cat];
                        $esperado = $this->calcularEsperadoMes($promesa, $anioActual, $mes);
                    }

                    $mesDatos['categorias'][$cat] = [
                        'dado' => $dado,
                        'esperado' => $esperado,
                    ];

                    $mesDatos['total_dado'] += $dado;
                    $mesDatos['total_esperado'] += $esperado;
                }

                $mesDatos['diferencia'] = $mesDatos['total_dado'] - $mesDatos['total_esperado'];

                $datosPersona['meses'][] = $mesDatos;

                // Acumular totales de persona
                $datosPersona['totales']['dado'] += $mesDatos['total_dado'];
                $datosPersona['totales']['esperado'] += $mesDatos['total_esperado'];
            }

            $datosPersona['totales']['diferencia'] = $datosPersona['totales']['dado'] - $datosPersona['totales']['esperado'];

            // Solo incluir personas que tengan algo dado o esperado
            if ($datosPersona['totales']['dado'] > 0 || $datosPersona['totales']['esperado'] > 0) {
                $reporteData[] = $datosPersona;
            }
        }

        // Calcular totales generales
        $totalesGenerales = [
            'meses' => [],
            'total_dado' => 0,
            'total_esperado' => 0,
            'total_diferencia' => 0,
        ];

        for ($mes = 1; $mes <= $mesActual; $mes++) {
            $totalesGenerales['meses'][$mes] = [
                'categorias' => array_fill_keys($categoriasConPromesa, ['dado' => 0, 'esperado' => 0]),
                'total_dado' => 0,
                'total_esperado' => 0,
                'diferencia' => 0,
            ];
        }

        foreach ($reporteData as $persona) {
            foreach ($persona['meses'] as $idx => $mesDatos) {
                $mes = $idx + 1;
                foreach ($categoriasConPromesa as $cat) {
                    $totalesGenerales['meses'][$mes]['categorias'][$cat]['dado'] += $mesDatos['categorias'][$cat]['dado'];
                    $totalesGenerales['meses'][$mes]['categorias'][$cat]['esperado'] += $mesDatos['categorias'][$cat]['esperado'];
                }
                $totalesGenerales['meses'][$mes]['total_dado'] += $mesDatos['total_dado'];
                $totalesGenerales['meses'][$mes]['total_esperado'] += $mesDatos['total_esperado'];
                $totalesGenerales['meses'][$mes]['diferencia'] += $mesDatos['diferencia'];
            }
            $totalesGenerales['total_dado'] += $persona['totales']['dado'];
            $totalesGenerales['total_esperado'] += $persona['totales']['esperado'];
            $totalesGenerales['total_diferencia'] += $persona['totales']['diferencia'];
        }

        $pdf = \PDF::loadView('pdfs.reporte-general', [
            'reporteData' => $reporteData,
            'categoriasConPromesa' => $categoriasConPromesa,
            'mesesNombres' => $mesesNombres,
            'mesActual' => $mesActual,
            'anioActual' => $anioActual,
            'totalesGenerales' => $totalesGenerales,
        ]);

        $pdf->setPaper('letter', 'landscape');

        $nombreArchivo = 'reporte-general-' . $anioActual . '-' . str_pad($mesActual, 2, '0', STR_PAD_LEFT) . '.pdf';

        if ($validated['accion'] === 'descargar') {
            return $pdf->download($nombreArchivo);
        } else {
            return $pdf->stream($nombreArchivo);
        }
    }

    /**
     * Calcula el monto esperado segun la frecuencia de la promesa para un mes especifico
     */
    private function calcularEsperadoMes($promesa, int $anio, int $mes): float
    {
        $fechaMes = Carbon::create($anio, $mes, 1);

        switch ($promesa->frecuencia) {
            case 'semanal':
                // Contar domingos en el mes
                $domingos = 0;
                $fecha = $fechaMes->copy()->startOfMonth();
                $finMes = $fechaMes->copy()->endOfMonth();

                while ($fecha->lte($finMes)) {
                    if ($fecha->dayOfWeek === Carbon::SUNDAY) {
                        $domingos++;
                    }
                    $fecha->addDay();
                }

                return $promesa->monto * $domingos;

            case 'quincenal':
                return $promesa->monto * 2;

            case 'mensual':
            default:
                return $promesa->monto;
        }
    }
}

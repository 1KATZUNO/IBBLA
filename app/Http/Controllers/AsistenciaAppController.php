<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\AsistenciaClaseDetalle;
use App\Models\ClaseAsistencia;
use App\Models\Culto;
use App\Models\Persona;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AsistenciaAppController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        $user = Auth::user();

        // Reject miembro and invitado roles
        $rol = $user->rol ?? '';
        if (in_array($rol, ['miembro', 'invitado'])) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'No tienes permiso para usar esta app'], 403);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $rol,
            ],
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $rol = $user->rol ?? '';

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'rol' => $rol,
        ]);
    }

    public function cultos()
    {
        $cultos = Culto::where('cerrado', false)
            ->orderBy('fecha', 'desc')
            ->get()
            ->map(function ($culto) {
                return [
                    'id' => $culto->id,
                    'fecha' => $culto->fecha->format('Y-m-d'),
                    'tipo_culto' => $culto->tipo_culto,
                    'tiene_asistencia' => $culto->asistencia()->exists(),
                ];
            });

        return response()->json($cultos);
    }

    public function clases()
    {
        $clases = ClaseAsistencia::activas()->regulares()->ordenadas()
            ->with(['maestros' => function ($q) {
                $q->where('activo', true)->orderBy('nombre');
            }, 'estudiantes' => function ($q) {
                $q->where('activo', true)->orderBy('nombre');
            }])
            ->get()
            ->map(function ($clase) {
                return [
                    'id' => $clase->id,
                    'nombre' => $clase->nombre,
                    'slug' => $clase->slug,
                    'color' => $clase->color,
                    'tiene_maestros' => $clase->tiene_maestros,
                    'maestros' => $clase->maestros->map(fn ($p) => [
                        'id' => $p->id,
                        'nombre' => $p->nombre,
                    ]),
                    'estudiantes' => $clase->estudiantes->map(fn ($p) => [
                        'id' => $p->id,
                        'nombre' => $p->nombre,
                        'fecha_nacimiento' => $p->fecha_nacimiento?->format('Y-m-d'),
                    ]),
                ];
            });

        return response()->json($clases);
    }

    public function cumpleaneros(ClaseAsistencia $clase)
    {
        $mes = request('mes', Carbon::now()->month);

        $personaIds = $clase->personas()->pluck('personas.id');

        $cumpleaneros = Persona::whereIn('id', $personaIds)
            ->where('activo', true)
            ->whereNotNull('fecha_nacimiento')
            ->whereMonth('fecha_nacimiento', $mes)
            ->orderByRaw('DAY(fecha_nacimiento) ASC')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'fecha_nacimiento' => $p->fecha_nacimiento->format('Y-m-d'),
                'dia' => $p->fecha_nacimiento->day,
            ]);

        return response()->json($cumpleaneros);
    }

    public function resumenCulto(Culto $culto)
    {
        $asistencia = $culto->asistencia;

        if (! $asistencia) {
            return response()->json(['message' => 'No hay asistencia registrada para este culto'], 404);
        }

        $asistencia->load(['detallesClases.claseAsistencia', 'registrosEspeciales']);

        // Per-class breakdown
        $porClase = $asistencia->detallesClases->map(function ($detalle) {
            return [
                'clase' => $detalle->claseAsistencia->nombre ?? 'Desconocida',
                'color' => $detalle->claseAsistencia->color ?? '#999',
                'total_alumnos' => $detalle->getTotalAlumnos(),
                'total_maestros' => $detalle->getTotalMaestros(),
                'hombres' => $detalle->hombres,
                'mujeres' => $detalle->mujeres,
            ];
        });

        // Chapel breakdown
        $capilla = [
            'adultos_hombres' => $asistencia->chapel_adultos_hombres ?? 0,
            'adultos_mujeres' => $asistencia->chapel_adultos_mujeres ?? 0,
            'jovenes_masculinos' => $asistencia->chapel_jovenes_masculinos ?? 0,
            'jovenes_femeninas' => $asistencia->chapel_jovenes_femeninas ?? 0,
            'total' => $asistencia->getTotalCapilla(),
        ];

        // General totals
        $totales = [
            'total_hombres' => $asistencia->getTotalHombres(),
            'total_mujeres' => $asistencia->getTotalMujeres(),
            'total_maestros' => $asistencia->getTotalMaestros(),
            'total_ninos' => $asistencia->getTotalNinos(),
            'total_capilla' => $asistencia->getTotalCapilla(),
            'total_clases' => $asistencia->getTotalClases(),
            'total_general' => $asistencia->total_asistencia,
        ];

        // Specials
        $especiales = [
            'salvos' => $asistencia->getTotalSalvos(),
            'bautismos' => $asistencia->getTotalBautismos(),
            'visitas' => $asistencia->getTotalVisitas(),
            'registros' => $asistencia->registrosEspeciales->map(fn ($r) => [
                'tipo' => $r->tipo,
                'nombre' => $r->nombre,
                'genero' => $r->genero,
                'edad' => $r->edad,
            ]),
        ];

        return response()->json([
            'culto' => [
                'id' => $culto->id,
                'fecha' => $culto->fecha->format('Y-m-d'),
                'tipo_culto' => $culto->tipo_culto,
            ],
            'por_clase' => $porClase,
            'capilla' => $capilla,
            'totales' => $totales,
            'especiales' => $especiales,
        ]);
    }

    public function guardarAsistenciaClase(Request $request)
    {
        $validated = $request->validate([
            'culto_id' => 'required|exists:cultos,id',
            'clase_asistencia_id' => 'required|exists:clases_asistencia,id',
            'hombres' => 'required|integer|min:0',
            'mujeres' => 'required|integer|min:0',
            'maestros_ids' => 'nullable|array',
            'maestros_ids.*' => 'exists:personas,id',
            'estudiantes_presentes_ids' => 'nullable|array',
            'estudiantes_presentes_ids.*' => 'exists:personas,id',
        ]);

        $culto = Culto::findOrFail($validated['culto_id']);
        if ($culto->cerrado) {
            return response()->json(['message' => 'El culto ya está cerrado'], 422);
        }

        // Get or create asistencia for this culto
        $asistencia = Asistencia::firstOrCreate(
            ['culto_id' => $culto->id],
            ['total_asistencia' => 0]
        );

        $maestrosIds = $validated['maestros_ids'] ?? [];
        $totalMaestros = count($maestrosIds);

        AsistenciaClaseDetalle::updateOrCreate(
            [
                'asistencia_id' => $asistencia->id,
                'clase_asistencia_id' => $validated['clase_asistencia_id'],
            ],
            [
                'hombres' => $validated['hombres'],
                'mujeres' => $validated['mujeres'],
                'maestros_hombres' => $totalMaestros,
                'maestros_mujeres' => 0,
                'maestros_ids' => ! empty($maestrosIds) ? $maestrosIds : null,
                'estudiantes_presentes_ids' => $validated['estudiantes_presentes_ids'] ?? null,
            ]
        );

        // Recalculate total
        $this->recalcularTotal($asistencia);

        return response()->json(['message' => 'Asistencia de clase guardada']);
    }

    public function guardarAsistenciaCapilla(Request $request)
    {
        $validated = $request->validate([
            'culto_id' => 'required|exists:cultos,id',
            'chapel_adultos_hombres' => 'required|integer|min:0',
            'chapel_adultos_mujeres' => 'required|integer|min:0',
            'chapel_jovenes_masculinos' => 'required|integer|min:0',
            'chapel_jovenes_femeninas' => 'required|integer|min:0',
            'salvos_adulto_hombre' => 'nullable|integer|min:0',
            'salvos_adulto_mujer' => 'nullable|integer|min:0',
            'salvos_joven_hombre' => 'nullable|integer|min:0',
            'salvos_joven_mujer' => 'nullable|integer|min:0',
            'salvos_nino' => 'nullable|integer|min:0',
            'salvos_nina' => 'nullable|integer|min:0',
            'bautismos_adulto_hombre' => 'nullable|integer|min:0',
            'bautismos_adulto_mujer' => 'nullable|integer|min:0',
            'bautismos_joven_hombre' => 'nullable|integer|min:0',
            'bautismos_joven_mujer' => 'nullable|integer|min:0',
            'bautismos_nino' => 'nullable|integer|min:0',
            'bautismos_nina' => 'nullable|integer|min:0',
            'visitas_adulto_hombre' => 'nullable|integer|min:0',
            'visitas_adulto_mujer' => 'nullable|integer|min:0',
            'visitas_joven_hombre' => 'nullable|integer|min:0',
            'visitas_joven_mujer' => 'nullable|integer|min:0',
            'visitas_nino' => 'nullable|integer|min:0',
            'visitas_nina' => 'nullable|integer|min:0',
            'registros_especiales' => 'nullable|array',
            'registros_especiales.*.tipo' => 'required|in:visita,salvo,bautismo',
            'registros_especiales.*.nombre' => 'required|string|max:255',
            'registros_especiales.*.genero' => 'required|in:M,F',
            'registros_especiales.*.edad' => 'nullable|integer|min:0|max:120',
            'registros_especiales.*.telefono' => 'nullable|string|max:20',
            'registros_especiales.*.fecha_nacimiento' => 'nullable|date',
        ]);

        $culto = Culto::findOrFail($validated['culto_id']);
        if ($culto->cerrado) {
            return response()->json(['message' => 'El culto ya está cerrado'], 422);
        }

        DB::transaction(function () use ($validated, $culto) {
            $chapelData = collect($validated)->except(['culto_id', 'registros_especiales'])->toArray();

            $asistencia = Asistencia::updateOrCreate(
                ['culto_id' => $culto->id],
                $chapelData
            );

            // Save registros especiales
            if (! empty($validated['registros_especiales'])) {
                // Delete existing and recreate
                $asistencia->registrosEspeciales()->delete();
                foreach ($validated['registros_especiales'] as $registro) {
                    $asistencia->registrosEspeciales()->create($registro);
                }
            }

            $this->recalcularTotal($asistencia);
        });

        return response()->json(['message' => 'Asistencia de capilla guardada']);
    }

    public function quickAddPersona(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha_nacimiento' => 'nullable|date',
            'clase_asistencia_id' => 'required|exists:clases_asistencia,id',
        ]);

        $persona = Persona::create([
            'nombre' => $validated['nombre'],
            'fecha_nacimiento' => $validated['fecha_nacimiento'] ?? null,
            'activo' => true,
        ]);

        // Attach to class as student
        $persona->clasesAsistencia()->attach($validated['clase_asistencia_id'], [
            'es_maestro' => false,
        ]);

        return response()->json([
            'id' => $persona->id,
            'nombre' => $persona->nombre,
            'fecha_nacimiento' => $persona->fecha_nacimiento?->format('Y-m-d'),
        ]);
    }

    private function recalcularTotal(Asistencia $asistencia): void
    {
        $asistencia->refresh();
        $asistencia->load('detallesClases');

        $total = $asistencia->getTotalCapilla() + $asistencia->getTotalClases();
        $asistencia->update(['total_asistencia' => $total]);
    }
}

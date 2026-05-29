<?php

namespace App\Http\Controllers;

use App\Models\AsistenciaPersonaCulto;
use App\Models\ClaseAsistencia;
use App\Models\Culto;
use App\Models\Persona;
use App\Models\VisitacionPastoral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * App genérica de clase (Fase 6 — basado en Registro Horeb).
 *
 * Permite a una maestra registrar miembros, visitas, asistencia individual
 * por culto y visitación pastoral por mes/semana, todo dentro de una clase
 * específica (Horeb 55-64, Jóvenes, etc.).
 *
 * Permisos:
 *  - Admin/Pastor: cualquier clase.
 *  - Maestra (user.clase_asistencia_id seteado): solo SU clase.
 *  - Otros roles: 403.
 */
class ClaseAppController extends Controller
{
    /** Selector de clases: maestra ve solo SU clase, admin ve todas. */
    public function index(): View
    {
        $user = auth()->user();
        $clasesQuery = ClaseAsistencia::where('tenant_id', $user->tenant_id)->activas()->ordenadas();

        if (! $user->isAdmin() && ! $user->is_super_admin) {
            // Maestra: solo su clase asignada o las clases donde es maestro
            $clasesIds = collect([$user->clase_asistencia_id])->filter();
            if ($user->persona) {
                $clasesIds = $clasesIds->merge(
                    $user->persona->clasesAsistencia()->wherePivot('es_maestro', true)->pluck('clases_asistencia.id')
                );
            }
            $clasesQuery->whereIn('id', $clasesIds->unique());
        }

        $clases = $clasesQuery->get();

        // Si solo hay una clase visible, redirigir directo
        if ($clases->count() === 1) {
            return redirect()->route('clase-app.shell', $clases->first()->slug);
        }

        return view('clase-app.index', compact('clases'));
    }

    /** Vista shell que monta la app React. */
    public function shell(string $slug): View
    {
        $clase = $this->getClaseOrFail($slug);
        $this->authorizeAccess($clase);

        return view('clase-app.shell', compact('clase'));
    }

    /**
     * Devuelve toda la data inicial de la clase para hidratar la app:
     * miembros, visitas, cultos del año con asistencia individual, visitaciones.
     */
    public function data(string $slug): JsonResponse
    {
        $clase = $this->getClaseOrFail($slug);
        $this->authorizeAccess($clase);

        $año = (int) request('año', now()->year);

        $personas = $clase->personas()
            ->orderBy('personas.nombre')
            ->get();

        $miembros = $personas->filter(fn ($p) => $p->pivot->tipo === 'miembro')->values();
        $visitas = $personas->filter(fn ($p) => $p->pivot->tipo === 'visita')->values();

        $mapPersona = fn ($p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'telefono' => $p->telefono,
            'direccion' => $p->direccion,
            'cumpleanos' => $p->fecha_nacimiento?->format('d/m'),
            'edad' => $p->fecha_nacimiento ? now()->diffInYears($p->fecha_nacimiento) : null,
            'notas' => $p->pivot->notas_clase,
            'convertida_de_visita_at' => $p->pivot->convertida_de_visita_at,
        ];

        // Cultos del año (todos los domingos, no solo los que tienen asistencia)
        $cultos = Culto::where('tenant_id', auth()->user()->tenant_id)
            ->whereYear('fecha', $año)
            ->orderBy('fecha')
            ->get(['id', 'fecha', 'tipo_culto']);

        // Asistencia individual del año
        $asistencias = AsistenciaPersonaCulto::where('clase_asistencia_id', $clase->id)
            ->whereIn('culto_id', $cultos->pluck('id'))
            ->where('presente', true)
            ->get(['persona_id', 'culto_id']);

        // Reestructura como { 'YYYY-MM-DD': { members: {persona_id: true}, visitors: {persona_id: true} } }
        $attendance = [];
        $cultosById = $cultos->keyBy('id');
        foreach ($asistencias as $a) {
            $culto = $cultosById[$a->culto_id] ?? null;
            if (! $culto) {
                continue;
            }
            $key = $culto->fecha->format('Y-m-d');
            $attendance[$key] ??= ['members' => [], 'visitors' => []];
            $bucket = $miembros->contains('id', $a->persona_id) ? 'members' : 'visitors';
            $attendance[$key][$bucket][$a->persona_id] = true;
        }

        // Visitaciones pastorales del año
        $visitacionesRaw = VisitacionPastoral::where('clase_asistencia_id', $clase->id)
            ->where('año', $año)
            ->get();
        $visitations = [];
        foreach ($visitacionesRaw as $v) {
            $monthKey = $v->año.'-'.str_pad((string) $v->mes, 2, '0', STR_PAD_LEFT);
            $visitations[$monthKey][$v->persona_id][$v->semana] = [
                'visited' => true,
                'date' => $v->fecha?->format('Y-m-d'),
                'notes' => $v->notas,
            ];
        }

        return response()->json([
            'clase' => [
                'id' => $clase->id,
                'nombre' => $clase->nombre,
                'slug' => $clase->slug,
                'color' => $clase->color,
            ],
            'year' => $año,
            'currentUser' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'role' => $this->resolveRol(),
            ],
            'members' => $miembros->map($mapPersona)->values(),
            'visitors' => $visitas->map($mapPersona)->values(),
            'attendance' => $attendance,
            'visitations' => $visitations,
            'cultos' => $cultos->map(fn ($c) => [
                'id' => $c->id,
                'fecha' => $c->fecha->format('Y-m-d'),
                'tipo_culto' => $c->tipo_culto,
            ])->values(),
        ]);
    }

    public function storePersona(Request $request, string $slug, string $tipo): JsonResponse
    {
        $clase = $this->getClaseOrFail($slug);
        $this->authorizeAccess($clase);
        abort_unless(in_array($tipo, ['miembro', 'visita']), 400);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'direccion' => 'nullable|string|max:255',
            'cumpleanos' => 'nullable|string|max:10',
            'edad' => 'nullable|integer|min:0',
            'notas' => 'nullable|string',
        ]);

        $persona = DB::transaction(function () use ($clase, $tipo, $data) {
            $fechaNac = null;
            if (! empty($data['cumpleanos'])) {
                // 'DD/MM' → usar año actual menos edad si hay
                $parts = preg_split('/[\/\-\.]/', $data['cumpleanos']);
                if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                    $año = ! empty($data['edad']) ? now()->year - (int) $data['edad'] : 1970;
                    try {
                        $fechaNac = \Carbon\Carbon::createFromDate($año, (int) $parts[1], (int) $parts[0]);
                    } catch (\Throwable) {
                    }
                }
            }
            $persona = Persona::create([
                'nombre' => $data['nombre'],
                'telefono' => $data['telefono'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'fecha_nacimiento' => $fechaNac,
            ]);
            $clase->personas()->attach($persona->id, [
                'tipo' => $tipo,
                'es_maestro' => false,
                'notas_clase' => $data['notas'] ?? null,
            ]);

            return $persona;
        });

        return response()->json(['id' => $persona->id]);
    }

    public function updatePersona(Request $request, string $slug, int $personaId): JsonResponse
    {
        $clase = $this->getClaseOrFail($slug);
        $this->authorizeAccess($clase);
        $persona = Persona::findOrFail($personaId);
        $this->ensurePersonaEnClase($persona, $clase);

        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'telefono' => 'nullable|string|max:30',
            'direccion' => 'nullable|string|max:255',
            'cumpleanos' => 'nullable|string|max:10',
            'edad' => 'nullable|integer|min:0',
            'notas' => 'nullable|string',
        ]);

        $persona->update(array_filter([
            'nombre' => $data['nombre'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'direccion' => $data['direccion'] ?? null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('notas', $data)) {
            $clase->personas()->updateExistingPivot($persona->id, ['notas_clase' => $data['notas']]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroyPersona(string $slug, int $personaId): JsonResponse
    {
        $clase = $this->getClaseOrFail($slug);
        $this->authorizeAccess($clase);
        $persona = Persona::findOrFail($personaId);
        $this->ensurePersonaEnClase($persona, $clase);

        // Solo detach de la clase; la persona y su historial permanecen.
        $clase->personas()->detach($persona->id);

        return response()->json(['ok' => true]);
    }

    public function convertirVisita(string $slug, int $personaId): JsonResponse
    {
        $clase = $this->getClaseOrFail($slug);
        $this->authorizeAccess($clase);
        $persona = Persona::findOrFail($personaId);
        $this->ensurePersonaEnClase($persona, $clase);

        $clase->personas()->updateExistingPivot($persona->id, [
            'tipo' => 'miembro',
            'convertida_de_visita_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function toggleAsistencia(Request $request, string $slug): JsonResponse
    {
        $clase = $this->getClaseOrFail($slug);
        $this->authorizeAccess($clase);

        $data = $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'culto_id' => 'required|exists:cultos,id',
        ]);

        $existing = AsistenciaPersonaCulto::where('persona_id', $data['persona_id'])
            ->where('clase_asistencia_id', $clase->id)
            ->where('culto_id', $data['culto_id'])
            ->first();

        if ($existing) {
            $existing->update(['presente' => ! $existing->presente, 'marcada_por_user_id' => auth()->id()]);
            $presente = $existing->presente;
        } else {
            AsistenciaPersonaCulto::create([
                'persona_id' => $data['persona_id'],
                'clase_asistencia_id' => $clase->id,
                'culto_id' => $data['culto_id'],
                'presente' => true,
                'marcada_por_user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id,
            ]);
            $presente = true;
        }

        return response()->json(['presente' => $presente]);
    }

    public function upsertVisitacion(Request $request, string $slug): JsonResponse
    {
        $clase = $this->getClaseOrFail($slug);
        $this->authorizeAccess($clase);

        $data = $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'año' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'semana' => 'required|integer|min:1|max:5',
            'visited' => 'nullable|boolean',
            'fecha' => 'nullable|date',
            'notas' => 'nullable|string',
        ]);

        $existing = VisitacionPastoral::where('persona_id', $data['persona_id'])
            ->where('clase_asistencia_id', $clase->id)
            ->where('año', $data['año'])
            ->where('mes', $data['mes'])
            ->where('semana', $data['semana'])
            ->first();

        // Si se desmarca (visited=false) sin notas → eliminar registro.
        if ($existing && isset($data['visited']) && ! $data['visited'] && empty($data['fecha']) && empty($data['notas'])) {
            $existing->delete();

            return response()->json(['deleted' => true]);
        }

        $payload = [
            'fecha' => $data['fecha'] ?? ($existing?->fecha ?? now()->toDateString()),
            'notas' => $data['notas'] ?? $existing?->notas,
            'registrada_por_user_id' => auth()->id(),
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            VisitacionPastoral::create(array_merge($payload, [
                'persona_id' => $data['persona_id'],
                'clase_asistencia_id' => $clase->id,
                'año' => $data['año'],
                'mes' => $data['mes'],
                'semana' => $data['semana'],
                'tenant_id' => auth()->user()->tenant_id,
            ]));
        }

        return response()->json(['ok' => true]);
    }

    // -------------------- Helpers --------------------

    protected function getClaseOrFail(string $slug): ClaseAsistencia
    {
        return ClaseAsistencia::where('slug', $slug)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->firstOrFail();
    }

    protected function authorizeAccess(ClaseAsistencia $clase): void
    {
        $user = auth()->user();
        if ($user->isAdmin() || $user->is_super_admin) {
            return;
        }
        // Maestra asignada a esta clase específica
        if ($user->clase_asistencia_id === $clase->id) {
            return;
        }
        // Maestra registrada como persona con es_maestro=true en esta clase
        if ($user->persona && $user->persona->esMaestroEn($clase->id)) {
            return;
        }
        abort(403, 'No tienes acceso a esta clase.');
    }

    protected function ensurePersonaEnClase(Persona $persona, ClaseAsistencia $clase): void
    {
        $exists = DB::table('clase_persona')
            ->where('persona_id', $persona->id)
            ->where('clase_asistencia_id', $clase->id)
            ->exists();
        abort_unless($exists, 404, 'Persona no pertenece a esta clase.');
    }

    protected function resolveRol(): string
    {
        return auth()->user()->isAdmin() ? 'admin' : 'maestra';
    }
}

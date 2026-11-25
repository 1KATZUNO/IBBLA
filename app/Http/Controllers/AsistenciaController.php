<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\AuditLog;
use App\Models\AsistenciaClaseDetalle;
use App\Models\AsistenciaRegistroExtra;
use App\Models\ClaseAsistencia;
use App\Models\Culto;
use App\Models\RegistroExtraTipo;
use App\Services\PermisosEdicionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsistenciaController extends Controller
{
    private function getClasesActivas()
    {
        return ClaseAsistencia::activas()->regulares()->ordenadas()->get();
    }

    private function getMaestrosPorClase()
    {
        $clases = ClaseAsistencia::activas()->regulares()->ordenadas()
            ->with(['maestros' => function ($q) {
                $q->where('activo', true)->orderBy('nombre');
            }])
            ->get();

        $grouped = collect();
        foreach ($clases as $clase) {
            if ($clase->maestros->isNotEmpty()) {
                $grouped[$clase->id] = $clase->maestros;
            }
        }

        return $grouped;
    }

    private function getRegistroExtraTipos()
    {
        return RegistroExtraTipo::activos()->ordenados()->get();
    }

    /**
     * Regla de una casilla de cantidad de personas: entero entre 0 y Asistencia::MAX_CANTIDAD.
     */
    private function reglaCantidad(bool $requerida = true): string
    {
        return ($requerida ? 'required' : 'nullable').'|integer|min:0|max:'.Asistencia::MAX_CANTIDAD;
    }

    /**
     * Reglas de las casillas fijas del formulario (capilla, salvos, bautismos y visitas).
     * El total de asistencia no lleva tope: lo suma el formulario (capilla + clases + maestros)
     * y puede pasar de Asistencia::MAX_CANTIDAD.
     */
    private function reglasCantidadesFijas(): array
    {
        $rules = [
            'chapel_adultos_hombres' => $this->reglaCantidad(),
            'chapel_adultos_mujeres' => $this->reglaCantidad(),
            'total_asistencia' => 'required|integer|min:0',
        ];

        foreach (['salvos', 'bautismos', 'visitas'] as $grupo) {
            foreach (['adulto_hombre', 'adulto_mujer', 'joven_hombre', 'joven_mujer', 'nino', 'nina'] as $subgrupo) {
                $rules["{$grupo}_{$subgrupo}"] = $this->reglaCantidad();
            }
        }

        return $rules;
    }

    /**
     * Las casillas son <input type="number">: si alguien escribe un dígito detrás del 0 inicial el
     * navegador manda "05", y la regla `integer` rechaza los ceros a la izquierda. Se limpian aquí
     * para que el formulario no rebote en silencio aunque falle el JS de la casilla.
     */
    private function normalizarCantidades(Request $request): void
    {
        $limpiar = function ($valor) use (&$limpiar) {
            if (is_array($valor)) {
                return array_map($limpiar, $valor);
            }

            return is_string($valor) && ctype_digit($valor) ? preg_replace('/^0+(?=\d)/', '', $valor) : $valor;
        };

        $request->merge($limpiar($request->input()));
    }

    private function buildRegistroExtraValidationRules($tipos): array
    {
        $rules = [];
        foreach ($tipos as $tipo) {
            foreach ($tipo->subcampos as $subcampo) {
                $rules["registro_extra.{$tipo->id}.{$subcampo}"] = $this->reglaCantidad(false);
            }
        }

        return $rules;
    }

    private function saveRegistrosExtra(Asistencia $asistencia, array $registroExtraData, $tipos): void
    {
        $asistencia->registrosExtra()->delete();

        foreach ($tipos as $tipo) {
            if (isset($registroExtraData[$tipo->id])) {
                $valores = [];
                foreach ($tipo->subcampos as $subcampo) {
                    $valores[$subcampo] = (int) ($registroExtraData[$tipo->id][$subcampo] ?? 0);
                }

                if (array_sum($valores) > 0) {
                    AsistenciaRegistroExtra::create([
                        'asistencia_id' => $asistencia->id,
                        'registro_extra_tipo_id' => $tipo->id,
                        'valores' => $valores,
                    ]);
                }
            }
        }
    }

    private function buildClaseValidationRules($clases): array
    {
        $rules = [];
        foreach ($clases as $clase) {
            $rules["clase.{$clase->id}.hombres"] = $this->reglaCantidad();
            $rules["clase.{$clase->id}.mujeres"] = $this->reglaCantidad();
            if ($clase->tiene_maestros) {
                $rules["clase.{$clase->id}.maestros_ids"] = 'nullable|array';
                $rules["clase.{$clase->id}.maestros_ids.*"] = 'exists:personas,id';
            }
        }

        return $rules;
    }

    private function saveClaseDetalles(Asistencia $asistencia, array $clasesData, $clases): void
    {
        // Eliminar detalles existentes y recrear
        $asistencia->detallesClases()->delete();

        foreach ($clases as $clase) {
            if (isset($clasesData[$clase->id])) {
                $data = $clasesData[$clase->id];
                $maestrosIds = $clase->tiene_maestros ? ($data['maestros_ids'] ?? []) : [];
                $totalMaestros = count($maestrosIds);

                AsistenciaClaseDetalle::create([
                    'asistencia_id' => $asistencia->id,
                    'clase_asistencia_id' => $clase->id,
                    'hombres' => $data['hombres'] ?? 0,
                    'mujeres' => $data['mujeres'] ?? 0,
                    'maestros_hombres' => $totalMaestros,
                    'maestros_mujeres' => 0,
                    'maestros_ids' => ! empty($maestrosIds) ? $maestrosIds : null,
                ]);
            }
        }
    }

    public function index(Request $request)
    {
        $query = Culto::with(['asistencia.detallesClases.claseAsistencia'])
            ->whereHas('asistencia', function ($query) {
                $query->where('cerrado', false);
            });

        // Filtro por mes
        if ($request->filled('mes') && $request->mes !== 'todos') {
            $query->whereMonth('fecha', $request->mes);
        }

        // Filtro por año
        if ($request->filled('año') && $request->año !== 'todos') {
            $query->whereYear('fecha', $request->año);
        }

        $cultos = $query->orderBy('fecha', 'desc')->paginate(20);

        $asistenciasCerradas = Asistencia::where('cerrado', true)
            ->with(['culto', 'detallesClases.claseAsistencia'])
            ->orderBy('cerrado_at', 'desc')
            ->get();

        return view('asistencia.index', compact('cultos', 'asistenciasCerradas'));
    }

    public function create()
    {
        $cultos = Culto::whereDoesntHave('asistencia')->orderBy('fecha', 'desc')->get();
        $clases = $this->getClasesActivas();
        $maestrosPorClase = $this->getMaestrosPorClase();
        $registroExtraTipos = $this->getRegistroExtraTipos();

        return view('asistencia.create', compact('cultos', 'clases', 'maestrosPorClase', 'registroExtraTipos'));
    }

    public function store(Request $request)
    {
        $clases = $this->getClasesActivas();
        $registroExtraTipos = $this->getRegistroExtraTipos();

        $this->normalizarCantidades($request);

        $rules = array_merge([
            'culto_id' => [
                'required',
                'exists:cultos,id',
                'unique:asistencia,culto_id,NULL,id,tenant_id,'.(auth()->user()->tenant_id ?? ''),
            ],
        ], $this->reglasCantidadesFijas());

        $rules = array_merge($rules, $this->buildClaseValidationRules($clases));
        $rules = array_merge($rules, $this->buildRegistroExtraValidationRules($registroExtraTipos));
        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $request, $clases, $registroExtraTipos) {
            $clasesData = $request->input('clase', []);
            $registroExtraData = $request->input('registro_extra', []);
            unset($validated['clase'], $validated['registro_extra']);

            $asistencia = Asistencia::create($validated);
            $this->saveClaseDetalles($asistencia, $clasesData, $clases);
            $this->saveRegistrosExtra($asistencia, $registroExtraData, $registroExtraTipos);
        });

        return redirect()->route('asistencia.index')
            ->with('success', 'Asistencia registrada correctamente.');
    }

    /**
     * Asistencia cerrada = solo lectura, con la misma regla que Recuento (QE-015 / QA-021): el
     * asistente la ve en gris con el motivo; el administrador la puede corregir, con aviso, y la
     * correccion queda en la auditoria (y en Accesos > Ultimos cambios).
     */
    private function puedeCorregirCerrada(): bool
    {
        $user = auth()->user();

        return $user && ($user->isAdmin() || $user->isSuperAdmin());
    }

    /** "Cerrada el 05/11/2026 a las 18:30 por Roy": el motivo que ve quien no la puede editar. */
    public static function textoCerrada(Asistencia $asistencia): string
    {
        return 'Cerrada'
            .($asistencia->cerrado_at ? ' el '.$asistencia->cerrado_at->format('d/m/Y \a \l\a\s H:i') : '')
            .($asistencia->cerradoPor ? ' por '.$asistencia->cerradoPor->name : '');
    }

    /** Cifras de la asistencia para dejar en la auditoria el antes y el despues de una correccion. */
    private function fotoParaAuditoria(Asistencia $asistencia): array
    {
        $asistencia->load('detallesClases.claseAsistencia');

        return [
            'cantidades' => $asistencia->only(array_keys($this->reglasCantidadesFijas())),
            'clases' => $asistencia->detallesClases
                ->mapWithKeys(fn ($d) => [($d->claseAsistencia->nombre ?? 'Clase '.$d->clase_asistencia_id) => (int) $d->hombres + (int) $d->mujeres + (int) $d->maestros_hombres + (int) $d->maestros_mujeres])
                ->all(),
        ];
    }

    public function show(Asistencia $asistencium)
    {
        $asistencium->load(['culto', 'detallesClases.claseAsistencia', 'cerradoPor']);

        return view('asistencia.show', ['asistencia' => $asistencium, 'puedeCorregir' => $this->puedeCorregirCerrada()]);
    }

    public function edit(Asistencia $asistencium)
    {
        // Cerrada y sin poder corregirla: se muestra en solo lectura (antes abria el formulario y
        // rebotaba al guardar)
        if ($asistencium->cerrado && ! $this->puedeCorregirCerrada()) {
            return $this->show($asistencium);
        }

        $asistencium->load(['detallesClases.claseAsistencia', 'registrosExtra.tipo', 'cerradoPor']);
        $clases = $this->getClasesActivas();
        $maestrosPorClase = $this->getMaestrosPorClase();
        $registroExtraTipos = $this->getRegistroExtraTipos();

        return view('asistencia.edit', ['asistencia' => $asistencium, 'clases' => $clases, 'maestrosPorClase' => $maestrosPorClase, 'registroExtraTipos' => $registroExtraTipos]);
    }

    public function update(Request $request, Asistencia $asistencium)
    {
        $corrigiendoCerrada = $asistencium->cerrado;
        if ($corrigiendoCerrada && ! $this->puedeCorregirCerrada()) {
            abort(403, self::textoCerrada($asistencium->loadMissing('cerradoPor')).'. Solo el administrador puede corregirla.');
        }
        $antes = $corrigiendoCerrada ? $this->fotoParaAuditoria($asistencium) : null;

        $clases = $this->getClasesActivas();

        $this->normalizarCantidades($request);

        $rules = $this->reglasCantidadesFijas();

        $registroExtraTipos = $this->getRegistroExtraTipos();
        $rules = array_merge($rules, $this->buildClaseValidationRules($clases));
        $rules = array_merge($rules, $this->buildRegistroExtraValidationRules($registroExtraTipos));
        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $request, $asistencium, $clases, $registroExtraTipos) {
            $clasesData = $request->input('clase', []);
            $registroExtraData = $request->input('registro_extra', []);
            unset($validated['clase'], $validated['registro_extra']);

            $asistencium->update($validated);
            $this->saveClaseDetalles($asistencium, $clasesData, $clases);
            $this->saveRegistrosExtra($asistencium, $registroExtraData, $registroExtraTipos);
        });

        if ($corrigiendoCerrada) {
            $asistencium->load('culto');
            $fecha = $asistencium->culto?->fecha;
            AuditLog::registrar(PermisosEdicionService::ACCION_CORRECCION, [
                'permiso_id' => null,
                'accion' => 'Corregir asistencia cerrada'.($fecha ? ' del culto del '.$fecha->format('d/m/Y') : ''),
                'asistencia_id' => $asistencium->id,
                'fecha_culto' => $fecha?->toDateString(),
                'antes' => $antes,
                'despues' => $this->fotoParaAuditoria($asistencium),
            ], 'asistencia.update');

            return redirect()->route('asistencia.show', $asistencium)
                ->with('success', 'Asistencia cerrada corregida. El cambio quedó en la auditoría.');
        }

        return redirect()->route('asistencia.index')
            ->with('success', 'Asistencia actualizada correctamente.');
    }

    public function destroy(Asistencia $asistencium)
    {
        // Solo admin y asistente pueden eliminar asistencias
        if (! in_array(auth()->user()->rol, ['admin', 'asistente'])) {
            return redirect()->route('asistencia.index')
                ->with('error', 'No tienes permiso para eliminar asistencias.');
        }

        if ($asistencium->cerrado) {
            return redirect()->route('asistencia.index')
                ->with('error', 'No se puede eliminar una asistencia cerrada.');
        }

        $asistencium->delete();

        return redirect()->route('asistencia.index')
            ->with('success', 'Asistencia eliminada correctamente.');
    }

    public function cerrarAsistencia(Asistencia $asistencium)
    {
        if ($asistencium->cerrado) {
            return redirect()->route('asistencia.index')
                ->with('error', 'Esta asistencia ya está cerrada.');
        }

        $asistencium->update([
            'cerrado' => true,
            'cerrado_at' => now(),
            'cerrado_por' => auth()->id(),
        ]);

        return redirect()->route('asistencia.index')
            ->with('success', 'Asistencia cerrada correctamente. Ahora aparece en la lista de asistencias cerradas.');
    }
}

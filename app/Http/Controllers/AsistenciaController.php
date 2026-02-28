<?php

namespace App\Http\Controllers;

use App\Models\Culto;
use App\Models\Asistencia;
use App\Models\ClaseAsistencia;
use App\Models\AsistenciaClaseDetalle;
use App\Models\Persona;
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
        return Persona::where('activo', true)
            ->where('es_maestro', true)
            ->whereNotNull('clase_asistencia_id')
            ->orderBy('nombre')
            ->get()
            ->groupBy('clase_asistencia_id');
    }

    private function buildClaseValidationRules($clases): array
    {
        $rules = [];
        foreach ($clases as $clase) {
            $rules["clase.{$clase->id}.hombres"] = 'required|integer|min:0';
            $rules["clase.{$clase->id}.mujeres"] = 'required|integer|min:0';
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
                    'maestros_ids' => !empty($maestrosIds) ? $maestrosIds : null,
                ]);
            }
        }
    }

    public function index(Request $request)
    {
        $query = Culto::with(['asistencia.detallesClases.claseAsistencia'])
            ->whereHas('asistencia', function($query) {
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
        return view('asistencia.create', compact('cultos', 'clases', 'maestrosPorClase'));
    }

    public function store(Request $request)
    {
        $clases = $this->getClasesActivas();

        $rules = [
            'culto_id' => 'required|exists:cultos,id|unique:asistencia,culto_id',
            'chapel_adultos_hombres' => 'required|integer|min:0',
            'chapel_adultos_mujeres' => 'required|integer|min:0',
            'chapel_adultos_mayores_hombres' => 'required|integer|min:0',
            'chapel_adultos_mayores_mujeres' => 'required|integer|min:0',
            'chapel_jovenes_masculinos' => 'required|integer|min:0',
            'chapel_jovenes_femeninas' => 'required|integer|min:0',
            'chapel_maestros_hombres' => 'required|integer|min:0',
            'chapel_maestros_mujeres' => 'required|integer|min:0',
            'total_asistencia' => 'required|integer|min:0',
            'salvos_adulto_hombre' => 'required|integer|min:0',
            'salvos_adulto_mujer' => 'required|integer|min:0',
            'salvos_joven_hombre' => 'required|integer|min:0',
            'salvos_joven_mujer' => 'required|integer|min:0',
            'salvos_nino' => 'required|integer|min:0',
            'salvos_nina' => 'required|integer|min:0',
            'bautismos_adulto_hombre' => 'required|integer|min:0',
            'bautismos_adulto_mujer' => 'required|integer|min:0',
            'bautismos_joven_hombre' => 'required|integer|min:0',
            'bautismos_joven_mujer' => 'required|integer|min:0',
            'bautismos_nino' => 'required|integer|min:0',
            'bautismos_nina' => 'required|integer|min:0',
            'visitas_adulto_hombre' => 'required|integer|min:0',
            'visitas_adulto_mujer' => 'required|integer|min:0',
            'visitas_joven_hombre' => 'required|integer|min:0',
            'visitas_joven_mujer' => 'required|integer|min:0',
            'visitas_nino' => 'required|integer|min:0',
            'visitas_nina' => 'required|integer|min:0',
        ];

        $rules = array_merge($rules, $this->buildClaseValidationRules($clases));
        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $request, $clases) {
            // Extraer datos de clases antes de crear asistencia
            $clasesData = $request->input('clase', []);
            unset($validated['clase']);

            $asistencia = Asistencia::create($validated);
            $this->saveClaseDetalles($asistencia, $clasesData, $clases);
        });

        return redirect()->route('asistencia.index')
            ->with('success', 'Asistencia registrada correctamente.');
    }

    public function show(Asistencia $asistencium)
    {
        $asistencium->load(['culto', 'detallesClases.claseAsistencia']);
        return view('asistencia.show', ['asistencia' => $asistencium]);
    }

    public function edit(Asistencia $asistencium)
    {
        $asistencium->load('detallesClases.claseAsistencia');
        $clases = $this->getClasesActivas();
        $maestrosPorClase = $this->getMaestrosPorClase();
        return view('asistencia.edit', ['asistencia' => $asistencium, 'clases' => $clases, 'maestrosPorClase' => $maestrosPorClase]);
    }

    public function update(Request $request, Asistencia $asistencium)
    {
        if ($asistencium->cerrado) {
            return redirect()->route('asistencia.index')
                ->with('error', 'No se puede editar una asistencia cerrada.');
        }

        $clases = $this->getClasesActivas();

        $rules = [
            'chapel_adultos_hombres' => 'required|integer|min:0',
            'chapel_adultos_mujeres' => 'required|integer|min:0',
            'chapel_adultos_mayores_hombres' => 'required|integer|min:0',
            'chapel_adultos_mayores_mujeres' => 'required|integer|min:0',
            'chapel_jovenes_masculinos' => 'required|integer|min:0',
            'chapel_jovenes_femeninas' => 'required|integer|min:0',
            'chapel_maestros_hombres' => 'required|integer|min:0',
            'chapel_maestros_mujeres' => 'required|integer|min:0',
            'total_asistencia' => 'required|integer|min:0',
            'salvos_adulto_hombre' => 'required|integer|min:0',
            'salvos_adulto_mujer' => 'required|integer|min:0',
            'salvos_joven_hombre' => 'required|integer|min:0',
            'salvos_joven_mujer' => 'required|integer|min:0',
            'salvos_nino' => 'required|integer|min:0',
            'salvos_nina' => 'required|integer|min:0',
            'bautismos_adulto_hombre' => 'required|integer|min:0',
            'bautismos_adulto_mujer' => 'required|integer|min:0',
            'bautismos_joven_hombre' => 'required|integer|min:0',
            'bautismos_joven_mujer' => 'required|integer|min:0',
            'bautismos_nino' => 'required|integer|min:0',
            'bautismos_nina' => 'required|integer|min:0',
            'visitas_adulto_hombre' => 'required|integer|min:0',
            'visitas_adulto_mujer' => 'required|integer|min:0',
            'visitas_joven_hombre' => 'required|integer|min:0',
            'visitas_joven_mujer' => 'required|integer|min:0',
            'visitas_nino' => 'required|integer|min:0',
            'visitas_nina' => 'required|integer|min:0',
        ];

        $rules = array_merge($rules, $this->buildClaseValidationRules($clases));
        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $request, $asistencium, $clases) {
            $clasesData = $request->input('clase', []);
            unset($validated['clase']);

            $asistencium->update($validated);
            $this->saveClaseDetalles($asistencium, $clasesData, $clases);
        });

        return redirect()->route('asistencia.index')
            ->with('success', 'Asistencia actualizada correctamente.');
    }

    public function destroy(Asistencia $asistencium)
    {
        // Solo admin y asistente pueden eliminar asistencias
        if (!in_array(auth()->user()->rol, ['admin', 'asistente'])) {
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

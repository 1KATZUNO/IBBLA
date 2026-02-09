<?php

namespace App\Http\Controllers;

use App\Models\Culto;
use App\Models\Asistencia;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    public function index(Request $request)
    {
        $query = Culto::with('asistencia')
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
            ->with('culto')
            ->orderBy('cerrado_at', 'desc')
            ->get();
        
        return view('asistencia.index', compact('cultos', 'asistenciasCerradas'));
    }

    public function create()
    {
        $cultos = Culto::whereDoesntHave('asistencia')->orderBy('fecha', 'desc')->get();
        return view('asistencia.create', compact('cultos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'culto_id' => 'required|exists:cultos,id|unique:asistencia,culto_id',
            'chapel_adultos_hombres' => 'required|integer|min:0',
            'chapel_adultos_mujeres' => 'required|integer|min:0',
            'chapel_adultos_mayores_hombres' => 'required|integer|min:0',
            'chapel_adultos_mayores_mujeres' => 'required|integer|min:0',
            'chapel_jovenes_masculinos' => 'required|integer|min:0',
            'chapel_jovenes_femeninas' => 'required|integer|min:0',
            'chapel_maestros_hombres' => 'required|integer|min:0',
            'chapel_maestros_mujeres' => 'required|integer|min:0',
            'clase_0_1_hombres' => 'required|integer|min:0',
            'clase_0_1_mujeres' => 'required|integer|min:0',
            'clase_0_1_maestros_hombres' => 'required|integer|min:0',
            'clase_0_1_maestros_mujeres' => 'required|integer|min:0',
            'clase_2_6_hombres' => 'required|integer|min:0',
            'clase_2_6_mujeres' => 'required|integer|min:0',
            'clase_2_6_maestros_hombres' => 'required|integer|min:0',
            'clase_2_6_maestros_mujeres' => 'required|integer|min:0',
            'clase_7_8_hombres' => 'required|integer|min:0',
            'clase_7_8_mujeres' => 'required|integer|min:0',
            'clase_7_8_maestros_hombres' => 'required|integer|min:0',
            'clase_7_8_maestros_mujeres' => 'required|integer|min:0',
            'clase_9_11_hombres' => 'required|integer|min:0',
            'clase_9_11_mujeres' => 'required|integer|min:0',
            'clase_9_11_maestros_hombres' => 'required|integer|min:0',
            'clase_9_11_maestros_mujeres' => 'required|integer|min:0',
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
        ]);

        Asistencia::create($validated);

        return redirect()->route('asistencia.index')
            ->with('success', 'Asistencia registrada correctamente.');
    }

    public function show(Asistencia $asistencium)
    {
        $asistencium->load('culto');
        return view('asistencia.show', ['asistencia' => $asistencium]);
    }

    public function edit(Asistencia $asistencium)
    {
        return view('asistencia.edit', ['asistencia' => $asistencium]);
    }

    public function update(Request $request, Asistencia $asistencium)
    {
        if ($asistencium->cerrado) {
            return redirect()->route('asistencia.index')
                ->with('error', 'No se puede editar una asistencia cerrada.');
        }

        $validated = $request->validate([
            'chapel_adultos_hombres' => 'required|integer|min:0',
            'chapel_adultos_mujeres' => 'required|integer|min:0',
            'chapel_adultos_mayores_hombres' => 'required|integer|min:0',
            'chapel_adultos_mayores_mujeres' => 'required|integer|min:0',
            'chapel_jovenes_masculinos' => 'required|integer|min:0',
            'chapel_jovenes_femeninas' => 'required|integer|min:0',
            'chapel_maestros_hombres' => 'required|integer|min:0',
            'chapel_maestros_mujeres' => 'required|integer|min:0',
            'clase_0_1_hombres' => 'required|integer|min:0',
            'clase_0_1_mujeres' => 'required|integer|min:0',
            'clase_0_1_maestros_hombres' => 'required|integer|min:0',
            'clase_0_1_maestros_mujeres' => 'required|integer|min:0',
            'clase_2_6_hombres' => 'required|integer|min:0',
            'clase_2_6_mujeres' => 'required|integer|min:0',
            'clase_2_6_maestros_hombres' => 'required|integer|min:0',
            'clase_2_6_maestros_mujeres' => 'required|integer|min:0',
            'clase_7_8_hombres' => 'required|integer|min:0',
            'clase_7_8_mujeres' => 'required|integer|min:0',
            'clase_7_8_maestros_hombres' => 'required|integer|min:0',
            'clase_7_8_maestros_mujeres' => 'required|integer|min:0',
            'clase_9_11_hombres' => 'required|integer|min:0',
            'clase_9_11_mujeres' => 'required|integer|min:0',
            'clase_9_11_maestros_hombres' => 'required|integer|min:0',
            'clase_9_11_maestros_mujeres' => 'required|integer|min:0',
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
        ]);

        $asistencium->update($validated);

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

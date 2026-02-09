<?php

namespace App\Http\Controllers;

use App\Models\Culto;
use Illuminate\Http\Request;

class CultoController extends Controller
{
    public function index()
    {
        $cultos = Culto::with(['totales', 'asistencia', 'sobres.detalles', 'ofrendasSueltas'])
            ->orderBy('fecha', 'desc')
            ->paginate(20);
            
        return view('cultos.index', compact('cultos'));
    }

    public function create()
    {
        return view('cultos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fecha' => 'required|date',
            'hora' => 'required',
            'tipo_culto' => 'required|in:domingo,domingo_pm,miércoles,sábado,especial',
            'notas' => 'nullable|string',
        ]);

        Culto::create($validated);

        return redirect()->route('cultos.index')
            ->with('success', 'Culto registrado correctamente.');
    }

    public function show(Culto $culto)
    {
        $sobres = $culto->sobres()->with(['persona', 'detalles'])->get();
        $ofrendasSueltas = $culto->ofrendasSueltas;
        
        return view('recuento.index', [
            'sobres' => $sobres,
            'cultos' => Culto::where('cerrado', false)->orderBy('fecha', 'desc')->get(),
            'cultoSeleccionado' => $culto,
            'ofrendasSueltas' => $ofrendasSueltas,
            'cultosCerrados' => Culto::where('cerrado', true)->with('totales')->orderBy('cerrado_at', 'desc')->get()
        ]);
    }

    public function edit(Culto $culto)
    {
        return view('cultos.edit', compact('culto'));
    }

    public function update(Request $request, Culto $culto)
    {
        $validated = $request->validate([
            'fecha' => 'required|date',
            'hora' => 'required',
            'tipo_culto' => 'required|in:domingo,domingo_pm,miércoles,sábado,especial',
            'notas' => 'nullable|string',
        ]);

        $culto->update($validated);

        return redirect()->route('cultos.index')
            ->with('success', 'Culto actualizado correctamente.');
    }

    public function destroy(Culto $culto)
    {
        $culto->delete();

        return redirect()->route('cultos.index')
            ->with('success', 'Culto eliminado correctamente.');
    }
}

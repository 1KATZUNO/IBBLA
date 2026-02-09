<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Promesa;
use Illuminate\Http\Request;

class PromesaController extends Controller
{
    public function index()
    {
        $promesas = Promesa::with('persona')->paginate(20);
        return view('promesas.index', compact('promesas'));
    }

    public function create()
    {
        $personas = Persona::where('activo', true)->get();
        return view('promesas.create', compact('personas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'categoria' => 'required|string',
            'monto' => 'required|numeric|min:0',
            'frecuencia' => 'required|in:semanal,quincenal,mensual',
        ]);

        Promesa::create($validated);

        return redirect()->route('promesas.index')
            ->with('success', 'Promesa registrada correctamente.');
    }

    public function show(Promesa $promesa)
    {
        $promesa->load('persona');
        return view('promesas.show', compact('promesa'));
    }

    public function edit(Promesa $promesa)
    {
        $personas = Persona::where('activo', true)->get();
        return view('promesas.edit', compact('promesa', 'personas'));
    }

    public function update(Request $request, Promesa $promesa)
    {
        $validated = $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'categoria' => 'required|string',
            'monto' => 'required|numeric|min:0',
            'frecuencia' => 'required|in:semanal,quincenal,mensual',
        ]);

        $promesa->update($validated);

        return redirect()->route('promesas.index')
            ->with('success', 'Promesa actualizada correctamente.');
    }

    public function destroy(Promesa $promesa)
    {
        $promesa->delete();

        return redirect()->route('promesas.index')
            ->with('success', 'Promesa eliminada correctamente.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ClaseAsistencia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClaseAsistenciaController extends Controller
{
    public function index()
    {
        $clases = ClaseAsistencia::orderBy('orden')->get();
        return view('admin.clases.index', compact('clases'));
    }

    public function create()
    {
        return view('admin.clases.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'orden' => 'required|integer|min:0',
            'tiene_maestros' => 'boolean',
        ]);

        // Generar slug automático
        $slug = Str::slug($validated['nombre'], '_');
        $slug = str_replace('-', '_', $slug);
        
        // Asegurar que sea único
        $originalSlug = $slug;
        $counter = 1;
        while (ClaseAsistencia::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '_' . $counter;
            $counter++;
        }

        ClaseAsistencia::create([
            'nombre' => $validated['nombre'],
            'slug' => $slug,
            'color' => $validated['color'],
            'orden' => $validated['orden'],
            'tiene_maestros' => $request->has('tiene_maestros'),
            'activa' => true,
        ]);

        return redirect()->route('admin.clases.index')
            ->with('success', 'Clase creada correctamente.');
    }

    public function edit(ClaseAsistencia $clase)
    {
        return view('admin.clases.edit', compact('clase'));
    }

    public function update(Request $request, ClaseAsistencia $clase)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'orden' => 'required|integer|min:0',
            'tiene_maestros' => 'boolean',
            'activa' => 'boolean',
        ]);

        $clase->update([
            'nombre' => $validated['nombre'],
            'color' => $validated['color'],
            'orden' => $validated['orden'],
            'tiene_maestros' => $request->has('tiene_maestros'),
            'activa' => $request->has('activa'),
        ]);

        return redirect()->route('admin.clases.index')
            ->with('success', 'Clase actualizada correctamente.');
    }

    public function destroy(ClaseAsistencia $clase)
    {
        $clase->delete();

        return redirect()->route('admin.clases.index')
            ->with('success', 'Clase eliminada correctamente.');
    }
}

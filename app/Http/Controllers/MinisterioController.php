<?php

namespace App\Http\Controllers;

use App\Models\Ministerio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD de ministerios / áreas de servicio (solo admin).
 * Roy puede crear nuevos, editar y desactivar desde /admin/ministerios.
 * Eliminar también, pero la migración tiene cascade en personas_ministerio:
 * borrar un ministerio elimina las asignaciones de personas (advertir en UI).
 */
class MinisterioController extends Controller
{
    public function index(): View
    {
        $ministerios = Ministerio::ordenados()->withCount('personasActivas')->get();

        return view('admin.ministerios.index', compact('ministerios'));
    }

    public function create(): View
    {
        return view('admin.ministerios.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validar($request);
        Ministerio::create($validated);

        return redirect()->route('admin.ministerios.index')->with('success', 'Ministerio creado.');
    }

    public function edit(Ministerio $ministerio): View
    {
        return view('admin.ministerios.edit', compact('ministerio'));
    }

    public function update(Request $request, Ministerio $ministerio): RedirectResponse
    {
        $validated = $this->validar($request, $ministerio);
        $ministerio->update($validated);

        return redirect()->route('admin.ministerios.index')->with('success', 'Ministerio actualizado.');
    }

    public function destroy(Ministerio $ministerio): RedirectResponse
    {
        $ministerio->delete();

        return redirect()->route('admin.ministerios.index')->with('success', 'Ministerio eliminado.');
    }

    protected function validar(Request $request, ?Ministerio $existente = null): array
    {
        $tenantId = auth()->user()->tenant_id;
        $uniqueSlug = 'unique:ministerios,slug,'.($existente?->id ?? 'NULL').',id,tenant_id,'.$tenantId;

        return $request->validate([
            'nombre' => 'required|string|max:80',
            'slug' => "required|string|max:80|regex:/^[a-z0-9_-]+$/|{$uniqueSlug}",
            'color' => 'required|string|max:9',
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'sometimes|boolean',
            'orden' => 'nullable|integer|min:0',
        ]);
    }
}

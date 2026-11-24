<?php

namespace App\Http\Controllers;

use App\Models\Ministerio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD de ministerios / áreas de servicio (solo admin) en /admin/ministerios.
 *
 * - Crear / editar a mano: el slug se genera solo desde el nombre si no se escribe.
 * - Desactivar: oculta el ministerio de los selectores de Personas pero conserva las
 *   asignaciones y su historial. Es lo recomendado cuando ya tiene gente.
 * - Eliminar: solo si NO tiene personas asignadas (activas ni históricas). La tabla
 *   pivote borra en cascada, así que eliminar uno con gente destruiría el historial
 *   de servicio de esas personas.
 */
class MinisterioController extends Controller
{
    public function index(): View
    {
        $ministerios = Ministerio::ordenados()
            ->withCount([
                'personas',
                'personasActivas as personas_activas_count',
            ])
            ->get();

        return view('admin.ministerios.index', compact('ministerios'));
    }

    public function create(): View
    {
        return view('admin.ministerios.create');
    }

    public function store(Request $request): RedirectResponse
    {
        // El super admin no pertenece a ninguna iglesia: un ministerio sin iglesia no
        // existe (tenant_id es obligatorio), así que se corta con un mensaje claro.
        if (! $request->user()->tenant_id) {
            return back()->withInput()->withErrors([
                'nombre' => 'Los ministerios se crean desde una cuenta de iglesia. Entra con un usuario administrador de la iglesia.',
            ]);
        }

        $validated = $this->validar($request);
        $validated['orden'] = $validated['orden'] ?? null; // null => al final de la lista
        $validated['activo'] = $validated['activo'] ?? true;
        $ministerio = Ministerio::create($validated);

        return redirect()->route('admin.ministerios.index')
            ->with('success', "Ministerio «{$ministerio->nombre}» creado.");
    }

    public function edit(Ministerio $ministerio): View
    {
        $personas = $ministerio->personas()
            ->orderByRaw('persona_ministerio.hasta IS NOT NULL')   // activas primero
            ->orderBy('personas.nombre')
            ->get();

        return view('admin.ministerios.edit', compact('ministerio', 'personas'));
    }

    public function update(Request $request, Ministerio $ministerio): RedirectResponse
    {
        $validated = $this->validar($request, $ministerio);

        // La columna orden no admite NULL: si el campo viene vacío se conserva el actual.
        if (($validated['orden'] ?? null) === null) {
            unset($validated['orden']);
        }
        // Slug vacío al editar = no tocarlo (cambiarlo no aporta nada y es un identificador).
        if (blank($validated['slug'] ?? null)) {
            unset($validated['slug']);
        }

        $ministerio->update($validated);

        return redirect()->route('admin.ministerios.index')
            ->with('success', "Ministerio «{$ministerio->nombre}» actualizado.");
    }

    public function toggle(Ministerio $ministerio): RedirectResponse
    {
        $ministerio->update(['activo' => ! $ministerio->activo]);

        $mensaje = $ministerio->activo
            ? "Ministerio «{$ministerio->nombre}» activado."
            : "Ministerio «{$ministerio->nombre}» desactivado: ya no aparece al asignar personas, pero se conservan sus asignaciones.";

        return redirect()->route('admin.ministerios.index')->with('success', $mensaje);
    }

    public function destroy(Ministerio $ministerio): RedirectResponse
    {
        $asignadas = $ministerio->personas()->count();

        if ($asignadas > 0) {
            return redirect()->route('admin.ministerios.index')->with(
                'error',
                "No se puede eliminar «{$ministerio->nombre}»: tiene {$asignadas} ".
                ($asignadas === 1 ? 'persona asignada' : 'personas asignadas').'. '.
                'Desactívalo para ocultarlo (se conserva el historial) o quita primero las asignaciones desde Personas.'
            );
        }

        $nombre = $ministerio->nombre;
        $ministerio->delete();

        return redirect()->route('admin.ministerios.index')
            ->with('success', "Ministerio «{$nombre}» eliminado.");
    }

    protected function validar(Request $request, ?Ministerio $existente = null): array
    {
        // Al editar se usa la iglesia del propio ministerio (por si lo edita un super admin).
        $tenantId = $existente?->tenant_id ?? $request->user()->tenant_id;

        // Los nombres son únicos por iglesia (sin distinguir mayúsculas: lo resuelve la
        // collation de MySQL) y el slug también, porque la BD tiene unique(tenant_id, slug).
        $unicoEnIglesia = fn (string $columna) => Rule::unique('ministerios', $columna)
            ->where('tenant_id', $tenantId)
            ->ignore($existente?->id);

        return $request->validate([
            'nombre' => ['required', 'string', 'max:80', $unicoEnIglesia('nombre')],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/', $unicoEnIglesia('slug')],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'nombre.unique' => 'Ya existe un ministerio con ese nombre.',
            'slug.unique' => 'Ya existe un ministerio con ese identificador (slug).',
            'slug.regex' => 'El identificador solo admite minúsculas, números, guion y guion bajo.',
            'color.regex' => 'El color debe tener formato #RRGGBB.',
        ]);
    }
}

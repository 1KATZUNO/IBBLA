<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tenant = tenant();
        $usuarios = User::where('tenant_id', $tenant?->id)
            ->where('is_super_admin', false)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('admin.usuarios.index', compact('usuarios'));
    }

    /**
     * QE-020: no hay pantalla de detalle de un usuario; /usuarios/{id} lleva a la lista (antes 405).
     */
    public function show(string $usuario)
    {
        return redirect()->route('usuarios.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $tenant = tenant();
        $roles = $tenant ? $tenant->roles()->orderBy('orden')->get() : collect();

        return view('admin.usuarios.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $tenant = tenant();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'telefono' => $this->reglaTelefono(),
            'password' => 'required|string|min:8|confirmed',
            'rol' => 'required|in:admin,tesorero,asistente,servidor,miembro',
            'tenant_role_id' => $this->reglaRolDeLaIglesia(),
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['tenant_id'] = $tenant?->id;

        // Assign tenant role if provided, otherwise find by legacy slug
        if (empty($validated['tenant_role_id']) && $tenant) {
            $tenantRole = $tenant->roles()->where('slug', $validated['rol'])->first();
            $validated['tenant_role_id'] = $tenantRole?->id;
        }

        User::create($validated);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado exitosamente.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $usuario)
    {
        $this->soloDeEstaIglesia($usuario);
        $tenant = tenant();
        $roles = $tenant ? $tenant->roles()->orderBy('orden')->get() : collect();

        return view('admin.usuarios.edit', compact('usuario', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $usuario)
    {
        $this->soloDeEstaIglesia($usuario);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($usuario->id)],
            'telefono' => $this->reglaTelefono(),
            'password' => 'nullable|string|min:8|confirmed',
            'rol' => 'required|in:admin,tesorero,asistente,servidor,miembro',
            'tenant_role_id' => $this->reglaRolDeLaIglesia(),
        ]);

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Assign tenant role if provided, otherwise find by legacy slug
        $tenant = tenant();
        if (empty($validated['tenant_role_id']) && $tenant) {
            $tenantRole = $tenant->roles()->where('slug', $validated['rol'])->first();
            $validated['tenant_role_id'] = $tenantRole?->id;
        }

        $usuario->update($validated);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado exitosamente.');
    }

    /**
     * QA-012: el administrador de una iglesia solo ve y cambia usuarios de SU iglesia, y nunca a un
     * Super Admin (ni para editarlo ni para borrarlo). Para cualquier otro usuario la ruta no existe.
     */
    private function soloDeEstaIglesia(User $usuario): void
    {
        $actual = auth()->user();
        $deOtraIglesia = (int) $usuario->tenant_id !== (int) ($actual->tenant_id ?? tenant()?->id);

        if ($usuario->isSuperAdmin() || ($deOtraIglesia && ! $actual->isSuperAdmin())) {
            abort(404);
        }
    }

    /** El rol dinamico elegido tiene que ser de esta iglesia. */
    private function reglaRolDeLaIglesia(): array
    {
        $tenantId = auth()->user()->tenant_id ?? tenant()?->id;

        return ['nullable', Rule::exists('tenant_roles', 'id')->where('tenant_id', $tenantId)];
    }

    /**
     * Teléfono opcional del usuario: se usa para los avisos de WhatsApp (culto creado, sobres alcanzados).
     * Si se escribe, tiene que poder convertirse a un número de WhatsApp; así un dato mal tecleado se
     * corrige al guardarlo y no cuando el aviso no llega.
     *
     * @return array<int, mixed>
     */
    private function reglaTelefono(): array
    {
        return ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail) {
            if (WhatsAppService::normalizarTelefono($value) === null) {
                $fail('El teléfono no es válido para WhatsApp. Escriba 8 dígitos (por ejemplo 8888-7777) o con prefijo internacional (+506 8888-7777).');
            }
        }];
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $usuario)
    {
        $this->soloDeEstaIglesia($usuario);

        // No permitir eliminar el propio usuario
        if ($usuario->id === auth()->id()) {
            return redirect()->route('usuarios.index')
                ->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario eliminado exitosamente.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            'password' => 'required|string|min:8|confirmed',
            'rol' => 'required|in:admin,tesorero,asistente,servidor,miembro,invitado',
            'tenant_role_id' => 'nullable|exists:tenant_roles,id',
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
     * Display the specified resource.
     */
    public function show(User $usuario)
    {
        return view('admin.usuarios.show', compact('usuario'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $usuario)
    {
        $tenant = tenant();
        $roles = $tenant ? $tenant->roles()->orderBy('orden')->get() : collect();

        return view('admin.usuarios.edit', compact('usuario', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $usuario)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($usuario->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'rol' => 'required|in:admin,tesorero,asistente,servidor,miembro,invitado',
            'tenant_role_id' => 'nullable|exists:tenant_roles,id',
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
     * Remove the specified resource from storage.
     */
    public function destroy(User $usuario)
    {
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

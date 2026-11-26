<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Persona;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'persona_id' => $this->reglaPersona(null),
        ], $this->mensajesPersona());

        $personaId = $validated['persona_id'] ?? null;
        unset($validated['persona_id']);

        $contrasena = $validated['password'];
        unset($validated['password']);
        $validated['tenant_id'] = $tenant?->id;

        // Assign tenant role if provided, otherwise find by legacy slug
        if (empty($validated['tenant_role_id']) && $tenant) {
            $tenantRole = $tenant->roles()->where('slug', $validated['rol'])->first();
            $validated['tenant_role_id'] = $tenantRole?->id;
        }

        // Hash normal y, si es una cuenta miembro, la copia que el administrador puede ver (Tarjetas QR)
        $usuario = new User($validated);
        $usuario->establecerContrasena($contrasena);
        $usuario->save();
        $persona = $this->vincularPersona($usuario, $personaId);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado exitosamente.'.($persona ? " Quedó vinculado con {$persona->nombre}." : ''));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $usuario)
    {
        $this->soloDeEstaIglesia($usuario);
        $tenant = tenant();
        $roles = $tenant ? $tenant->roles()->orderBy('orden')->get() : collect();

        $personaVinculada = Persona::where('user_id', $usuario->id)->first();

        return view('admin.usuarios.edit', compact('usuario', 'roles', 'personaVinculada'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $usuario)
    {
        $this->soloDeEstaIglesia($usuario);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Quien creo su acceso con la tarjeta QR con nombre de usuario puede no tener correo
            'email' => [$usuario->usuario ? 'nullable' : 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($usuario->id)],
            'telefono' => $this->reglaTelefono(),
            'password' => 'nullable|string|min:8|confirmed',
            'rol' => 'required|in:admin,tesorero,asistente,servidor,miembro',
            'tenant_role_id' => $this->reglaRolDeLaIglesia(),
            'persona_id' => $this->reglaPersona($usuario),
        ], $this->mensajesPersona());

        $personaId = $validated['persona_id'] ?? null;
        unset($validated['persona_id']);

        $contrasena = $request->filled('password') ? $validated['password'] : null;
        unset($validated['password']);

        // Assign tenant role if provided, otherwise find by legacy slug
        $tenant = tenant();
        if (empty($validated['tenant_role_id']) && $tenant) {
            $tenantRole = $tenant->roles()->where('slug', $validated['rol'])->first();
            $validated['tenant_role_id'] = $tenantRole?->id;
        }

        $usuario->fill($validated);
        if ($contrasena !== null) {
            $usuario->establecerContrasena($contrasena);
        }
        $usuario->save();
        $this->vincularPersona($usuario, $personaId);

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

    /**
     * QA-018: buscador de personas para vincular con un usuario (nombre, PIN o teléfono). Devuelve hasta
     * 10, de esta iglesia, y dice si ya tienen otro usuario (no se pueden elegir).
     */
    public function buscarPersonas(Request $request): JsonResponse
    {
        $texto = trim((string) $request->query('q', ''));
        if (mb_strlen($texto) < 2) {
            return response()->json([]);
        }

        $digitos = preg_replace('/\D+/', '', $texto);
        $tenantId = auth()->user()->tenant_id ?? tenant()?->id;

        $personas = Persona::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where(function ($q) use ($texto, $digitos) {
                $q->where('nombre', 'like', '%'.$texto.'%')->orWhere('pin', $texto);
                if (strlen($digitos) >= 4) {
                    $q->orWhereRaw("REPLACE(REPLACE(telefono, '-', ''), ' ', '') like ?", ['%'.$digitos.'%']);
                }
            })
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->limit(10)
            ->get(['id', 'nombre', 'pin', 'telefono', 'activo', 'user_id']);

        $ignorar = $request->integer('usuario') ?: null;

        return response()->json($personas->map(fn (Persona $p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'detalle' => collect([$p->pin ? 'PIN '.$p->pin : null, $p->telefono, $p->activo ? null : 'inactiva'])->filter()->join(' · '),
            'ocupada' => $p->user_id !== null && (int) $p->user_id !== (int) $ignorar,
        ])->values());
    }

    /** La persona elegida es de esta iglesia y no tiene otro usuario. */
    private function reglaPersona(?User $usuario): array
    {
        $tenantId = auth()->user()->tenant_id ?? tenant()?->id;

        return ['nullable', 'integer', Rule::exists('personas', 'id')->where(function ($q) use ($tenantId, $usuario) {
            if ($tenantId) {
                $q->where('tenant_id', $tenantId);
            }
            $q->where(fn ($libre) => $libre->whereNull('user_id')->when($usuario, fn ($w) => $w->orWhere('user_id', $usuario->id)));
        })];
    }

    private function mensajesPersona(): array
    {
        return ['persona_id.exists' => 'Esa persona ya tiene otro usuario o no es de esta iglesia. Elija otra.'];
    }

    /**
     * Deja al usuario vinculado con esa persona (o sin persona si es null): suelta la que tenía antes
     * y queda en la auditoría. Así "Mi perfil" encuentra su ficha y su estado de cuenta.
     */
    private function vincularPersona(User $usuario, ?int $personaId): ?Persona
    {
        $anterior = Persona::where('user_id', $usuario->id)->first();
        if ($anterior?->id === $personaId) {
            return $anterior;
        }

        if ($anterior) {
            $anterior->forceFill(['user_id' => null])->save();
        }

        $persona = $personaId ? Persona::find($personaId) : null;
        if ($persona) {
            $persona->forceFill(['user_id' => $usuario->id])->save();
        }

        AuditLog::registrar($persona ? 'Vincular usuario con persona' : 'Quitar persona del usuario', [
            'usuario_id' => $usuario->id,
            'usuario' => $usuario->name,
            'persona_id' => $persona?->id,
            'persona' => $persona?->nombre,
            'persona_anterior_id' => $anterior?->id,
            'persona_anterior' => $anterior?->nombre,
        ]);

        return $persona;
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

<?php

namespace App\Http\Controllers;

use App\Models\Culto;
use App\Models\PermisoEdicion;
use App\Models\User;
use App\Services\PermisosEdicionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Modulo Accesos (/admin/accesos, solo administrador): darle a un usuario de tesoreria permiso de
 * EDICION sobre cultos cerrados (uno, varios, un rango de fechas o todos) con vencimiento opcional,
 * ver los accesos vigentes, revocarlos y ver lo que se cambio con ellos.
 */
class AccesoEdicionController extends Controller
{
    public function __construct(private PermisosEdicionService $permisos) {}

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $vigentes = PermisoEdicion::with(['user:id,name,email', 'otorgadoPor:id,name'])
            ->vigentes()
            ->orderByDesc('id')
            ->get();
        $historial = PermisoEdicion::with(['user:id,name,email', 'otorgadoPor:id,name', 'revocadoPor:id,name'])
            ->noVigentes()
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        // Los cultos mencionados por los accesos, de una vez (para describir el alcance)
        $idsCultos = $vigentes->concat($historial)->flatMap(fn (PermisoEdicion $p) => $p->idsCultos())->unique()->values();
        $cultosPorId = Culto::with('serviceType')->whereIn('id', $idsCultos)->get()->keyBy('id');

        return view('admin.accesos.index', [
            'usuarios' => $this->permisos->usuariosElegibles($tenantId),
            'vigentes' => $vigentes,
            'historial' => $historial,
            'cultosPorId' => $cultosPorId,
            'ediciones' => $this->permisos->edicionesPorPermiso($vigentes->concat($historial), $tenantId),
            // Para elegir cultos concretos: solo los cerrados (los abiertos tesoreria ya los edita), los mas recientes primero
            'cultos' => Culto::with(['serviceType', 'cerradoPor:id,name'])->where('cerrado', true)->orderByDesc('fecha')->orderByDesc('hora')->limit(150)->get(),
            'cultoPreseleccionado' => $request->integer('culto_id') ?: null,
            'ultimosCambios' => $this->permisos->ultimosCambiosEnCerrados($tenantId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $datos = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'tipo' => ['required', Rule::in(PermisoEdicion::TIPOS)],
            'cultos' => ['exclude_unless:tipo,cultos', 'required', 'array', 'min:1'],
            'cultos.*' => ['integer', Rule::exists('cultos', 'id')->where('tenant_id', $tenantId)],
            'desde' => ['exclude_unless:tipo,rango', 'required', 'date'],
            'hasta' => ['exclude_unless:tipo,rango', 'required', 'date', 'after_or_equal:desde'],
            'vence_el' => ['nullable', 'date', 'after_or_equal:today'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ], [
            'user_id.required' => 'Elige a quién le das el acceso.',
            'tipo.required' => 'Elige sobre qué cultos es el acceso.',
            'cultos.required' => 'Marca al menos un culto.',
            'cultos.min' => 'Marca al menos un culto.',
            'desde.required' => 'Indica desde qué fecha.',
            'hasta.required' => 'Indica hasta qué fecha.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'vence_el.after_or_equal' => 'El vencimiento no puede ser una fecha pasada.',
        ]);

        $usuario = User::findOrFail($datos['user_id']);
        if (! $this->permisos->esElegible($usuario)) {
            return back()->withInput()->withErrors([
                'user_id' => "{$usuario->name} no entra a Recuento o ya es administrador. Para darle acceso primero debe tener el rol de tesorero.",
            ]);
        }

        $permiso = $this->permisos->conceder($request->user(), $usuario, $datos, $request);
        $alcance = $permiso->descripcionAlcance(Culto::whereIn('id', $permiso->idsCultos())->get()->keyBy('id'));
        $vence = $permiso->vence_el ? ' hasta el '.$permiso->vence_el->format('d/m/Y') : ' sin vencimiento';

        return redirect()->route('admin.accesos.index')
            ->with('success', "{$usuario->name} ya puede corregir: {$alcance}{$vence}.");
    }

    public function destroy(Request $request, PermisoEdicion $permiso): RedirectResponse
    {
        $this->permisos->revocar($request->user(), $permiso, $request);

        return redirect()->route('admin.accesos.index')
            ->with('success', 'Acceso de '.($permiso->user?->name ?? 'ese usuario').' revocado: desde ahora ve esos cultos en solo lectura.');
    }
}

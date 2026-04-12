<?php

namespace App\Http\Controllers;

use App\Models\Culto;
use App\Models\TenantServiceType;
use Illuminate\Http\Request;

class CultoController extends Controller
{
    public function index()
    {
        $cultos = Culto::with(['totales', 'asistencia', 'sobres.detalles', 'ofrendasSueltas', 'serviceType'])
            ->orderBy('fecha', 'desc')
            ->paginate(20);

        return view('cultos.index', compact('cultos'));
    }

    public function create()
    {
        $serviceTypes = $this->getServiceTypes();

        return view('cultos.create', compact('serviceTypes'));
    }

    public function store(Request $request)
    {
        $serviceTypes = $this->getServiceTypes();
        $validSlugs = $serviceTypes->pluck('slug')->implode(',');

        $validated = $request->validate([
            'fecha' => 'required|date',
            'hora' => 'required',
            'tipo_culto' => 'required|in:'.$validSlugs,
            'notas' => 'nullable|string',
        ]);

        $serviceType = $serviceTypes->firstWhere('slug', $validated['tipo_culto']);

        $data = $validated;
        if ($serviceType && $serviceType->id) {
            $data['service_type_id'] = $serviceType->id;
            $data['tenant_id'] = $serviceType->tenant_id;
        } elseif (auth()->user()->tenant_id) {
            $data['tenant_id'] = auth()->user()->tenant_id;
        }

        Culto::create($data);

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
            'cultosCerrados' => Culto::where('cerrado', true)->with('totales')->orderBy('cerrado_at', 'desc')->get(),
        ]);
    }

    public function edit(Culto $culto)
    {
        $serviceTypes = $this->getServiceTypes();

        return view('cultos.edit', compact('culto', 'serviceTypes'));
    }

    public function update(Request $request, Culto $culto)
    {
        $serviceTypes = $this->getServiceTypes();
        $validSlugs = $serviceTypes->pluck('slug')->implode(',');

        $validated = $request->validate([
            'fecha' => 'required|date',
            'hora' => 'required',
            'tipo_culto' => 'required|in:'.$validSlugs,
            'notas' => 'nullable|string',
        ]);

        $serviceType = $serviceTypes->firstWhere('slug', $validated['tipo_culto']);
        if ($serviceType && $serviceType->id) {
            $validated['service_type_id'] = $serviceType->id;
        }

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

    private function getServiceTypes()
    {
        $user = auth()->user();

        if ($user && $user->tenant_id) {
            $types = TenantServiceType::where('tenant_id', $user->tenant_id)
                ->where('activo', true)
                ->orderBy('orden')
                ->get();

            if ($types->isNotEmpty()) {
                return $types;
            }
        }

        // Fallback: tipos por defecto para compatibilidad
        return collect([
            (object) ['id' => null, 'slug' => 'domingo', 'nombre' => 'Domingo AM', 'tenant_id' => $user->tenant_id ?? null],
            (object) ['id' => null, 'slug' => 'domingo_pm', 'nombre' => 'Domingo PM', 'tenant_id' => $user->tenant_id ?? null],
            (object) ['id' => null, 'slug' => 'miercoles', 'nombre' => 'Miercoles', 'tenant_id' => $user->tenant_id ?? null],
            (object) ['id' => null, 'slug' => 'sabado', 'nombre' => 'Sabado', 'tenant_id' => $user->tenant_id ?? null],
            (object) ['id' => null, 'slug' => 'especial', 'nombre' => 'Especial', 'tenant_id' => $user->tenant_id ?? null],
        ]);
    }
}

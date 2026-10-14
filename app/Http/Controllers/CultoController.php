<?php

namespace App\Http\Controllers;

use App\Events\CultoCreado;
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

    public function create(Request $request)
    {
        $serviceTypes = $this->getServiceTypes();
        $desdeRecuento = $this->vieneDeRecuento($request);

        return view('cultos.create', compact('serviceTypes', 'desdeRecuento'));
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

        $culto = Culto::create($data);

        // Avisa por WhatsApp a los líderes (un fallo al preparar los avisos no impide crear el culto).
        CultoCreado::dispatch($culto);

        // Desde Recuento (o quien no administra cultos, p. ej. un tesorero con acceso de edicion)
        // se sigue directo a registrar los sobres del culto nuevo.
        if ($this->vieneDeRecuento($request)) {
            return redirect()->route('recuento.culto', $culto)
                ->with('success', 'Culto creado. Ya puedes registrar sus sobres.');
        }

        return redirect()->route('cultos.index')
            ->with('success', 'Culto registrado correctamente.');
    }

    /** El detalle de un culto es su pantalla de Recuento (sobres, totales y firmas). */
    public function show(Culto $culto)
    {
        return redirect()->route('recuento.culto', $culto);
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

    /** El formulario se abrio desde Recuento, o quien lo usa no gestiona cultos (no entra a la lista de admin). */
    private function vieneDeRecuento(Request $request): bool
    {
        return $request->input('desde') === 'recuento' || ! $request->user()->canAccessAdmin();
    }

    private function getServiceTypes()
    {
        return TenantServiceType::disponiblesPara(auth()->user());
    }
}

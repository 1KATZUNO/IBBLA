<?php

namespace App\Http\Controllers;

use App\Events\CultoCreado;
use App\Models\AuditLog;
use App\Models\Culto;
use App\Models\TenantServiceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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

    /**
     * Eliminar un culto (QA-003 y decision del cliente 13/10): solo el administrador. Un culto
     * cerrado o con sobres, dinero suelto o egresos exige confirmar y escribir el motivo, y en la
     * auditoria queda lo que se borro (culto, fecha, total, cuantos sobres y cuales).
     */
    public function destroy(Request $request, Culto $culto)
    {
        Gate::authorize('delete', $culto);

        $culto->load(['totales', 'cerradoPor:id,name', 'sobres.persona:id,nombre']);
        $cantidadSobres = $culto->sobres->count();
        $conDatos = $culto->cerrado || $cantidadSobres > 0 || $culto->ofrendasSueltas()->exists() || $culto->egresos()->exists();

        if ($conDatos) {
            $request->validate([
                'motivo' => 'required|string|min:10|max:500',
                'confirmo' => 'accepted',
            ], [
                'motivo.required' => 'Escribe por qué se elimina este culto (queda en la auditoría).',
                'motivo.min' => 'El motivo tiene que explicar un poco más (al menos 10 letras).',
                'confirmo.accepted' => 'Marca que entiendes que se borran el culto y todo su recuento.',
            ]);
        }

        $detalles = [
            'culto_id' => $culto->id,
            'fecha' => $culto->fecha->toDateString(),
            'tipo' => $culto->tipo_nombre,
            'cerrado' => (bool) $culto->cerrado,
            'cerrado_por' => $culto->cerradoPor?->name,
            'total_general' => (float) ($culto->totales->total_general ?? 0),
            'cantidad_sobres' => $cantidadSobres,
            'cantidad_ofrendas_sueltas' => $culto->ofrendasSueltas()->count(),
            'cantidad_egresos' => $culto->egresos()->count(),
            'motivo' => $conDatos ? trim((string) $request->input('motivo')) : null,
            // Lo minimo para reconstruir que se perdio
            'sobres' => $culto->sobres->map(fn ($s) => [
                'numero' => $s->numero_sobre,
                'persona' => $s->persona?->nombre ?? 'Anónimo',
                'total' => (float) $s->total_declarado,
                'moneda' => $s->moneda ?? 'CRC',
                'metodo' => $s->metodo_pago,
            ])->values()->all(),
        ];

        DB::transaction(function () use ($culto, $detalles) {
            AuditLog::registrar('Eliminar culto', $detalles, 'cultos.destroy');
            $culto->delete();
        });

        return redirect()->route('cultos.index')
            ->with('success', 'Culto del '.$culto->fecha->format('d/m/Y').' eliminado'.($cantidadSobres ? " con sus {$cantidadSobres} sobres" : '').'. Quedó registrado en la auditoría.');
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

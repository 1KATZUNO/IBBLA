<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Culto;
use App\Models\Egreso;
use App\Models\OfrendaSuelta;
use App\Models\Persona;
use App\Models\Sobre;
use App\Models\SobreDetalle;
use App\Models\TenantServiceType;
use App\Models\TipoCambio;
use App\Services\CalculoTotalesCultoService;
use App\Services\PermisosEdicionService;
use App\Services\WhatsApp\AvisosWhatsAppService;
use App\Support\ResumenCulto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecuentoController extends Controller
{
    public function __construct(
        protected CalculoTotalesCultoService $calculoService,
        protected PermisosEdicionService $permisosEdicion,
    ) {}

    /**
     * Lista de cultos por lineas: una fila por culto (fecha, dia, tipo, sobres, total, estado)
     * con buscador por fecha y filtros por mes, tipo y estado. Al tocar una linea se abre el culto.
     */
    public function index(Request $request)
    {
        // Enlaces de siempre (avisos de WhatsApp, marcadores, redirecciones viejas): ?culto_id=N abre ese culto.
        if ($request->filled('culto_id') && ctype_digit((string) $request->query('culto_id'))) {
            return redirect()->route('recuento.culto', ['culto' => (int) $request->query('culto_id')]);
        }

        $tipos = TenantServiceType::disponiblesPara($request->user());
        $filtros = $this->filtrosDeLista($request, $tipos->pluck('slug')->all());

        $consulta = $this->cultosFiltrados($filtros);
        $conteo = (clone $consulta)
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN cerrado = 1 THEN 0 ELSE 1 END), 0) AS abiertos')
            ->toBase()
            ->first();

        $cultos = $consulta
            ->when($filtros['estado'] === 'abiertos', fn (Builder $q) => $q->where('cerrado', false))
            ->when($filtros['estado'] === 'cerrados', fn (Builder $q) => $q->where('cerrado', true))
            ->with(['totales', 'serviceType', 'cerradoPor:id,name'])
            ->withCount('sobres')
            ->orderByDesc('fecha')
            ->orderByDesc('hora')
            ->paginate(25)
            ->withQueryString();

        $meses = Culto::query()
            ->orderByDesc('fecha')
            ->pluck('fecha')
            ->map(fn ($fecha) => Carbon::parse($fecha)->format('Y-m'))
            ->unique()
            ->mapWithKeys(fn ($mes) => [$mes => ucfirst(Carbon::createFromFormat('Y-m-d', $mes.'-01')->locale('es')->isoFormat('MMMM YYYY'))]);

        return view('recuento.index', [
            'cultos' => $cultos,
            'filtros' => $filtros,
            'meses' => $meses,
            'tipos' => $tipos,
            'conteo' => [
                'total' => (int) ($conteo->total ?? 0),
                'abiertos' => (int) ($conteo->abiertos ?? 0),
                'cerrados' => (int) ($conteo->total ?? 0) - (int) ($conteo->abiertos ?? 0),
            ],
            'hayFiltros' => (bool) array_filter($filtros),
        ]);
    }

    /**
     * Pantalla de un culto: encabezado con su resumen y acciones, sobres, dinero suelto y egresos,
     * resumen por categorias y firmas. Solo este culto: nada de otros cultos en la pantalla.
     */
    public function culto(Request $request, Culto $culto)
    {
        $culto->load(['totales', 'serviceType', 'cerradoPor:id,name']);
        $categorias = tenant_categories(['es_ofrenda_suelta' => false]);
        $sobres = $culto->sobres()->with(['persona', 'detalles'])->orderBy('numero_sobre')->get();
        $ofrendasSueltas = $culto->ofrendasSueltas()->orderBy('created_at')->get();
        $egresos = $culto->egresos()->orderBy('created_at')->get();
        $resumen = ResumenCulto::calcular($sobres, $ofrendasSueltas, $egresos, $categorias);
        // Una sola respuesta de la regla para toda la pantalla: habilita, deshabilita (con motivo) u oculta acciones
        $permisoEditar = Gate::inspect('puedeEditarCulto', $culto);

        // Sobres anonimos: se marcan, se pueden ver solos y se les asigna persona desde la fila
        $cantidadAnonimos = $sobres->whereNull('persona_id')->count();
        $soloAnonimos = $request->boolean('anonimos');
        $personasParaAsignar = $cantidadAnonimos > 0 && $permisoEditar->allowed()
            ? Persona::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'pin'])
            : collect();
        // Si el culto esta cerrado y lo puede corregir alguien que no es admin, es por un acceso de edicion
        $user = $request->user();
        $accesoUsado = $culto->cerrado && $permisoEditar->allowed() && ! $user->isAdmin() && ! $user->isSuperAdmin()
            ? $this->permisosEdicion->permisoQueCubre($user, $culto)
            : null;

        return view('recuento.culto', compact('culto', 'categorias', 'sobres', 'ofrendasSueltas', 'egresos', 'resumen', 'permisoEditar', 'accesoUsado',
            'cantidadAnonimos', 'soloAnonimos', 'personasParaAsignar'));
    }

    /** Antes un culto cerrado se veia en un modal; ahora tiene su pantalla (en solo lectura si corresponde). */
    public function verCultoCerrado(Culto $culto): RedirectResponse
    {
        return redirect()->route('recuento.culto', $culto);
    }

    /**
     * @param  array<int, string>  $slugsTipos
     * @return array{fecha: ?string, mes: ?string, tipo: ?string, estado: ?string}
     */
    private function filtrosDeLista(Request $request, array $slugsTipos): array
    {
        $fecha = (string) $request->query('fecha', '');
        $mes = (string) $request->query('mes', '');
        $tipo = (string) $request->query('tipo', '');
        $estado = (string) $request->query('estado', '');

        return [
            // Valores raros en la URL se ignoran: un filtro nunca debe dar error
            'fecha' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && strtotime($fecha) ? $fecha : null,
            'mes' => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) ? $mes : null,
            'tipo' => in_array($tipo, $slugsTipos, true) ? $tipo : null,
            'estado' => in_array($estado, ['abiertos', 'cerrados'], true) ? $estado : null,
        ];
    }

    /** Cultos de la iglesia con los filtros de fecha, mes y tipo (el de estado se aplica despues de contar). */
    private function cultosFiltrados(array $filtros): Builder
    {
        return Culto::query()
            ->when($filtros['fecha'], fn (Builder $q, string $fecha) => $q->whereDate('fecha', $fecha))
            ->when($filtros['mes'], function (Builder $q, string $mes) {
                $inicio = Carbon::createFromFormat('Y-m-d', $mes.'-01')->startOfDay();
                $q->whereBetween('fecha', [$inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()]);
            })
            ->when($filtros['tipo'], fn (Builder $q, string $slug) => $q->where(function (Builder $w) use ($slug) {
                $w->whereHas('serviceType', fn (Builder $s) => $s->where('slug', $slug))
                    // Cultos viejos sin tipo por iglesia: se comparan por la columna tipo_culto
                    ->orWhere(fn (Builder $l) => $l->whereNull('service_type_id')->where('tipo_culto', $slug));
            }));
    }

    /** Vuelve a la pantalla del culto con un mensaje (exito, error o aviso). */
    private function alCulto(int $cultoId, string $tipo, string $mensaje): RedirectResponse
    {
        return redirect()->route('recuento.culto', $cultoId)->with($tipo, $mensaje);
    }

    /**
     * Registrar sobres de un culto, uno tras otro (flujo en docs/plan-oct-2026/FLUJO-SOBRES.md):
     * solo ese culto en pantalla, con su avance y el ultimo sobre guardado.
     */
    public function create(Request $request)
    {
        $culto = $this->cultoPedido($request->query('culto_id'));
        Gate::authorize('puedeEditarCulto', $culto);

        $datos = $this->datosFormularioSobre($culto);
        $sobres = $culto->sobres()->with(['persona', 'detalles'])->orderBy('numero_sobre')->get();

        return view('recuento.create', $datos + [
            // El mismo resumen que la pantalla del culto, para que el avance cuadre
            'resumen' => ResumenCulto::calcular($sobres, $culto->ofrendasSueltas, $culto->egresos, $datos['categorias']),
            'ultimoSobre' => $sobres->last(),
            // Codigo de envio: si el mismo formulario llega dos veces, se guarda una sola
            'envio' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request)
    {
        $culto = $this->cultoPedido($request->input('culto_id'));
        Gate::authorize('puedeEditarCulto', $culto);

        $validated = $this->validarSobre($request);
        $siguiente = $request->boolean('siguiente');

        // Doble envio del mismo formulario (doble toque, volver atras y reenviar): se guarda una sola vez.
        // Sin codigo de envio (otros clientes) se considera duplicado el mismo sobre en 10 segundos.
        $envio = (string) $request->input('envio', '');
        if ($envio !== '') {
            if (! Cache::add('recuento:envio:'.$envio, true, now()->addHours(12))) {
                return $this->trasGuardarSobre($culto, $siguiente, 'warning', 'Ese sobre ya se había guardado: el formulario llegó dos veces y se registró una sola.');
            }
        } elseif ($this->hayDuplicadoReciente($culto, $validated)) {
            return $this->alCulto($culto->id, 'warning', 'Hace unos segundos se registró un sobre igual (misma persona, monto y método) en este culto, así que no se guardó de nuevo. Si era otro sobre, regístralo otra vez.');
        }

        [$moneda, $tipoCambioVenta, $tipoCambioId] = $this->monedaYTipoCambio($validated['moneda'] ?? null);

        $sobre = Sobre::create([
            'culto_id' => $culto->id,
            'persona_id' => $validated['persona_id'] ?? null,
            'metodo_pago' => $validated['metodo_pago'],
            'comprobante_numero' => $validated['metodo_pago'] === 'transferencia' ? ($validated['comprobante_numero'] ?? null) : null,
            'total_declarado' => $this->totalDetalles($validated['detalles']),
            'moneda' => $moneda,
            'tipo_cambio_venta' => $tipoCambioVenta,
            'tipo_cambio_id' => $tipoCambioId,
            'notas' => $validated['notas'] ?? null,
        ]);

        $this->guardarDetalles($sobre, $validated['detalles']);
        $this->auditar($request, 'POST', 'recuento.store', 'Agregar Sobre', $this->datosSobre($sobre));

        $this->despuesDeCambiar($request, $culto, 'Agregar sobre', $this->datosSobre($sobre));

        // Aviso por WhatsApp cuando el culto llega a N sobres (una sola vez por culto; nunca interrumpe el registro).
        app(AvisosWhatsAppService::class)->sobreRegistrado($culto);

        $quien = $sobre->persona?->nombre ?? 'Anónimo';

        return $this->trasGuardarSobre($culto, $siguiente, 'success',
            "Sobre #{$sobre->numero_sobre} guardado: {$quien}, {$sobre->simbolo_moneda}".number_format((float) $sobre->total_declarado, 2).'.');
    }

    public function edit(Sobre $sobre)
    {
        $culto = $this->cultoDe($sobre);
        Gate::authorize('puedeEditarCulto', $culto);

        $sobre->load(['detalles', 'persona']);

        return view('recuento.edit', $this->datosFormularioSobre($culto, $sobre) + ['sobre' => $sobre]);
    }

    public function update(Request $request, Sobre $sobre)
    {
        $culto = $this->cultoDe($sobre);
        Gate::authorize('puedeEditarCulto', $culto);

        $validated = $this->validarSobre($request);

        [$moneda, $tipoCambioVenta, $tipoCambioId] = $this->monedaYTipoCambio($validated['moneda'] ?? null);

        $sobre->update([
            'persona_id' => $validated['persona_id'] ?? null,
            'metodo_pago' => $validated['metodo_pago'],
            'comprobante_numero' => $validated['metodo_pago'] === 'transferencia' ? ($validated['comprobante_numero'] ?? null) : null,
            'total_declarado' => $this->totalDetalles($validated['detalles']),
            'moneda' => $moneda,
            'tipo_cambio_venta' => $tipoCambioVenta,
            'tipo_cambio_id' => $tipoCambioId,
            'notas' => $validated['notas'] ?? null,
        ]);

        $sobre->detalles()->delete();
        $this->guardarDetalles($sobre, $validated['detalles']);
        $this->auditar($request, 'PUT', 'recuento.update', 'Editar Sobre', $this->datosSobre($sobre));

        $this->despuesDeCambiar($request, $culto, 'Editar sobre', $this->datosSobre($sobre));

        return $this->alCulto($culto->id, 'success', "Sobre #{$sobre->numero_sobre} actualizado.");
    }

    public function destroy(Request $request, Sobre $sobre)
    {
        $culto = $this->cultoDe($sobre);
        Gate::authorize('puedeEditarCulto', $culto);

        $datos = $this->datosSobre($sobre);
        $sobre->delete();

        $this->auditar($request, 'DELETE', 'recuento.destroy', 'Eliminar Sobre', $datos);
        $this->despuesDeCambiar($request, $culto, 'Eliminar sobre', $datos);

        return $this->alCulto($culto->id, 'success', 'Sobre eliminado correctamente.');
    }

    /**
     * Corregir un sobre que quedo Anonimo por error: se le asigna la persona sin tocar montos.
     * Misma regla que editar el sobre (culto cerrado: admin o acceso de edicion) y queda auditado.
     */
    public function asignarPersona(Request $request, Sobre $sobre): RedirectResponse
    {
        $culto = $this->cultoDe($sobre);
        Gate::authorize('puedeEditarCulto', $culto);

        if ($sobre->persona_id) {
            return $this->alCulto($culto->id, 'warning', "El sobre #{$sobre->numero_sobre} ya tiene persona; para cambiarla usa Editar.");
        }

        $request->validate(['persona_nombre' => 'required|string|max:255'], [
            'persona_nombre.required' => 'Escribe el nombre (o el PIN) de la persona del sobre.',
        ]);
        try {
            $personaId = $this->personaPorNombre(trim((string) $request->input('persona_nombre')), 'no se cambió nada');
        } catch (ValidationException $e) {
            return redirect()->route('recuento.culto', ['culto' => $culto->id, 'anonimos' => 1])
                ->with('error', "Sobre #{$sobre->numero_sobre}: ".$e->validator->errors()->first('persona_id'));
        }

        $sobre->update(['persona_id' => $personaId]);
        $sobre->load('persona');

        $detalles = $this->datosSobre($sobre) + ['persona_id' => $personaId, 'persona' => $sobre->persona?->nombre, 'antes' => 'Anónimo'];
        AuditLog::registrar('Asignar persona a sobre anónimo', $detalles, 'recuento.asignar-persona');
        $this->despuesDeCambiar($request, $culto, 'Asignar persona a sobre anónimo', $detalles);

        return redirect()->route('recuento.culto', ['culto' => $culto->id, 'anonimos' => $request->boolean('anonimos') ? 1 : null])
            ->with('success', "Sobre #{$sobre->numero_sobre} asignado a {$sobre->persona?->nombre}.");
    }

    public function storeSuelto(Request $request)
    {
        $culto = $this->cultoPedido($request->input('culto_id'));
        Gate::authorize('puedeEditarCulto', $culto);

        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'moneda' => 'nullable|in:CRC,USD',
            'descripcion' => 'nullable|string|max:500',
        ]);

        [$moneda, $tipoCambioVenta] = $this->monedaYTipoCambio($validated['moneda'] ?? null);

        $suelto = OfrendaSuelta::create([
            'culto_id' => $culto->id,
            'monto' => $validated['monto'],
            'moneda' => $moneda,
            'tipo_cambio_venta' => $tipoCambioVenta,
            'metodo_pago' => 'efectivo',
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        $this->despuesDeCambiar($request, $culto, 'Agregar dinero suelto', ['suelto_id' => $suelto->id, 'monto' => $suelto->monto]);

        return $this->alCulto($culto->id, 'success', 'Dinero suelto registrado correctamente.');
    }

    public function editSuelto(OfrendaSuelta $suelto)
    {
        Gate::authorize('puedeEditarCulto', $this->cultoDe($suelto));

        return response()->json($suelto);
    }

    public function updateSuelto(Request $request, OfrendaSuelta $suelto)
    {
        $culto = $this->cultoDe($suelto);
        Gate::authorize('puedeEditarCulto', $culto);

        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'nullable|string|max:500',
        ]);

        $suelto->update([
            'monto' => $validated['monto'],
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        $this->despuesDeCambiar($request, $culto, 'Editar dinero suelto', ['suelto_id' => $suelto->id, 'monto' => $suelto->monto]);

        return $this->alCulto($culto->id, 'success', 'Dinero suelto actualizado correctamente.');
    }

    public function destroySuelto(Request $request, OfrendaSuelta $suelto)
    {
        $culto = $this->cultoDe($suelto);
        Gate::authorize('puedeEditarCulto', $culto);

        $suelto->delete();
        $this->despuesDeCambiar($request, $culto, 'Eliminar dinero suelto', ['suelto_id' => $suelto->id, 'monto' => $suelto->monto]);

        return $this->alCulto($culto->id, 'success', 'Dinero suelto eliminado correctamente.');
    }

    // Egresos (restas)
    public function storeEgreso(Request $request)
    {
        $culto = $this->cultoPedido($request->input('culto_id'));
        Gate::authorize('puedeEditarCulto', $culto);

        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'moneda' => 'nullable|in:CRC,USD',
            'descripcion' => 'nullable|string|max:500',
        ]);

        [$moneda, $tipoCambioVenta] = $this->monedaYTipoCambio($validated['moneda'] ?? null);

        $egreso = Egreso::create([
            'culto_id' => $culto->id,
            'monto' => $validated['monto'],
            'moneda' => $moneda,
            'tipo_cambio_venta' => $tipoCambioVenta,
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        $this->despuesDeCambiar($request, $culto, 'Agregar egreso', ['egreso_id' => $egreso->id, 'monto' => $egreso->monto]);

        return $this->alCulto($culto->id, 'success', 'Egreso registrado correctamente.');
    }

    public function editEgreso(Egreso $egreso)
    {
        Gate::authorize('puedeEditarCulto', $this->cultoDe($egreso));

        return response()->json($egreso);
    }

    public function updateEgreso(Request $request, Egreso $egreso)
    {
        $culto = $this->cultoDe($egreso);
        Gate::authorize('puedeEditarCulto', $culto);

        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'nullable|string|max:500',
        ]);

        $egreso->update([
            'monto' => $validated['monto'],
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        $this->despuesDeCambiar($request, $culto, 'Editar egreso', ['egreso_id' => $egreso->id, 'monto' => $egreso->monto]);

        return $this->alCulto($culto->id, 'success', 'Egreso actualizado correctamente.');
    }

    public function destroyEgreso(Request $request, Egreso $egreso)
    {
        $culto = $this->cultoDe($egreso);
        Gate::authorize('puedeEditarCulto', $culto);

        $egreso->delete();
        $this->despuesDeCambiar($request, $culto, 'Eliminar egreso', ['egreso_id' => $egreso->id, 'monto' => $egreso->monto]);

        return $this->alCulto($culto->id, 'success', 'Egreso eliminado correctamente.');
    }

    public function cerrarCulto(Culto $culto)
    {
        if ($culto->cerrado) {
            return $this->alCulto($culto->id, 'error', 'Este culto ya está cerrado.');
        }
        Gate::authorize('puedeEditarCulto', $culto);

        // Congelar tipo de cambio al cerrar
        $tipoCambioVenta = TipoCambio::hoy()?->venta;

        $culto->update([
            'cerrado' => true,
            'cerrado_at' => now(),
            'cerrado_por' => auth()->id(),
            'tipo_cambio_venta' => $tipoCambioVenta,
        ]);

        // Recalcular totales finales con tipo de cambio congelado
        $this->calculoService->recalcular($culto);

        return $this->alCulto($culto->id, 'success', 'Culto cerrado correctamente. Tipo de cambio congelado: ₡'.($tipoCambioVenta ? number_format((float) $tipoCambioVenta, 2) : 'N/A'));
    }

    /** Firmas del recuento: pastor y tesoreros (nombre y firma dibujada). */
    public function actualizarFirmas(Request $request, Culto $culto): RedirectResponse
    {
        Gate::authorize('puedeEditarCulto', $culto);

        $data = $request->validate([
            'firma_pastor' => 'nullable|string|max:255',
            'firma_pastor_imagen' => 'nullable|string',
            'firmas_tesoreros' => 'nullable|array',
            'firmas_tesoreros.*.nombre' => 'nullable|string|max:255',
            'firmas_tesoreros.*.imagen' => 'nullable|string',
        ]);

        // Tesoreros: se descartan las filas sin nombre ni firma
        $tesoreros = [];
        $tesorerosImagenes = [];
        foreach ($data['firmas_tesoreros'] ?? [] as $t) {
            $nombre = $t['nombre'] ?? '';
            $imagen = $t['imagen'] ?? '';
            if ($nombre !== '' || $imagen !== '') {
                $tesoreros[] = $nombre;
                $tesorerosImagenes[] = ['nombre' => $nombre, 'imagen' => $imagen];
            }
        }

        $culto->update([
            'firma_pastor' => $data['firma_pastor'] ?? null,
            'firma_pastor_imagen' => $data['firma_pastor_imagen'] ?? null,
            'firmas_tesoreros' => $tesoreros,
            'firmas_tesoreros_imagenes' => $tesorerosImagenes,
        ]);

        $this->permisosEdicion->registrarCambioEnCerrado($request, $culto, 'Actualizar firmas');

        return $this->alCulto($culto->id, 'success', 'Firmas de recuento actualizadas.');
    }

    /**
     * Culto al que se quiere agregar algo (viene en el formulario o en la URL). Sin culto se vuelve
     * a la lista; un culto de otra iglesia no existe para este usuario (404).
     */
    private function cultoPedido(mixed $cultoId): Culto
    {
        if (! is_numeric($cultoId)) {
            abort(redirect()->route('recuento.index')->with('warning', 'Primero elige el culto en la lista.'));
        }

        return Culto::findOrFail((int) $cultoId);
    }

    /** Culto de un sobre, dinero suelto o egreso. Si es de otra iglesia, para este usuario no existe. */
    private function cultoDe(Sobre|OfrendaSuelta|Egreso $registro): Culto
    {
        return $registro->culto ?? abort(404);
    }

    /** @return array{0: string, 1: mixed, 2: int|null} moneda, tipo de cambio de venta e id del tipo de cambio */
    private function monedaYTipoCambio(?string $moneda): array
    {
        if ($moneda !== 'USD') {
            return ['CRC', null, null];
        }

        $tipoCambio = TipoCambio::hoy();

        return ['USD', $tipoCambio?->venta, $tipoCambio?->id];
    }

    /**
     * Despues de cualquier cambio en el recuento: recalcula los totales del culto y, si el culto esta
     * cerrado, deja el cambio en la auditoria (hecho con un acceso de edicion o como correccion del admin).
     */
    private function despuesDeCambiar(Request $request, Culto $culto, string $accion, array $detalles = []): void
    {
        $this->calculoService->recalcular($culto);
        $this->permisosEdicion->registrarCambioEnCerrado($request, $culto, $accion, $detalles);
    }

    /**
     * Lo que necesita el formulario de sobre (registrar y editar comparten el mismo). Al editar, la
     * persona del sobre se incluye aunque este inactiva: si no, el formulario la mostraba como Anonimo
     * y al guardar el sobre perdia a su persona (QA-002).
     */
    private function datosFormularioSobre(Culto $culto, ?Sobre $sobre = null): array
    {
        $personas = Persona::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'pin'])
            ->map(fn (Persona $p) => ['id' => $p->id, 'nombre' => $p->nombre, 'pin' => $p->pin, 'inactiva' => false]);

        if ($sobre?->persona && ! $sobre->persona->activo) {
            $personas->prepend(['id' => $sobre->persona->id, 'nombre' => $sobre->persona->nombre, 'pin' => $sobre->persona->pin, 'inactiva' => true]);
        }

        return [
            'culto' => $culto,
            'categorias' => tenant_categories(['es_ofrenda_suelta' => false]),
            // Para el buscador de persona: solo lo necesario (nombre, PIN y si esta inactiva)
            'personas' => $personas->values(),
        ];
    }

    /** Reglas del sobre (registrar y editar). Los montos vacios cuentan como cero, pero el total debe ser mayor que cero. */
    private function validarSobre(Request $request): array
    {
        $validated = $request->validate([
            'persona_id' => 'nullable|integer|exists:personas,id',
            'persona_nombre' => 'nullable|string|max:255',
            'anonimo' => 'nullable|boolean',
            'metodo_pago' => 'required|in:efectivo,transferencia',
            'comprobante_numero' => 'nullable|required_if:metodo_pago,transferencia|string|max:100',
            'moneda' => 'nullable|in:CRC,USD',
            'notas' => 'nullable|string',
            'detalles' => 'required|array',
            'detalles.*.categoria' => 'required|string',
            'detalles.*.monto' => 'nullable|numeric|min:0',
        ], [
            'comprobante_numero.required_if' => 'Para una transferencia hay que anotar el número de comprobante.',
            'detalles.*.monto.numeric' => 'Los montos tienen que ser números.',
            'detalles.*.monto.min' => 'Los montos no pueden ser negativos.',
        ]);

        if ($this->totalDetalles($validated['detalles']) <= 0) {
            throw ValidationException::withMessages(['detalles' => 'Escribe al menos un monto mayor que cero.']);
        }

        $validated['persona_id'] = $this->personaDelSobre($request, $validated['persona_id'] ?? null);

        return $validated;
    }

    /**
     * A quien pertenece el sobre. Pedido del cliente (04/11): sobres que se guardaban como "Anonimo"
     * aunque el tesorero habia escrito el nombre (el buscador mostraba el nombre pero la persona nunca
     * quedaba elegida). Ahora un sobre sin persona solo se guarda si se marco "Sobre anonimo"; si se
     * escribio un nombre, tiene que coincidir con una sola persona o se pide elegirla o agregarla.
     */
    private function personaDelSobre(Request $request, mixed $personaId): ?int
    {
        if (filled($personaId)) {
            // Persona de esta iglesia (el alcance por iglesia del modelo deja fuera a las demas)
            if (! Persona::whereKey((int) $personaId)->exists()) {
                throw ValidationException::withMessages(['persona_id' => 'La persona elegida no existe en esta iglesia.']);
            }

            return (int) $personaId;
        }

        $nombre = trim((string) $request->input('persona_nombre', ''));
        if ($nombre !== '') {
            return $this->personaPorNombre($nombre);
        }

        if (! $request->boolean('anonimo')) {
            throw ValidationException::withMessages(['persona_id' => 'Elige la persona del sobre o marca «Sobre anónimo (sin persona)». El sobre no se guardó.']);
        }

        return null;
    }

    /** La unica persona de esta iglesia con ese nombre (o PIN); si no hay o hay varias, error para el formulario. */
    private function personaPorNombre(string $nombre, string $sufijo = 'el sobre no se guardó'): int
    {
        $clave = self::claveNombre($nombre);
        $coinciden = Persona::query()->get(['id', 'nombre', 'pin', 'activo'])
            ->filter(fn (Persona $p) => self::claveNombre($p->nombre) === $clave || ($p->pin && (string) $p->pin === $clave));
        $activas = $coinciden->where('activo', true);
        $candidatas = $activas->isNotEmpty() ? $activas : $coinciden;

        if ($candidatas->count() === 1) {
            return (int) $candidatas->first()->id;
        }

        throw ValidationException::withMessages(['persona_id' => $candidatas->isEmpty()
            ? "No hay ninguna persona llamada «{$nombre}». Elígela de la lista o agrégala con «+ Nueva persona»; {$sufijo}."
            : "Hay varias personas llamadas «{$nombre}». Elige la correcta en la lista (sale con su PIN); {$sufijo}.",
        ]);
    }

    /** Nombre comparable: sin tildes, sin mayusculas y con un solo espacio entre palabras. */
    private static function claveNombre(?string $nombre): string
    {
        return (string) Str::of((string) $nombre)->ascii()->lower()->squish();
    }

    /** @param  array<int, array{categoria: string, monto: mixed}>  $detalles */
    private function totalDetalles(array $detalles): float
    {
        return round(collect($detalles)->sum(fn ($d) => (float) ($d['monto'] ?? 0)), 2);
    }

    /** "Guardar y siguiente" vuelve al formulario del mismo culto; "Guardar y terminar", a la pantalla del culto. */
    private function trasGuardarSobre(Culto $culto, bool $siguiente, string $tipo, string $mensaje): RedirectResponse
    {
        return $siguiente
            ? redirect()->route('recuento.create', ['culto_id' => $culto->id])->with($tipo, $mensaje)
            : $this->alCulto($culto->id, $tipo, $mensaje);
    }

    /**
     * Respaldo contra dobles envios para pedidos sin codigo de envio: el MISMO sobre (persona o anonimo,
     * total y metodo de pago) en el mismo culto en los ultimos 10 segundos. Antes bastaba la misma persona
     * en 30 segundos y se perdian los anonimos seguidos (QA-001).
     */
    private function hayDuplicadoReciente(Culto $culto, array $validated): bool
    {
        return Sobre::where('culto_id', $culto->id)
            ->where('persona_id', $validated['persona_id'] ?? null)
            ->where('metodo_pago', $validated['metodo_pago'])
            ->where('total_declarado', $this->totalDetalles($validated['detalles']))
            ->where('created_at', '>=', now()->subSeconds(10))
            ->exists();
    }

    /** @param  array<int, array{categoria: string, monto: mixed}>  $detalles */
    private function guardarDetalles(Sobre $sobre, array $detalles): void
    {
        foreach ($detalles as $detalle) {
            if ((float) ($detalle['monto'] ?? 0) > 0) {
                SobreDetalle::create([
                    'sobre_id' => $sobre->id,
                    'categoria' => $detalle['categoria'],
                    'monto' => $detalle['monto'],
                ]);
            }
        }
    }

    private function datosSobre(Sobre $sobre): array
    {
        return [
            'culto_id' => $sobre->culto_id,
            'sobre_id' => $sobre->id,
            'numero_sobre' => $sobre->numero_sobre,
            'metodo_pago' => $sobre->metodo_pago,
            'comprobante_numero' => $sobre->comprobante_numero,
            'total_declarado' => $sobre->total_declarado,
        ];
    }

    private function auditar(Request $request, string $metodo, string $ruta, string $accion, array $detalles): void
    {
        $user = $request->user();

        AuditLog::create([
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            'method' => $metodo,
            'route' => $ruta,
            'action' => $accion,
            'details' => json_encode($detalles),
        ]);
    }
}

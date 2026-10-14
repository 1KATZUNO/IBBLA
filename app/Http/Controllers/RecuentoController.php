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
use Illuminate\Support\Facades\Gate;

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
        // Si el culto esta cerrado y lo puede corregir alguien que no es admin, es por un acceso de edicion
        $user = $request->user();
        $accesoUsado = $culto->cerrado && $permisoEditar->allowed() && ! $user->isAdmin() && ! $user->isSuperAdmin()
            ? $this->permisosEdicion->permisoQueCubre($user, $culto)
            : null;

        return view('recuento.culto', compact('culto', 'categorias', 'sobres', 'ofrendasSueltas', 'egresos', 'resumen', 'permisoEditar', 'accesoUsado'));
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

    public function create(Request $request)
    {
        $culto = $this->cultoPedido($request->query('culto_id'));
        Gate::authorize('puedeEditarCulto', $culto);

        $personas = Persona::where('activo', true)->orderBy('nombre')->get();
        $categorias = tenant_categories(['es_ofrenda_suelta' => false]);

        return view('recuento.create', compact('culto', 'personas', 'categorias'));
    }

    public function store(Request $request)
    {
        $culto = $this->cultoPedido($request->input('culto_id'));
        Gate::authorize('puedeEditarCulto', $culto);

        $validated = $request->validate([
            'persona_id' => 'nullable|exists:personas,id',
            'metodo_pago' => 'required|in:efectivo,transferencia',
            'comprobante_numero' => 'nullable|required_if:metodo_pago,transferencia|string|max:100',
            'moneda' => 'nullable|in:CRC,USD',
            'notas' => 'nullable|string',
            'detalles' => 'required|array',
            'detalles.*.categoria' => 'required|string',
            'detalles.*.monto' => 'required|numeric|min:0',
        ], [
            'comprobante_numero.required_if' => 'Para una transferencia hay que anotar el número de comprobante.',
        ]);

        // Prevenir duplicados: el mismo sobre enviado dos veces en pocos segundos
        $duplicado = Sobre::where('culto_id', $culto->id)
            ->where('persona_id', $validated['persona_id'] ?? null)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->exists();

        if ($duplicado) {
            return $this->alCulto($culto->id, 'warning', 'Ya se registró un sobre para esta persona en este culto hace unos segundos. Verifique que no sea duplicado.');
        }

        [$moneda, $tipoCambioVenta, $tipoCambioId] = $this->monedaYTipoCambio($validated['moneda'] ?? null);

        $sobre = Sobre::create([
            'culto_id' => $culto->id,
            'persona_id' => $validated['persona_id'] ?? null,
            'metodo_pago' => $validated['metodo_pago'],
            'comprobante_numero' => $validated['metodo_pago'] === 'transferencia' ? ($validated['comprobante_numero'] ?? null) : null,
            'total_declarado' => collect($validated['detalles'])->sum('monto'),
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

        return $this->alCulto($culto->id, 'success', 'Sobre registrado correctamente.');
    }

    public function edit(Sobre $sobre)
    {
        $culto = $this->cultoDe($sobre);
        Gate::authorize('puedeEditarCulto', $culto);

        $sobre->load(['detalles', 'culto']);
        $personas = Persona::where('activo', true)->orderBy('nombre')->get();
        $categorias = tenant_categories(['es_ofrenda_suelta' => false]);

        return view('recuento.edit', compact('sobre', 'personas', 'categorias'));
    }

    public function update(Request $request, Sobre $sobre)
    {
        $culto = $this->cultoDe($sobre);
        Gate::authorize('puedeEditarCulto', $culto);

        $validated = $request->validate([
            'persona_id' => 'nullable|exists:personas,id',
            'metodo_pago' => 'required|in:efectivo,transferencia',
            'comprobante_numero' => 'nullable|required_if:metodo_pago,transferencia|string|max:100',
            'moneda' => 'nullable|in:CRC,USD',
            'notas' => 'nullable|string',
            'detalles' => 'required|array',
            'detalles.*.categoria' => 'required|string',
            'detalles.*.monto' => 'required|numeric|min:0',
        ], [
            'comprobante_numero.required_if' => 'Para una transferencia hay que anotar el número de comprobante.',
        ]);

        [$moneda, $tipoCambioVenta, $tipoCambioId] = $this->monedaYTipoCambio($validated['moneda'] ?? null);

        $sobre->update([
            'persona_id' => $validated['persona_id'] ?? null,
            'metodo_pago' => $validated['metodo_pago'],
            'comprobante_numero' => $validated['metodo_pago'] === 'transferencia' ? ($validated['comprobante_numero'] ?? null) : null,
            'total_declarado' => collect($validated['detalles'])->sum('monto'),
            'moneda' => $moneda,
            'tipo_cambio_venta' => $tipoCambioVenta,
            'tipo_cambio_id' => $tipoCambioId,
            'notas' => $validated['notas'] ?? null,
        ]);

        $sobre->detalles()->delete();
        $this->guardarDetalles($sobre, $validated['detalles']);
        $this->auditar($request, 'PUT', 'recuento.update', 'Editar Sobre', $this->datosSobre($sobre));

        $this->despuesDeCambiar($request, $culto, 'Editar sobre', $this->datosSobre($sobre));

        return $this->alCulto($culto->id, 'success', 'Sobre actualizado correctamente.');
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

    /** @param  array<int, array{categoria: string, monto: mixed}>  $detalles */
    private function guardarDetalles(Sobre $sobre, array $detalles): void
    {
        foreach ($detalles as $detalle) {
            if ((float) $detalle['monto'] > 0) {
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

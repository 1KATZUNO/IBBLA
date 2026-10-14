<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Culto;
use App\Models\OfrendaSuelta;
use App\Models\Persona;
use App\Models\Sobre;
use App\Models\SobreDetalle;
use App\Models\TenantServiceType;
use App\Models\TipoCambio;
use App\Services\CalculoTotalesCultoService;
use App\Services\WhatsApp\AvisosWhatsAppService;
use App\Support\ResumenCulto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecuentoController extends Controller
{
    protected $calculoService;

    public function __construct(CalculoTotalesCultoService $calculoService)
    {
        $this->calculoService = $calculoService;
    }

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
    public function culto(Culto $culto)
    {
        $culto->load(['totales', 'serviceType', 'cerradoPor:id,name']);
        $categorias = tenant_categories(['es_ofrenda_suelta' => false]);
        $sobres = $culto->sobres()->with(['persona', 'detalles'])->orderBy('numero_sobre')->get();
        $ofrendasSueltas = $culto->ofrendasSueltas()->orderBy('created_at')->get();
        $egresos = $culto->egresos()->orderBy('created_at')->get();
        $resumen = ResumenCulto::calcular($sobres, $ofrendasSueltas, $egresos, $categorias);

        return view('recuento.culto', compact('culto', 'categorias', 'sobres', 'ofrendasSueltas', 'egresos', 'resumen'));
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
        $cultoId = $request->get('culto_id');
        $culto = $cultoId ? Culto::findOrFail($cultoId) : null;
        $personas = Persona::where('activo', true)->get();
        $categorias = tenant_categories(['es_ofrenda_suelta' => false]);

        return view('recuento.create', compact('culto', 'personas', 'categorias'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'culto_id' => 'required|exists:cultos,id',
            'persona_id' => 'nullable|exists:personas,id',
            'metodo_pago' => 'required|in:efectivo,transferencia',
            'comprobante_numero' => 'nullable|string|max:100',
            'moneda' => 'nullable|in:CRC,USD',
            'notas' => 'nullable|string',
            'detalles' => 'required|array',
            'detalles.*.categoria' => 'required|string',
            'detalles.*.monto' => 'required|numeric|min:0',
        ]);

        if ($validated['metodo_pago'] === 'transferencia') {
            $request->validate([
                'comprobante_numero' => 'required|string|max:100',
            ]);
        }

        // Verificar que el culto no esté cerrado
        $culto = Culto::findOrFail($validated['culto_id']);
        if ($culto->cerrado) {
            return $this->alCulto($culto->id, 'error', 'No se pueden agregar sobres a un culto cerrado.');
        }

        // Prevenir duplicados: verificar si ya existe un sobre idéntico creado en los últimos 30 segundos
        $duplicado = Sobre::where('culto_id', $validated['culto_id'])
            ->where('persona_id', $validated['persona_id'] ?? null)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->exists();

        if ($duplicado) {
            return $this->alCulto($culto->id, 'warning', 'Ya se registró un sobre para esta persona en este culto hace unos segundos. Verifique que no sea duplicado.');
        }

        // Calcular total declarado
        $totalDeclarado = collect($validated['detalles'])->sum('monto');

        // Moneda y tipo de cambio
        $moneda = $validated['moneda'] ?? 'CRC';
        $tipoCambioVenta = null;
        $tipoCambioId = null;

        if ($moneda === 'USD') {
            $tipoCambio = TipoCambio::hoy();
            if ($tipoCambio) {
                $tipoCambioVenta = $tipoCambio->venta;
                $tipoCambioId = $tipoCambio->id;
            }
        }

        $sobre = Sobre::create([
            'culto_id' => $validated['culto_id'],
            'persona_id' => $validated['persona_id'] ?? null,
            'metodo_pago' => $validated['metodo_pago'],
            'comprobante_numero' => $validated['comprobante_numero'] ?? null,
            'total_declarado' => $totalDeclarado,
            'moneda' => $moneda,
            'tipo_cambio_venta' => $tipoCambioVenta,
            'tipo_cambio_id' => $tipoCambioId,
            'notas' => $validated['notas'] ?? null,
        ]);

        // Auditoría
        $user = $request->user();
        AuditLog::create([
            'user_id' => $user->id ?? null,
            'user_name' => $user->name ?? ($user->nombre ?? null),
            'user_email' => $user->email ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            'method' => 'POST',
            'route' => 'recuento.store',
            'action' => 'Agregar Sobre',
            'details' => json_encode([
                'culto_id' => $sobre->culto_id,
                'sobre_id' => $sobre->id,
                'metodo_pago' => $sobre->metodo_pago,
                'comprobante_numero' => $sobre->comprobante_numero,
                'total_declarado' => $sobre->total_declarado,
            ]),
        ]);

        // Crear detalles
        foreach ($validated['detalles'] as $detalle) {
            if ($detalle['monto'] > 0) {
                SobreDetalle::create([
                    'sobre_id' => $sobre->id,
                    'categoria' => $detalle['categoria'],
                    'monto' => $detalle['monto'],
                ]);
            }
        }

        // Recalcular totales del culto
        $this->calculoService->recalcular($sobre->culto);

        // Aviso por WhatsApp cuando el culto llega a N sobres (una sola vez por culto; nunca interrumpe el registro).
        app(AvisosWhatsAppService::class)->sobreRegistrado($sobre->culto);

        return $this->alCulto($validated['culto_id'], 'success', 'Sobre registrado correctamente.');
    }

    public function edit(Sobre $sobre)
    {
        $user = auth()->user();
        $isAdmin = $user && method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user && $user->rol === 'admin');
        if ($sobre->culto->cerrado && ! $isAdmin) {
            return $this->alCulto($sobre->culto_id, 'error', 'No se puede editar un sobre de un culto cerrado.');
        }

        $sobre->load(['detalles', 'culto']);
        $personas = Persona::where('activo', true)->get();
        $categorias = tenant_categories(['es_ofrenda_suelta' => false]);

        return view('recuento.edit', compact('sobre', 'personas', 'categorias'));
    }

    public function update(Request $request, Sobre $sobre)
    {
        $user = auth()->user();
        $isAdmin = $user && method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user && $user->rol === 'admin');
        if ($sobre->culto->cerrado && ! $isAdmin) {
            return $this->alCulto($sobre->culto_id, 'error', 'No se puede editar un sobre de un culto cerrado.');
        }

        $validated = $request->validate([
            'persona_id' => 'nullable|exists:personas,id',
            'metodo_pago' => 'required|in:efectivo,transferencia',
            'comprobante_numero' => 'nullable|string|max:100',
            'moneda' => 'nullable|in:CRC,USD',
            'notas' => 'nullable|string',
            'detalles' => 'required|array',
            'detalles.*.categoria' => 'required|string',
            'detalles.*.monto' => 'required|numeric|min:0',
        ]);

        if ($validated['metodo_pago'] === 'transferencia') {
            $request->validate([
                'comprobante_numero' => 'required|string|max:100',
            ]);
        }

        $totalDeclarado = collect($validated['detalles'])->sum('monto');

        // Moneda y tipo de cambio
        $moneda = $validated['moneda'] ?? 'CRC';
        $tipoCambioVenta = null;
        $tipoCambioId = null;

        if ($moneda === 'USD') {
            $tipoCambio = TipoCambio::hoy();
            if ($tipoCambio) {
                $tipoCambioVenta = $tipoCambio->venta;
                $tipoCambioId = $tipoCambio->id;
            }
        }

        $sobre->update([
            'persona_id' => $validated['persona_id'] ?? null,
            'metodo_pago' => $validated['metodo_pago'],
            'comprobante_numero' => $validated['comprobante_numero'] ?? null,
            'total_declarado' => $totalDeclarado,
            'moneda' => $moneda,
            'tipo_cambio_venta' => $tipoCambioVenta,
            'tipo_cambio_id' => $tipoCambioId,
            'notas' => $validated['notas'] ?? null,
        ]);

        // Auditoría
        $user = $request->user();
        AuditLog::create([
            'user_id' => $user->id ?? null,
            'user_name' => $user->name ?? ($user->nombre ?? null),
            'user_email' => $user->email ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            'method' => 'PUT',
            'route' => 'recuento.update',
            'action' => 'Editar Sobre',
            'details' => json_encode([
                'culto_id' => $sobre->culto_id,
                'sobre_id' => $sobre->id,
                'metodo_pago' => $sobre->metodo_pago,
                'comprobante_numero' => $sobre->comprobante_numero,
                'total_declarado' => $sobre->total_declarado,
            ]),
        ]);

        // Eliminar detalles existentes y crear nuevos
        $sobre->detalles()->delete();
        foreach ($validated['detalles'] as $detalle) {
            if ($detalle['monto'] > 0) {
                SobreDetalle::create([
                    'sobre_id' => $sobre->id,
                    'categoria' => $detalle['categoria'],
                    'monto' => $detalle['monto'],
                ]);
            }
        }

        // Recalcular totales
        $this->calculoService->recalcular($sobre->culto);

        return $this->alCulto($sobre->culto_id, 'success', 'Sobre actualizado correctamente.');
    }

    public function destroy(Sobre $sobre)
    {
        // Solo admin y tesorero pueden eliminar sobres (con null-guard)
        $currentUser = auth()->user();
        if (! $currentUser || ! in_array($currentUser->rol, ['admin', 'tesorero'])) {
            return $this->alCulto($sobre->culto_id, 'error', 'No tienes permiso para eliminar sobres.');
        }

        if ($sobre->culto->cerrado) {
            return $this->alCulto($sobre->culto_id, 'error', 'No se puede eliminar un sobre de un culto cerrado.');
        }

        $cultoId = $sobre->culto_id;
        $sobreId = $sobre->id;
        $metodo = $sobre->metodo_pago;
        $comprobante = $sobre->comprobante_numero;
        $totalDeclarado = $sobre->total_declarado;
        $sobre->delete();

        // Recalcular totales
        $culto = Culto::find($cultoId);
        if ($culto) {
            $this->calculoService->recalcular($culto);
        }

        // Auditoría
        $user = $currentUser;
        AuditLog::create([
            'user_id' => $user->id ?? null,
            'user_name' => $user->name ?? ($user->nombre ?? null),
            'user_email' => $user->email ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 255),
            'method' => 'DELETE',
            'route' => 'recuento.destroy',
            'action' => 'Eliminar Sobre',
            'details' => json_encode([
                'culto_id' => $cultoId,
                'sobre_id' => $sobreId,
                'metodo_pago' => $metodo,
                'comprobante_numero' => $comprobante,
                'total_declarado' => $totalDeclarado,
            ]),
        ]);

        return $this->alCulto($cultoId, 'success', 'Sobre eliminado correctamente.');
    }

    public function storeSuelto(Request $request)
    {
        $validated = $request->validate([
            'culto_id' => 'required|exists:cultos,id',
            'monto' => 'required|numeric|min:0.01',
            'moneda' => 'nullable|in:CRC,USD',
            'descripcion' => 'nullable|string|max:500',
        ]);

        // Verificar que el culto no esté cerrado
        $culto = Culto::findOrFail($validated['culto_id']);
        if ($culto->cerrado) {
            return $this->alCulto($culto->id, 'error', 'No se puede agregar dinero suelto a un culto cerrado.');
        }

        $moneda = $validated['moneda'] ?? 'CRC';
        $tipoCambioVenta = null;
        if ($moneda === 'USD') {
            $tc = TipoCambio::hoy();
            $tipoCambioVenta = $tc?->venta;
        }

        OfrendaSuelta::create([
            'culto_id' => $validated['culto_id'],
            'monto' => $validated['monto'],
            'moneda' => $moneda,
            'tipo_cambio_venta' => $tipoCambioVenta,
            'metodo_pago' => 'efectivo',
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        // Recalcular totales
        $culto = Culto::find($validated['culto_id']);
        if ($culto) {
            $this->calculoService->recalcular($culto);
        }

        return $this->alCulto($validated['culto_id'], 'success', 'Dinero suelto registrado correctamente.');
    }

    // Egresos
    public function storeEgreso(Request $request)
    {
        $validated = $request->validate([
            'culto_id' => 'required|exists:cultos,id',
            'monto' => 'required|numeric|min:0.01',
            'moneda' => 'nullable|in:CRC,USD',
            'descripcion' => 'nullable|string|max:500',
        ]);

        // Verificar que el culto no esté cerrado
        $culto = Culto::findOrFail($validated['culto_id']);
        if ($culto->cerrado) {
            return $this->alCulto($culto->id, 'error', 'No se puede agregar egresos a un culto cerrado.');
        }

        $moneda = $validated['moneda'] ?? 'CRC';
        $tipoCambioVenta = null;
        if ($moneda === 'USD') {
            $tc = TipoCambio::hoy();
            $tipoCambioVenta = $tc?->venta;
        }

        \App\Models\Egreso::create([
            'culto_id' => $validated['culto_id'],
            'monto' => $validated['monto'],
            'moneda' => $moneda,
            'tipo_cambio_venta' => $tipoCambioVenta,
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        // Recalcular totales
        $culto = Culto::find($validated['culto_id']);
        if ($culto) {
            $this->calculoService->recalcular($culto);
        }

        return $this->alCulto($validated['culto_id'], 'success', 'Egreso registrado correctamente.');
    }

    public function editEgreso(\App\Models\Egreso $egreso)
    {
        if ($egreso->culto->cerrado) {
            return $this->alCulto($egreso->culto_id, 'error', 'No se puede editar egresos de un culto cerrado.');
        }

        return response()->json($egreso);
    }

    public function updateEgreso(Request $request, \App\Models\Egreso $egreso)
    {
        if ($egreso->culto->cerrado) {
            return $this->alCulto($egreso->culto_id, 'error', 'No se puede editar egresos de un culto cerrado.');
        }

        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'nullable|string|max:500',
        ]);

        $egreso->update([
            'monto' => $validated['monto'],
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        // Recalcular totales
        $culto = Culto::find($egreso->culto_id);
        if ($culto) {
            $this->calculoService->recalcular($culto);
        }

        return $this->alCulto($egreso->culto_id, 'success', 'Egreso actualizado correctamente.');
    }

    public function destroyEgreso(\App\Models\Egreso $egreso)
    {
        // Solo admin y tesorero pueden eliminar egresos
        if (! in_array(auth()->user()->rol, ['admin', 'tesorero'])) {
            return $this->alCulto($egreso->culto_id, 'error', 'No tienes permiso para eliminar egresos.');
        }

        if ($egreso->culto->cerrado) {
            return $this->alCulto($egreso->culto_id, 'error', 'No se puede eliminar egresos de un culto cerrado.');
        }

        $cultoId = $egreso->culto_id;
        $egreso->delete();

        // Recalcular totales
        $culto = Culto::find($cultoId);
        if ($culto) {
            $this->calculoService->recalcular($culto);
        }

        return $this->alCulto($cultoId, 'success', 'Egreso eliminado correctamente.');
    }

    public function editSuelto(OfrendaSuelta $suelto)
    {
        if ($suelto->culto->cerrado) {
            return $this->alCulto($suelto->culto_id, 'error', 'No se puede editar dinero suelto de un culto cerrado.');
        }

        return response()->json($suelto);
    }

    public function updateSuelto(Request $request, OfrendaSuelta $suelto)
    {
        if ($suelto->culto->cerrado) {
            return $this->alCulto($suelto->culto_id, 'error', 'No se puede editar dinero suelto de un culto cerrado.');
        }

        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'nullable|string|max:500',
        ]);

        $suelto->update([
            'monto' => $validated['monto'],
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        // Recalcular totales
        $culto = Culto::find($suelto->culto_id);
        if ($culto) {
            $this->calculoService->recalcular($culto);
        }

        return $this->alCulto($suelto->culto_id, 'success', 'Dinero suelto actualizado correctamente.');
    }

    public function destroySuelto(OfrendaSuelta $suelto)
    {
        // Solo admin y tesorero pueden eliminar dinero suelto
        if (! in_array(auth()->user()->rol, ['admin', 'tesorero'])) {
            return $this->alCulto($suelto->culto_id, 'error', 'No tienes permiso para eliminar dinero suelto.');
        }

        if ($suelto->culto->cerrado) {
            return $this->alCulto($suelto->culto_id, 'error', 'No se puede eliminar dinero suelto de un culto cerrado.');
        }

        $cultoId = $suelto->culto_id;
        $suelto->delete();

        // Recalcular totales
        $culto = Culto::find($cultoId);
        if ($culto) {
            $this->calculoService->recalcular($culto);
        }

        return $this->alCulto($cultoId, 'success', 'Dinero suelto eliminado correctamente.');
    }

    public function cerrarCulto(Culto $culto)
    {
        if ($culto->cerrado) {
            return $this->alCulto($culto->id, 'error', 'Este culto ya está cerrado.');
        }

        // Congelar tipo de cambio al cerrar
        $tipoCambio = TipoCambio::hoy();
        $tipoCambioVenta = $tipoCambio?->venta;

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

        return $this->alCulto($culto->id, 'success', 'Firmas de recuento actualizadas.');
    }
}

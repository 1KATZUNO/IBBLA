<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Persona;
use App\Models\Culto;
use App\Models\SobreDetalle;
use App\Models\OfrendaSuelta;
use App\Models\Egreso;
use App\Models\TenantCategory;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('activo', true)->count();
        $totalUsers = User::where('is_super_admin', false)->count();
        $totalPersonas = Persona::count();

        $tenants = Tenant::withCount(['users', 'categories', 'emailDomains'])
            ->orderByDesc('created_at')
            ->get();

        return view('super-admin.dashboard', compact(
            'totalTenants', 'activeTenants', 'totalUsers', 'totalPersonas', 'tenants'
        ));
    }

    public function ingresosGlobales(Request $request)
    {
        $mes = $request->get('mes', date('m'));
        $año = $request->get('año', date('Y'));
        $tenantId = $request->get('tenant_id');

        $tenants = Tenant::where('activo', true)->orderBy('nombre')->get();
        $añosDisponibles = range(date('Y') - 2, date('Y'));

        $inicio = Carbon::create($año, $mes, 1)->startOfMonth();
        $fin = Carbon::create($año, $mes, 1)->endOfMonth();

        $resumen = ['total_general' => 0, 'total_suelto' => 0, 'total_egresos' => 0, 'cantidad_cultos' => 0];
        $datosPorIglesia = [];
        $datosPorCategoria = [];
        $tendencia = [];
        $tenantSeleccionado = null;

        if ($tenantId) {
            // Modo: una iglesia especifica - calcular desde datos fuente
            $tenantSeleccionado = Tenant::find($tenantId);
            if ($tenantSeleccionado) {
                $cultoIds = Culto::where('tenant_id', $tenantId)
                    ->whereBetween('fecha', [$inicio, $fin])
                    ->pluck('id');

                $resumen['cantidad_cultos'] = $cultoIds->count();

                // Categorias de este tenant
                $categorias = TenantCategory::where('tenant_id', $tenantId)
                    ->where('activa', true)
                    ->orderBy('orden')
                    ->get();

                foreach ($categorias as $cat) {
                    $datosPorCategoria[$cat->slug] = [
                        'nombre' => $cat->nombre,
                        'color' => $cat->color,
                        'total' => 0,
                    ];
                }

                // Sumar por categoria desde SobreDetalle
                $detalles = SobreDetalle::whereHas('sobre', fn($q) => $q->whereIn('culto_id', $cultoIds))
                    ->selectRaw('categoria, SUM(monto) as total')
                    ->groupBy('categoria')
                    ->pluck('total', 'categoria');

                $sumaIngresos = 0;
                foreach ($detalles as $catSlug => $total) {
                    $sumaIngresos += (float) $total;
                    if (isset($datosPorCategoria[$catSlug])) {
                        $datosPorCategoria[$catSlug]['total'] = (float) $total;
                    }
                }

                // Suelto
                $totalSuelto = (float) OfrendaSuelta::whereIn('culto_id', $cultoIds)->sum('monto');
                $resumen['total_suelto'] = $totalSuelto;

                // Egresos
                $totalEgresos = (float) Egreso::whereIn('culto_id', $cultoIds)->sum('monto');
                $resumen['total_egresos'] = $totalEgresos;

                // Total general = suma categorias + suelto - egresos
                $resumen['total_general'] = $sumaIngresos + $totalSuelto - $totalEgresos;

                // Filtrar categorias con total 0
                $datosPorCategoria = array_filter($datosPorCategoria, fn($d) => $d['total'] > 0);
            }
        } else {
            // Modo: todas las iglesias - calcular desde datos fuente
            foreach ($tenants as $tenant) {
                $cultoIds = Culto::where('tenant_id', $tenant->id)
                    ->whereBetween('fecha', [$inicio, $fin])
                    ->pluck('id');

                $cantidadCultos = $cultoIds->count();

                // Suma de sobres por categoria
                $sumaIngresos = (float) SobreDetalle::whereHas('sobre', fn($q) => $q->whereIn('culto_id', $cultoIds))
                    ->sum('monto');

                $totalSuelto = (float) OfrendaSuelta::whereIn('culto_id', $cultoIds)->sum('monto');
                $totalEgresos = (float) Egreso::whereIn('culto_id', $cultoIds)->sum('monto');
                $totalGeneral = $sumaIngresos + $totalSuelto - $totalEgresos;

                $datosPorIglesia[] = [
                    'tenant' => $tenant,
                    'total_general' => $totalGeneral,
                    'total_suelto' => $totalSuelto,
                    'total_egresos' => $totalEgresos,
                    'cantidad_cultos' => $cantidadCultos,
                ];

                $resumen['total_general'] += $totalGeneral;
                $resumen['total_suelto'] += $totalSuelto;
                $resumen['total_egresos'] += $totalEgresos;
                $resumen['cantidad_cultos'] += $cantidadCultos;
            }
        }

        // Tendencia mensual del año - desde datos fuente
        for ($m = 1; $m <= 12; $m++) {
            $mInicio = Carbon::create($año, $m, 1)->startOfMonth();
            $mFin = Carbon::create($año, $m, 1)->endOfMonth();

            $query = Culto::whereBetween('fecha', [$mInicio, $mFin]);
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            $mesCultoIds = $query->pluck('id');

            $mesIngresos = (float) SobreDetalle::whereHas('sobre', fn($q) => $q->whereIn('culto_id', $mesCultoIds))
                ->sum('monto');
            $mesSuelto = (float) OfrendaSuelta::whereIn('culto_id', $mesCultoIds)->sum('monto');
            $mesEgresos = (float) Egreso::whereIn('culto_id', $mesCultoIds)->sum('monto');

            $tendencia[] = [
                'mes' => Carbon::create($año, $m, 1)->locale('es')->translatedFormat('M'),
                'total' => $mesIngresos + $mesSuelto - $mesEgresos,
            ];
        }

        return view('super-admin.ingresos-globales', compact(
            'tenants', 'añosDisponibles', 'mes', 'año', 'tenantId',
            'resumen', 'datosPorIglesia', 'datosPorCategoria', 'tendencia', 'tenantSeleccionado'
        ));
    }

    public function promesasGlobales(Request $request)
    {
        $mes = $request->get('mes', date('m'));
        $año = $request->get('año', date('Y'));
        $tenantId = $request->get('tenant_id');

        $tenants = Tenant::where('activo', true)->orderBy('nombre')->get();
        $añosDisponibles = range(date('Y') - 2, date('Y'));

        $resumen = ['prometido' => 0, 'dado' => 0, 'faltante' => 0, 'profit' => 0];
        $datosPorIglesia = [];
        $datosPorCategoria = [];
        $tenantSeleccionado = null;

        if ($tenantId) {
            // Modo: una iglesia especifica
            $tenantSeleccionado = Tenant::find($tenantId);
            if ($tenantSeleccionado) {
                if ($mes === 'todos') {
                    $totales = $this->calcularTotalesAnualesTenant($tenantSeleccionado, $año);
                } else {
                    $totales = $this->calcularTotalesTenant($tenantSeleccionado, $año, $mes);
                }
                $datosPorCategoria = $totales['categorias'];
                $resumen = $totales['grand_total'];
            }
        } else {
            // Modo: todas las iglesias
            foreach ($tenants as $tenant) {
                if ($mes === 'todos') {
                    $totales = $this->calcularTotalesAnualesTenant($tenant, $año);
                } else {
                    $totales = $this->calcularTotalesTenant($tenant, $año, $mes);
                }

                $gt = $totales['grand_total'];
                $porcentaje = $gt['prometido'] > 0 ? round(($gt['dado'] / $gt['prometido']) * 100, 1) : ($gt['dado'] > 0 ? 100 : 0);

                $datosPorIglesia[] = [
                    'tenant' => $tenant,
                    'prometido' => $gt['prometido'],
                    'dado' => $gt['dado'],
                    'faltante' => $gt['faltante'],
                    'profit' => $gt['profit'],
                    'porcentaje' => $porcentaje,
                ];

                $resumen['prometido'] += $gt['prometido'];
                $resumen['dado'] += $gt['dado'];
                $resumen['faltante'] += $gt['faltante'];
                $resumen['profit'] += $gt['profit'];
            }
        }

        return view('super-admin.promesas-globales', compact(
            'tenants', 'añosDisponibles', 'mes', 'año', 'tenantId',
            'resumen', 'datosPorIglesia', 'datosPorCategoria', 'tenantSeleccionado'
        ));
    }

    /**
     * Calcula totales de promesas para un tenant en un mes especifico.
     */
    private function calcularTotalesTenant(Tenant $tenant, $año, $mes): array
    {
        $personas = Persona::where('tenant_id', $tenant->id)
            ->where('activo', true)
            ->with('promesas')
            ->get();

        $categoriasExcluidas = TenantCategory::where('tenant_id', $tenant->id)
            ->where('activa', true)
            ->where('excluir_de_promesas', true)
            ->pluck('slug')
            ->map(fn($s) => strtolower($s))
            ->toArray();

        $categoriasPromesa = TenantCategory::where('tenant_id', $tenant->id)
            ->where('activa', true)
            ->where('excluir_de_promesas', false)
            ->pluck('slug')
            ->toArray();

        $totalesPorCategoria = [];
        $grandTotal = ['prometido' => 0, 'dado' => 0, 'faltante' => 0, 'profit' => 0];

        // Paso 1: Montos prometidos
        foreach ($personas as $persona) {
            foreach ($persona->promesas as $promesa) {
                $catLower = strtolower($promesa->categoria);
                if (in_array($catLower, $categoriasExcluidas)) {
                    continue;
                }

                $cat = $promesa->categoria;
                if (!isset($totalesPorCategoria[$cat])) {
                    $totalesPorCategoria[$cat] = [
                        'categoria' => ucfirst($cat),
                        'total_prometido' => 0,
                        'total_dado' => 0,
                        'faltante' => 0,
                        'profit' => 0,
                    ];
                }

                $totalesPorCategoria[$cat]['total_prometido'] += $this->calcularMontoPrometidoMes($promesa, $año, $mes);
            }
        }

        // Paso 2: Montos dados (solo cultos de este tenant)
        $cultoIds = Culto::where('tenant_id', $tenant->id)
            ->whereBetween('fecha', [
                Carbon::create($año, $mes, 1)->startOfMonth(),
                Carbon::create($año, $mes, 1)->endOfMonth(),
            ])
            ->pluck('id');

        foreach ($categoriasPromesa as $cat) {
            $montoDado = SobreDetalle::whereHas('sobre', function ($query) use ($cultoIds) {
                $query->whereIn('culto_id', $cultoIds);
            })
                ->where('categoria', $cat)
                ->sum('monto');

            if ($montoDado > 0 && !isset($totalesPorCategoria[$cat])) {
                $totalesPorCategoria[$cat] = [
                    'categoria' => ucfirst($cat),
                    'total_prometido' => 0,
                    'total_dado' => 0,
                    'faltante' => 0,
                    'profit' => 0,
                ];
            }

            if (isset($totalesPorCategoria[$cat])) {
                $totalesPorCategoria[$cat]['total_dado'] = (float) $montoDado;
            }
        }

        // Paso 3: Calcular faltante/profit
        foreach ($totalesPorCategoria as $cat => $datos) {
            if (in_array(strtolower($cat), $categoriasExcluidas)) {
                unset($totalesPorCategoria[$cat]);
                continue;
            }

            $saldo = $datos['total_dado'] - $datos['total_prometido'];
            if ($saldo < 0) {
                $totalesPorCategoria[$cat]['faltante'] = abs($saldo);
                $totalesPorCategoria[$cat]['profit'] = 0;
            } else {
                $totalesPorCategoria[$cat]['profit'] = $saldo;
                $totalesPorCategoria[$cat]['faltante'] = 0;
            }

            $grandTotal['prometido'] += $datos['total_prometido'];
            $grandTotal['dado'] += $datos['total_dado'];
            $grandTotal['faltante'] += $totalesPorCategoria[$cat]['faltante'];
            $grandTotal['profit'] += $totalesPorCategoria[$cat]['profit'];
        }

        return [
            'categorias' => array_values($totalesPorCategoria),
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Calcula totales anuales de promesas para un tenant.
     */
    private function calcularTotalesAnualesTenant(Tenant $tenant, $año): array
    {
        $totalesPorCategoria = [];
        $grandTotal = ['prometido' => 0, 'dado' => 0, 'faltante' => 0, 'profit' => 0];

        for ($mes = 1; $mes <= 12; $mes++) {
            $totalesMes = $this->calcularTotalesTenant($tenant, $año, $mes);

            foreach ($totalesMes['categorias'] as $catData) {
                $key = strtolower(str_replace(' ', '_', $catData['categoria']));
                if (!isset($totalesPorCategoria[$key])) {
                    $totalesPorCategoria[$key] = [
                        'categoria' => $catData['categoria'],
                        'total_prometido' => 0,
                        'total_dado' => 0,
                        'faltante' => 0,
                        'profit' => 0,
                    ];
                }
                $totalesPorCategoria[$key]['total_prometido'] += $catData['total_prometido'];
                $totalesPorCategoria[$key]['total_dado'] += $catData['total_dado'];
                $totalesPorCategoria[$key]['faltante'] += $catData['faltante'];
                $totalesPorCategoria[$key]['profit'] += $catData['profit'];
            }

            $grandTotal['prometido'] += $totalesMes['grand_total']['prometido'];
            $grandTotal['dado'] += $totalesMes['grand_total']['dado'];
            $grandTotal['faltante'] += $totalesMes['grand_total']['faltante'];
            $grandTotal['profit'] += $totalesMes['grand_total']['profit'];
        }

        return [
            'categorias' => array_values($totalesPorCategoria),
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Calcula el monto prometido en un mes especifico segun la frecuencia.
     */
    private function calcularMontoPrometidoMes($promesa, $año, $mes): float
    {
        $fechaMes = Carbon::create($año, $mes, 1);

        switch ($promesa->frecuencia) {
            case 'semanal':
                $domingos = 0;
                $fecha = $fechaMes->copy()->startOfMonth();
                $finMes = $fechaMes->copy()->endOfMonth();
                while ($fecha->lte($finMes)) {
                    if ($fecha->dayOfWeek === Carbon::SUNDAY) {
                        $domingos++;
                    }
                    $fecha->addDay();
                }
                return $promesa->monto * $domingos;

            case 'quincenal':
                return $promesa->monto * 2;

            case 'mensual':
            default:
                return (float) $promesa->monto;
        }
    }
}

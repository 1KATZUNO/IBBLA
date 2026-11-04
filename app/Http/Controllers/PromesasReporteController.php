<?php

namespace App\Http\Controllers;

use App\Models\ClaseAsistencia;
use App\Models\Persona;
use App\Models\Promesa;
use App\Models\SobreDetalle;
use App\Services\Reportes\Dinero;
use App\Services\Reportes\MotorReportes;
use App\Services\Reportes\PeriodoReporte;
use App\Support\Moneda\ConversorUsd;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromesasReporteController extends Controller
{
    public function index(Request $request)
    {
        $año = $request->get('año', date('Y'));
        $mes = $request->get('mes', date('m'));
        $categoria = $request->get('categoria', null);

        // Obtener años disponibles (solo año actual y anteriores)
        $añoActual = date('Y');
        $añosDisponibles = range($añoActual - 2, $añoActual);

        // Categorías dinámicas del tenant (para el dropdown de filtro)
        $categoriasDisponibles = tenant_categories(['excluir_de_promesas' => false]);

        // Si mes = 'todos', calcular total de todo el año
        if ($mes === 'todos') {
            $totales = $this->calcularTotalesAnuales($año, $categoria);
        } else {
            // Calcular totales para el mes específico
            $totales = $this->calcularTotales($año, $mes, $categoria);
        }

        return view('ingresos-asistencia.promesas', compact('totales', 'añosDisponibles', 'año', 'mes', 'categoria', 'categoriasDisponibles'));
    }

    public function pdfPromesas(Request $request)
    {
        $año = $request->get('año', date('Y'));
        $mes = $request->get('mes', date('m'));
        $categoria = $request->get('categoria', null);

        $totales = $this->calcularTotales($año, $mes, $categoria);

        $pdf = Pdf::loadView('pdfs.promesas', compact('totales', 'año', 'mes', 'categoria'));

        return $pdf->download('reporte_promesas_'.$año.'_'.$mes.'.pdf');
    }

    public function pdfAnual(Request $request)
    {
        $año = $request->get('año', date('Y'));
        $categoria = $request->get('categoria', null);

        // Calcular totales de todo el año (mes a mes)
        $totalesPorMes = [];
        $grandTotal = [
            'prometido' => 0,
            'dado' => 0,
            'faltante' => 0,
            'profit' => 0,
        ];

        for ($mes = 1; $mes <= 12; $mes++) {
            $totalesMes = $this->calcularTotales($año, $mes, $categoria);

            $totalesPorMes[] = [
                'mes' => Carbon::create($año, $mes, 1)->locale('es')->translatedFormat('F'),
                'totales' => $totalesMes['grand_total'],
            ];

            $grandTotal['prometido'] += $totalesMes['grand_total']['prometido'];
            $grandTotal['dado'] += $totalesMes['grand_total']['dado'];
            $grandTotal['faltante'] += $totalesMes['grand_total']['faltante'];
            $grandTotal['profit'] += $totalesMes['grand_total']['profit'];
        }

        $pdf = Pdf::loadView('pdfs.promesas-anual', compact('totalesPorMes', 'grandTotal', 'año', 'categoria'));

        return $pdf->download('reporte_promesas_anual_'.$año.'.pdf');
    }

    public function porClase(Request $request)
    {
        $año = $request->get('año', date('Y'));
        $mes = $request->get('mes', date('m'));
        $claseId = $request->get('clase_id', null);

        $añoActual = date('Y');
        $añosDisponibles = range($añoActual - 2, $añoActual);
        $clasesDisponibles = ClaseAsistencia::activas()->ordenadas()->get();
        $categoriasDisponibles = tenant_categories(['excluir_de_promesas' => false]);

        $totales = null;
        $claseNombre = 'Capilla (Adultos)';

        if ($claseId !== null) {
            // Filtrar personas por clase
            if ($claseId === 'capilla') {
                // Capilla = personas sin clase asignada (via pivot)
                $personaIds = Persona::where('activo', true)
                    ->whereDoesntHave('clasesAsistencia')
                    ->pluck('id')
                    ->toArray();
                $claseNombre = 'Capilla (Adultos)';
            } else {
                $clase = ClaseAsistencia::find($claseId);
                if ($clase) {
                    $personaIds = $clase->personas()
                        ->where('activo', true)
                        ->pluck('personas.id')
                        ->toArray();
                    $claseNombre = $clase->nombre;
                } else {
                    $personaIds = [];
                }
            }

            if ($mes === 'todos') {
                $totales = $this->calcularTotalesPorClase($año, null, $personaIds);
            } else {
                $totales = $this->calcularTotalesPorClase($año, $mes, $personaIds);
            }
        }

        return view('ingresos-asistencia.promesas-por-clase', compact(
            'totales', 'añosDisponibles', 'año', 'mes', 'claseId',
            'clasesDisponibles', 'categoriasDisponibles', 'claseNombre'
        ));
    }

    private function calcularTotalesPorClase($año, $mes, array $personaIds)
    {
        $personas = Persona::whereIn('id', $personaIds)->with('promesas')->get();

        $totalesPorCategoria = [];
        $grandTotal = ['prometido' => 0, 'dado' => 0, 'faltante' => 0, 'profit' => 0];

        $categoriasExcluidas = tenant_categories(['excluir_de_promesas' => true])
            ->pluck('slug')->map(fn ($s) => strtolower($s))->toArray();

        // Prometido
        foreach ($personas as $persona) {
            foreach ($persona->promesas as $promesa) {
                if (in_array(strtolower($promesa->categoria), $categoriasExcluidas)) {
                    continue;
                }

                $cat = $promesa->categoria;
                if (! isset($totalesPorCategoria[$cat])) {
                    $totalesPorCategoria[$cat] = [
                        'categoria' => ucfirst($cat),
                        'total_prometido' => 0,
                        'total_dado' => 0,
                        'faltante' => 0,
                        'profit' => 0,
                    ];
                }

                if ($mes) {
                    $montoPrometido = $this->calcularMontoPrometidoMes($promesa, $año, $mes);
                } else {
                    $montoPrometido = 0;
                    for ($m = 1; $m <= 12; $m++) {
                        $montoPrometido += $this->calcularMontoPrometidoMes($promesa, $año, $m);
                    }
                }
                $totalesPorCategoria[$cat]['total_prometido'] += $montoPrometido;
            }
        }

        // Dado (solo sobres de personas en esta clase)
        $categoriasPromesa = tenant_categories(['excluir_de_promesas' => false])->pluck('slug')->toArray();

        foreach ($categoriasPromesa as $cat) {
            $query = SobreDetalle::whereHas('sobre', function ($q) use ($año, $mes, $personaIds) {
                $q->whereIn('persona_id', $personaIds)
                    ->whereHas('culto', function ($cq) use ($año, $mes) {
                        $cq->whereYear('fecha', $año);
                        if ($mes) {
                            $cq->whereMonth('fecha', $mes);
                        }
                    });
            })->where('categoria', $cat);

            $montoDado = $query->sum('monto');

            if ($montoDado > 0 && ! isset($totalesPorCategoria[$cat])) {
                $totalesPorCategoria[$cat] = [
                    'categoria' => ucfirst($cat),
                    'total_prometido' => 0,
                    'total_dado' => 0,
                    'faltante' => 0,
                    'profit' => 0,
                ];
            }

            if (isset($totalesPorCategoria[$cat])) {
                $totalesPorCategoria[$cat]['total_dado'] = $montoDado;
            }
        }

        // Faltante / Profit
        foreach ($totalesPorCategoria as $cat => $datos) {
            if (in_array(strtolower($cat), $categoriasExcluidas)) {
                unset($totalesPorCategoria[$cat]);

                continue;
            }
            $saldo = $datos['total_dado'] - $datos['total_prometido'];
            $totalesPorCategoria[$cat]['faltante'] = $saldo < 0 ? abs($saldo) : 0;
            $totalesPorCategoria[$cat]['profit'] = $saldo >= 0 ? $saldo : 0;

            $grandTotal['prometido'] += $datos['total_prometido'];
            $grandTotal['dado'] += $datos['total_dado'];
            $grandTotal['faltante'] += $totalesPorCategoria[$cat]['faltante'];
            $grandTotal['profit'] += $totalesPorCategoria[$cat]['profit'];
        }

        return [
            'categorias' => array_values($totalesPorCategoria),
            'grand_total' => $grandTotal,
            'total_personas' => count($personaIds),
        ];
    }

    /** @var array<int, array{prometido: array, dado: array, nombres: array}> por año */
    private array $porAnio = [];

    private ?ConversorUsd $conversor = null;

    /**
     * Totales de un mes por categoría de compromiso: lo prometido (motor único: personas activas,
     * la última promesa de cada categoría, semanal x domingos, quincenal x 2, dólares al tipo de
     * cambio de fin de mes) y TODO lo que entró a la categoría ese mes (incluidos anónimos y quien no
     * prometió; dólares con el conversor único). Nada posterior a hoy cuenta.
     *
     * Antes se hacían ~4.000 consultas para el reporte anual (las categorías y el tipo de cambio se
     * leían por cada promesa y mes): ahora el año se calcula una sola vez (QA-030).
     */
    private function calcularTotales($año, $mes, $categoria = null)
    {
        $datos = $this->datosDelAnio((int) $año);
        $mes = (int) $mes;

        $totalesPorCategoria = [];
        $grandTotal = ['prometido' => 0, 'dado' => 0, 'faltante' => 0, 'profit' => 0];

        foreach ($datos['nombres'] as $cat => $nombre) {
            if ($categoria && $cat !== $categoria) {
                continue;
            }
            $prometido = Dinero::colones($datos['prometido'][$mes][$cat] ?? 0);
            $dado = Dinero::colones($datos['dado'][$mes][$cat] ?? 0);
            if ($prometido <= 0 && $dado <= 0) {
                continue;
            }
            $saldo = round($dado - $prometido, 2);
            $totalesPorCategoria[] = [
                'categoria' => $nombre,
                'total_prometido' => $prometido,
                'total_dado' => $dado,
                'faltante' => $saldo < 0 ? abs($saldo) : 0,
                'profit' => $saldo > 0 ? $saldo : 0,
            ];
        }

        foreach ($totalesPorCategoria as $fila) {
            $grandTotal['prometido'] += $fila['total_prometido'];
            $grandTotal['dado'] += $fila['total_dado'];
            $grandTotal['faltante'] += $fila['faltante'];
            $grandTotal['profit'] += $fila['profit'];
        }

        return [
            'categorias' => $totalesPorCategoria,
            'grand_total' => $grandTotal,
        ];
    }

    /** Prometido y dado (en céntimos) por mes y categoría de compromiso de todo un año, una sola vez. */
    private function datosDelAnio(int $año): array
    {
        if (isset($this->porAnio[$año])) {
            return $this->porAnio[$año];
        }

        $nombres = tenant_categories(['excluir_de_promesas' => false])->pluck('nombre', 'slug')->all();
        $prometido = [];
        $dado = [];
        $hoy = Carbon::today();

        if ($año <= $hoy->year) {
            $personas = Persona::where('activo', true)->whereHas('promesas')->get(['id', 'nombre', 'activo']);
            $resultado = app(MotorReportes::class)->calcular(PeriodoReporte::deMeses($año, 1, 12), $personas);
            foreach ($resultado->cuentas() as $cuenta) {
                foreach ($resultado->periodo->meses() as $mesPeriodo) {
                    foreach (array_keys($cuenta->promesas()) as $cat) {
                        $prometido[$mesPeriodo->mes][$cat] = ($prometido[$mesPeriodo->mes][$cat] ?? 0) + $cuenta->esperadoDelMesCentimos($mesPeriodo->clave, $cat);
                    }
                }
            }

            $conversor = $this->conversor ??= app(ConversorUsd::class);
            $hasta = $año === $hoy->year ? $hoy->toDateString() : $año.'-12-31';
            $filas = DB::table('sobre_detalles as d')
                ->join('sobres as s', 's.id', '=', 'd.sobre_id')
                ->join('cultos as c', 'c.id', '=', 's.culto_id')
                ->where('c.tenant_id', tenant()?->id)
                ->whereBetween('c.fecha', [$año.'-01-01', $hasta])
                ->whereIn('d.categoria', array_keys($nombres))
                ->get(['c.fecha', 'd.categoria', 'd.monto', 's.moneda', 's.tipo_cambio_venta']);
            foreach ($filas as $fila) {
                $mes = (int) substr((string) $fila->fecha, 5, 2);
                $dado[$mes][$fila->categoria] = ($dado[$mes][$fila->categoria] ?? 0)
                    + $conversor->centimosSobre($fila->monto, $fila->moneda, $fila->tipo_cambio_venta, substr((string) $fila->fecha, 0, 10));
            }
        }

        return $this->porAnio[$año] = ['prometido' => $prometido, 'dado' => $dado, 'nombres' => $nombres];
    }

    /**
     * Monto prometido de una promesa en un mes según la frecuencia, en colones (los dólares, al tipo
     * de cambio del último día del mes, regla única del ConversorUsd). Lo usa el reporte por clase.
     */
    private function calcularMontoPrometidoMes($promesa, $año, $mes): float
    {
        $domingos = PeriodoReporte::domingosDelMes(\Carbon\CarbonImmutable::create((int) $año, (int) $mes, 1));
        $montoBase = (float) $promesa->monto;
        $montoTotal = match ($promesa->frecuencia) {
            'semanal' => $montoBase * $domingos,
            'quincenal' => $montoBase * 2,
            default => $montoBase,
        };

        $this->conversor ??= app(ConversorUsd::class);

        return $this->conversor->promesaAColones($montoTotal, $promesa->moneda ?? 'CRC', (int) $año, (int) $mes);
    }

    /**
     * Calcula totales de todo el año (suma de todos los meses)
     */
    private function calcularTotalesAnuales($año, $categoria = null)
    {
        $totalesPorCategoria = [];
        $grandTotal = [
            'prometido' => 0,
            'dado' => 0,
            'faltante' => 0,
            'profit' => 0,
        ];

        // Sumar todos los meses del año
        for ($mes = 1; $mes <= 12; $mes++) {
            $totalesMes = $this->calcularTotales($año, $mes, $categoria);

            // Sumar por categoría
            foreach ($totalesMes['categorias'] as $catData) {
                $cat = strtolower(str_replace(' ', '_', $catData['categoria']));

                if (! isset($totalesPorCategoria[$cat])) {
                    $totalesPorCategoria[$cat] = [
                        'categoria' => $catData['categoria'],
                        'total_prometido' => 0,
                        'total_dado' => 0,
                        'faltante' => 0,
                        'profit' => 0,
                    ];
                }

                $totalesPorCategoria[$cat]['total_prometido'] += $catData['total_prometido'];
                $totalesPorCategoria[$cat]['total_dado'] += $catData['total_dado'];
                $totalesPorCategoria[$cat]['faltante'] += $catData['faltante'];
                $totalesPorCategoria[$cat]['profit'] += $catData['profit'];
            }

            // Sumar totales generales
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
}

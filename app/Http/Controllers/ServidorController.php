<?php

namespace App\Http\Controllers;

use App\Models\AsistenciaServidor;
use App\Models\Compromiso;
use App\Models\Culto;
use App\Models\Promesa;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ServidorController extends Controller
{
    public function index(Request $request)
    {
        // Culto mas cercano a hoy
        $cultoCercano = Culto::orderByRaw('ABS(DATEDIFF(fecha, CURDATE()))')
            ->first();

        $cultoSeleccionado = $request->culto_id
            ? Culto::find($request->culto_id)
            : $cultoCercano;

        // Todos los cultos para el selector
        $cultos = Culto::orderBy('fecha', 'desc')->get();

        // Servidores (usuarios con rol servidor)
        $servidores = User::where(function ($q) {
            $q->where('rol', 'servidor')
                ->orWhereHas('tenantRole', function ($q2) {
                    $q2->whereRaw("JSON_EXTRACT(permisos, '$.marcar_asistencia') = true");
                });
        })
            ->where('tenant_id', auth()->user()->tenant_id)
            ->get();

        // Asistencias del culto seleccionado
        $asistencias = collect();
        if ($cultoSeleccionado) {
            $asistencias = AsistenciaServidor::where('culto_id', $cultoSeleccionado->id)
                ->with('user')
                ->get()
                ->keyBy('user_id');
        }

        return view('admin.servidores.index', compact(
            'cultos',
            'cultoSeleccionado',
            'servidores',
            'asistencias'
        ));
    }

    public function reporte(Request $request)
    {
        $mes = (int) $request->input('mes', now()->month);
        $ano = (int) $request->input('ano', now()->year);

        return view('admin.servidores.reporte', $this->cargarDataReporte($mes, $ano));
    }

    public function qrCulto(Culto $culto)
    {
        $data = json_encode([
            'culto_id' => $culto->id,
            'tenant_id' => $culto->tenant_id,
        ]);

        $qrSvg = QrCode::format('svg')
            ->size(400)
            ->errorCorrection('H')
            ->generate($data);

        $qrBase64 = base64_encode($qrSvg);

        $pdf = Pdf::loadView('pdfs.qr-culto', [
            'culto' => $culto,
            'qrBase64' => $qrBase64,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('qr-culto-'.$culto->id.'.pdf');
    }

    /**
     * PDF general de servidores en UNA SOLA PÁGINA (Fase 5).
     * Roy pidió específicamente que sea un resumen condensado, no uno por servidor.
     */
    public function pdfReporteGeneral(Request $request)
    {
        $mes = (int) $request->input('mes', now()->month);
        $ano = (int) $request->input('ano', now()->year);
        $data = $this->cargarDataReporte($mes, $ano);

        $pdf = Pdf::loadView('pdfs.servidores-general', $data)
            ->setPaper('a4', 'portrait');

        $nombreMes = $data['meses'][$mes] ?? '';

        return $pdf->download("servidores_{$nombreMes}_{$ano}.pdf");
    }

    /**
     * PDF individual de un servidor con su detalle: asistencia mensual,
     * promesas y cumplimiento.
     */
    public function pdfIndividual(Request $request, User $servidor)
    {
        $ano = (int) $request->input('ano', now()->year);
        $mes = (int) $request->input('mes', now()->month);

        // Validar tenant
        if ($servidor->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        // Cultos del año
        $cultosAño = Culto::whereYear('fecha', $ano)->orderBy('fecha')->get();
        $totalCultosAño = $cultosAño->count();

        // Asistencia (AsistenciaServidor) por mes del año
        $asistenciasPorMes = AsistenciaServidor::whereIn('culto_id', $cultosAño->pluck('id'))
            ->where('user_id', $servidor->id)
            ->get()
            ->groupBy(function ($a) use ($cultosAño) {
                return $cultosAño->firstWhere('id', $a->culto_id)->fecha->month;
            })
            ->map->count();

        $cultosPorMes = $cultosAño->groupBy(fn ($c) => $c->fecha->month)->map->count();

        $asistenciaSerie = collect(range(1, 12))->map(function ($m) use ($asistenciasPorMes, $cultosPorMes) {
            return [
                'mes' => $m,
                'asistencias' => (int) ($asistenciasPorMes[$m] ?? 0),
                'cultos' => (int) ($cultosPorMes[$m] ?? 0),
            ];
        });

        // Promesas + cumplimiento del año
        $promesas = $servidor->persona
            ? Promesa::where('persona_id', $servidor->persona->id)->get()
            : collect();
        $compromisos = $servidor->persona
            ? Compromiso::where('persona_id', $servidor->persona->id)->where('año', $ano)->get()
            : collect();

        $cumplimiento = [];
        foreach ($promesas as $promesa) {
            $comps = $compromisos->where('categoria', $promesa->categoria);
            $prometido = (float) $comps->sum('monto_prometido');
            $dado = (float) $comps->sum('monto_dado');
            $cumplimiento[] = [
                'categoria' => $promesa->categoria,
                'moneda' => $promesa->moneda ?? 'CRC',
                'prometido' => $prometido,
                'dado' => $dado,
                'saldo' => $dado - $prometido,
                'pct' => $prometido > 0 ? round(($dado / $prometido) * 100, 1) : 0,
            ];
        }

        $totalAsistencias = $asistenciaSerie->sum('asistencias');
        $pctAño = $totalCultosAño > 0 ? round(($totalAsistencias / $totalCultosAño) * 100, 1) : 0;

        $pdf = Pdf::loadView('pdfs.servidor-individual', [
            'servidor' => $servidor,
            'ano' => $ano,
            'mes' => $mes,
            'totalCultosAño' => $totalCultosAño,
            'totalAsistencias' => $totalAsistencias,
            'pctAño' => $pctAño,
            'asistenciaSerie' => $asistenciaSerie,
            'cumplimiento' => $cumplimiento,
        ])->setPaper('a4', 'portrait');

        $nombre = preg_replace('/[^a-z0-9]+/i', '_', strtolower($servidor->name));

        return $pdf->download("servidor_{$nombre}_{$ano}.pdf");
    }

    protected function cargarDataReporte(int $mes, int $ano): array
    {
        $servidores = User::where(function ($q) {
            $q->where('rol', 'servidor')
                ->orWhereHas('tenantRole', function ($q2) {
                    $q2->whereRaw("JSON_EXTRACT(permisos, '$.marcar_asistencia') = true");
                });
        })
            ->where('tenant_id', auth()->user()->tenant_id)
            ->with('persona')
            ->get();

        $cultosMes = Culto::whereMonth('fecha', $mes)
            ->whereYear('fecha', $ano)
            ->orderBy('fecha')
            ->get();
        $totalCultosMes = $cultosMes->count();

        $asistenciasPorServidor = AsistenciaServidor::whereIn('culto_id', $cultosMes->pluck('id'))
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // Promesas + cumplimiento del año por servidor
        $promesasPorServidor = [];
        $cumplimientoPorServidor = [];
        foreach ($servidores as $servidor) {
            if (! $servidor->persona) {
                continue;
            }
            $promesas = Promesa::where('persona_id', $servidor->persona->id)->get();
            $promesasPorServidor[$servidor->id] = $promesas;

            $compromisos = Compromiso::where('persona_id', $servidor->persona->id)
                ->where('año', $ano)
                ->get();
            $prometido = (float) $compromisos->sum('monto_prometido');
            $dado = (float) $compromisos->sum('monto_dado');
            $cumplimientoPorServidor[$servidor->id] = [
                'prometido' => $prometido,
                'dado' => $dado,
                'saldo' => $dado - $prometido,
                'pct' => $prometido > 0 ? round(($dado / $prometido) * 100, 1) : 0,
            ];
        }

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return compact(
            'servidores',
            'asistenciasPorServidor',
            'promesasPorServidor',
            'cumplimientoPorServidor',
            'totalCultosMes',
            'mes',
            'ano',
            'meses',
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AsistenciaServidor;
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
        $mes = $request->input('mes', now()->month);
        $ano = $request->input('ano', now()->year);

        // Servidores del tenant
        $servidores = User::where(function ($q) {
            $q->where('rol', 'servidor')
                ->orWhereHas('tenantRole', function ($q2) {
                    $q2->whereRaw("JSON_EXTRACT(permisos, '$.marcar_asistencia') = true");
                });
        })
            ->where('tenant_id', auth()->user()->tenant_id)
            ->with('persona')
            ->get();

        // Cultos del mes
        $cultosMes = Culto::whereMonth('fecha', $mes)
            ->whereYear('fecha', $ano)
            ->orderBy('fecha')
            ->get();

        $totalCultosMes = $cultosMes->count();

        // Asistencias del mes por servidor
        $asistenciasPorServidor = AsistenciaServidor::whereIn('culto_id', $cultosMes->pluck('id'))
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // Promesas por persona (vinculada al servidor)
        $promesasPorServidor = [];
        foreach ($servidores as $servidor) {
            if ($servidor->persona) {
                $promesas = Promesa::where('persona_id', $servidor->persona->id)->get();
                $promesasPorServidor[$servidor->id] = $promesas;
            }
        }

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return view('admin.servidores.reporte', compact(
            'servidores',
            'asistenciasPorServidor',
            'promesasPorServidor',
            'totalCultosMes',
            'mes',
            'ano',
            'meses'
        ));
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
}

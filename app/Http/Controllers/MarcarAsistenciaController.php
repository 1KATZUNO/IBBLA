<?php

namespace App\Http\Controllers;

use App\Models\AsistenciaServidor;
use App\Models\Culto;
use Illuminate\Http\Request;

class MarcarAsistenciaController extends Controller
{
    public function index()
    {
        $historial = AsistenciaServidor::where('user_id', auth()->id())
            ->with('culto')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('marcar-asistencia.index', compact('historial'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'culto_id' => 'required|integer',
        ]);

        $data = $request->only('culto_id');
        $tenantId = auth()->user()->tenant_id;

        // Validar que el culto existe y pertenece al tenant
        $culto = Culto::withoutGlobalScopes()
            ->where('id', $data['culto_id'])
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$culto) {
            return response()->json([
                'success' => false,
                'message' => 'Culto no encontrado o no pertenece a su congregacion.',
            ], 404);
        }

        // Verificar si ya marco asistencia
        $existe = AsistenciaServidor::where('user_id', auth()->id())
            ->where('culto_id', $culto->id)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya marcaste asistencia para este culto.',
            ], 409);
        }

        AsistenciaServidor::create([
            'user_id' => auth()->id(),
            'culto_id' => $culto->id,
            'tenant_id' => $tenantId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Asistencia marcada correctamente para ' . $culto->tipo_nombre . ' - ' . $culto->fecha->format('d/m/Y'),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class MiPerfilController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $persona = $user->persona;

        if (!$persona) {
            return redirect()->route('dashboard')
                ->with('error', 'No se encontró información de persona asociada.');
        }

        $persona->load(['sobres.detalles', 'promesas', 'compromisos']);
        
        // Calcular cumplimiento de promesas del mes actual
        $promesasConEstatus = $persona->promesas->map(function ($promesa) use ($persona) {
            $montoPagado = $persona->sobres()
                ->whereHas('detalles', function ($query) use ($promesa) {
                    $query->where('categoria', $promesa->categoria);
                })
                ->whereMonth('created_at', Carbon::now()->month)
                ->get()
                ->sum(function ($sobre) use ($promesa) {
                    return $sobre->detalles()
                        ->where('categoria', $promesa->categoria)
                        ->sum('monto');
                });

            return [
                'promesa' => $promesa,
                'pagado' => $montoPagado,
                'faltante' => max(0, $promesa->monto - $montoPagado),
                'cumplido' => $montoPagado >= $promesa->monto,
                'porcentaje' => $promesa->monto > 0 ? min(100, ($montoPagado / $promesa->monto) * 100) : 0,
            ];
        });

        // Obtener compromisos con saldo negativo (deudas)
        $compromisos = $persona->compromisos()
            ->where('saldo_actual', '<', 0)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($compromiso) {
                $compromiso->deuda = abs($compromiso->saldo_actual); // Convertir a positivo para mostrar
                $compromiso->descripcion = ucfirst($compromiso->categoria) . ' ' . 
                    now()->locale('es')->parse($compromiso->año . '-' . $compromiso->mes . '-01')->isoFormat('MMMM YYYY');
                return $compromiso;
            });

        return view('mi-perfil.index', compact('persona', 'promesasConEstatus', 'compromisos'));
    }
}

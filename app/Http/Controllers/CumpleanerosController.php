<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CumpleanerosController extends Controller
{
    public function index(Request $request)
    {
        $mesSeleccionado = $request->filled('mes') ? (int) $request->mes : Carbon::now()->month;
        $mesHoyReal = Carbon::now()->month;

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $nombreMes = $meses[$mesSeleccionado] ?? $meses[1];

        $cumpleaneros = Persona::where('activo', true)
            ->whereNotNull('fecha_nacimiento')
            ->whereMonth('fecha_nacimiento', $mesSeleccionado)
            ->orderByRaw('DAY(fecha_nacimiento) ASC')
            ->get();

        return view('cumpleaneros.index', compact('cumpleaneros', 'nombreMes', 'mesSeleccionado', 'mesHoyReal', 'meses'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Services\InformePersonaService;
use App\Services\Reportes\SincronizadorCompromisos;
use Carbon\Carbon;

class CompromisoController extends Controller
{
    /** Años que se pueden consultar hacia atrás. */
    private const ANIOS_ATRAS = 2;

    /**
     * Estado de compromisos de una persona en un año, SOLO LECTURA (QA-025): las mismas cifras del
     * estado de cuenta en PDF, de Mi perfil y del reporte de cumplimiento, porque salen del mismo
     * cálculo (InformePersonaService sobre el motor único). Año en curso: corte hoy; años pasados:
     * al 31 de diciembre. Ya no guarda nada en la tabla compromisos (eso lo hace
     * compromisos:sincronizar).
     */
    public function show(Persona $persona, InformePersonaService $informe)
    {
        $hoy = Carbon::today();
        $año = (int) request('año', $hoy->year);
        $año = max($hoy->year - self::ANIOS_ATRAS, min($hoy->year, $año));

        $hasta = $año === $hoy->year ? $hoy : Carbon::create($año, 12, 31);
        $datos = $informe->datos($persona, $hasta);

        $anios = range($hoy->year, $hoy->year - self::ANIOS_ATRAS);

        return view('compromisos.show', compact('persona', 'año', 'anios', 'datos'));
    }

    /**
     * Botón del admin "Recalcular compromisos": rehace la tabla compromisos del año en curso de la
     * iglesia con el motor (idempotente, solo meses con cultos). La usan el Dashboard de promesas y
     * el Excel de promesas.
     */
    public function recalcular(SincronizadorCompromisos $sincronizador)
    {
        $tenant = tenant();
        if (! $tenant) {
            return redirect()->back()->with('error', 'No hay iglesia activa.');
        }

        $r = $sincronizador->sincronizar($tenant, Carbon::now()->year);

        return redirect()->back()->with('success', sprintf(
            'Compromisos recalculados (%d meses con cultos): %d nuevos, %d actualizados, %d borrados, %d sin cambios.',
            count($r['meses']), $r['creados'], $r['actualizados'], $r['borrados'], $r['sin_cambios'],
        ));
    }
}

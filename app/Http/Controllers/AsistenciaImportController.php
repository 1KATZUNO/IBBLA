<?php

namespace App\Http\Controllers;

use App\Exports\PlantillaAsistenciaExport;
use App\Imports\AsistenciaImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Carga masiva retroactiva de asistencias desde una plantilla Excel.
 *
 * Endpoints (solo admin):
 *  - GET  /admin/asistencia/importar           → vista con form
 *  - GET  /admin/asistencia/importar/plantilla → descarga Excel plantilla
 *  - POST /admin/asistencia/importar           → procesa el archivo
 */
class AsistenciaImportController extends Controller
{
    public function index(): View
    {
        return view('asistencia.importar');
    }

    public function descargarPlantilla(): BinaryFileResponse
    {
        $nombre = 'plantilla_asistencia_'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new PlantillaAsistenciaExport, $nombre);
    }

    public function procesar(Request $request): RedirectResponse|View
    {
        $validated = $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        $import = new AsistenciaImport(dryRun: false);

        try {
            Excel::import($import, $validated['archivo']);
        } catch (\Throwable $e) {
            return view('asistencia.importar', [
                'errores' => [['fila' => 0, 'mensaje' => 'Error inesperado al leer el archivo: '.$e->getMessage()]],
                'archivoNombre' => $validated['archivo']->getClientOriginalName(),
            ]);
        }

        // Si hubo errores DE VALIDACION pero las filas sanas SI se persistieron
        // (cada fila va en su propia transacción), mostramos resumen + errores.
        if (! empty($import->errores)) {
            return view('asistencia.importar', [
                'errores' => $import->errores,
                'creadas' => $import->creadas,
                'actualizadas' => $import->actualizadas,
                'archivoNombre' => $validated['archivo']->getClientOriginalName(),
            ]);
        }

        return redirect()
            ->route('asistencia.importar.index')
            ->with('success', sprintf(
                'Import completado: %d asistencias creadas, %d actualizadas.',
                count($import->creadas),
                count($import->actualizadas),
            ))
            ->with('creadas', $import->creadas)
            ->with('actualizadas', $import->actualizadas);
    }
}

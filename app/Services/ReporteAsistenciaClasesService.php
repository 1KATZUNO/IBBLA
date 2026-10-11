<?php

namespace App\Services;

use App\Models\ClaseAsistencia;
use App\Models\Culto;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Reporte de asistencia por clase en un rango de fechas (niños, niñas, maestros y total).
 *
 * Lee la asistencia que ya se guarda por culto y clase (`asistencia_clase_detalle`); no tiene tablas propias.
 * Es la única fuente de los números de la pantalla, el PDF, el CSV y el Excel, para que los cuatro coincidan.
 *
 * Reglas:
 *  - Una fila por fecha y clase. Si el mismo día hubo dos cultos con registro de la misma clase
 *    (por ejemplo Domingo AM y PM) se suman en una sola fila y cuentan como una fecha.
 *  - Niños = hombres, niñas = mujeres, maestros = maestros_hombres + maestros_mujeres.
 *    Total = niños + niñas + maestros (igual que Asistencia::getTotalClases()).
 *  - Promedio de una clase = suma de la clase en el rango ÷ fechas en que la clase tiene registro.
 *    Las fechas sin registro (culto sin asistencia, o asistencia sin esa clase) no cuentan, así que no bajan el promedio.
 *  - Promedio general = suma de todas las clases ÷ fechas con al menos una clase registrada.
 *  - Una clase con 0 niños, 0 niñas y 0 maestros se trata como sin registro, salvo que se pida contar las fechas en 0:
 *    los formularios guardan filas en 0 para todas las clases aunque ese día la clase no se reúna (p. ej. un miércoles).
 *  - La capilla no es una clase de niños: queda fuera, igual que en el resto de la app (ClaseAsistencia::regulares()).
 */
class ReporteAsistenciaClasesService
{
    public const COLUMNAS = ['ninos', 'ninas', 'maestros', 'total'];

    /** Decimales de los promedios en pantalla, PDF, CSV y Excel. */
    public const DECIMALES = 2;

    /**
     * @return array{
     *     desde: Carbon,
     *     hasta: Carbon,
     *     clase_id: ?int,
     *     clase_nombre: ?string,
     *     incluir_ceros: bool,
     *     clases: list<array{
     *         id: int, nombre: string, color: string, activa: bool,
     *         filas: list<array{fecha: Carbon, cultos: string, ninos: int, ninas: int, maestros: int, total: int}>,
     *         fechas: int,
     *         totales: array{ninos: int, ninas: int, maestros: int, total: int},
     *         promedios: array{ninos: float, ninas: float, maestros: float, total: float},
     *     }>,
     *     general: array{
     *         fechas: int,
     *         totales: array{ninos: int, ninas: int, maestros: int, total: int},
     *         promedios: array{ninos: float, ninas: float, maestros: float, total: float},
     *     },
     *     cultos_en_rango: int,
     *     fechas_sin_registro: list<Carbon>,
     * }
     */
    public function generar(Carbon $desde, Carbon $hasta, ?int $claseId = null, bool $incluirCeros = false): array
    {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();

        $cultos = Culto::query()
            ->with(['serviceType', 'asistencia.detallesClases.claseAsistencia'])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        // clase_id => ['clase' => ClaseAsistencia, 'fechas' => ['Y-m-d' => fila acumulada]]
        $porClase = [];

        foreach ($cultos as $culto) {
            $detalles = $culto->asistencia?->detallesClases ?? collect();

            foreach ($detalles as $detalle) {
                $clase = $detalle->claseAsistencia;

                if (! $clase || $clase->slug === 'capilla' || ($claseId !== null && $clase->id !== $claseId)) {
                    continue;
                }

                $ninos = (int) $detalle->hombres;
                $ninas = (int) $detalle->mujeres;
                $maestros = $detalle->getTotalMaestros();

                if (! $incluirCeros && ($ninos + $ninas + $maestros) === 0) {
                    continue;
                }

                $fecha = $culto->fecha->toDateString();
                $fila = $porClase[$clase->id]['fechas'][$fecha] ?? [
                    'fecha' => $culto->fecha->copy()->startOfDay(),
                    'cultos' => [],
                    'ninos' => 0,
                    'ninas' => 0,
                    'maestros' => 0,
                ];

                $fila['cultos'][$culto->tipo_nombre] = true;
                $fila['ninos'] += $ninos;
                $fila['ninas'] += $ninas;
                $fila['maestros'] += $maestros;

                $porClase[$clase->id]['clase'] = $clase;
                $porClase[$clase->id]['fechas'][$fecha] = $fila;
            }
        }

        $bloques = collect($porClase)
            ->sortBy(fn (array $bloque) => sprintf('%06d|%s', $bloque['clase']->orden, mb_strtolower($bloque['clase']->nombre)))
            ->map(fn (array $bloque) => $this->armarBloque($bloque['clase'], $bloque['fechas']))
            ->values();

        $fechasConRegistro = $bloques
            ->flatMap(fn (array $bloque) => collect($bloque['filas'])->map(fn (array $fila) => $fila['fecha']->toDateString()))
            ->unique();

        $fechasSinRegistro = $cultos
            ->map(fn (Culto $culto) => $culto->fecha->copy()->startOfDay())
            ->unique(fn (Carbon $fecha) => $fecha->toDateString())
            ->reject(fn (Carbon $fecha) => $fechasConRegistro->contains($fecha->toDateString()))
            ->values()
            ->all();

        $totalesGenerales = $this->sumar($bloques->pluck('totales'));
        $claseFiltro = $claseId !== null ? ClaseAsistencia::find($claseId) : null;

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'clase_id' => $claseId,
            'clase_nombre' => $claseFiltro?->nombre,
            'incluir_ceros' => $incluirCeros,
            'clases' => $bloques->all(),
            'general' => [
                'fechas' => $fechasConRegistro->count(),
                'totales' => $totalesGenerales,
                'promedios' => $this->promediar($totalesGenerales, $fechasConRegistro->count()),
            ],
            'cultos_en_rango' => $cultos->count(),
            'fechas_sin_registro' => $fechasSinRegistro,
        ];
    }

    /**
     * Rango por defecto de la pantalla: los últimos dos meses hasta hoy.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function rangoPorDefecto(): array
    {
        $hoy = Carbon::today();

        return [$hoy->copy()->subMonthsNoOverflow(2), $hoy];
    }

    /**
     * Promedio listo para mostrar: dos decimales con coma (16,67). El CSV usa el mismo formato.
     */
    public static function formatearPromedio(float|int $valor): string
    {
        return number_format($valor, self::DECIMALES, ',', '');
    }

    /**
     * Promedio por columna: total ÷ cantidad de fechas con registro (0 si no hay ninguna).
     *
     * @param  array{ninos: int, ninas: int, maestros: int, total: int}  $totales
     * @return array{ninos: float, ninas: float, maestros: float, total: float}
     */
    public function promediar(array $totales, int $fechas): array
    {
        $promedios = [];
        foreach (self::COLUMNAS as $columna) {
            $promedios[$columna] = $fechas > 0 ? round($totales[$columna] / $fechas, self::DECIMALES) : 0.0;
        }

        return $promedios;
    }

    /**
     * @param  array<string, array{fecha: Carbon, cultos: array<string, true>, ninos: int, ninas: int, maestros: int}>  $fechas
     */
    private function armarBloque(ClaseAsistencia $clase, array $fechas): array
    {
        ksort($fechas);

        $filas = collect($fechas)
            ->map(fn (array $fila) => [
                'fecha' => $fila['fecha'],
                'cultos' => implode(' + ', array_keys($fila['cultos'])),
                'ninos' => $fila['ninos'],
                'ninas' => $fila['ninas'],
                'maestros' => $fila['maestros'],
                'total' => $fila['ninos'] + $fila['ninas'] + $fila['maestros'],
            ])
            ->values();

        $totales = $this->sumar($filas);

        return [
            'id' => $clase->id,
            'nombre' => $clase->nombre,
            'color' => $clase->color ?: '#3b82f6',
            'activa' => (bool) $clase->activa,
            'filas' => $filas->all(),
            'fechas' => $filas->count(),
            'totales' => $totales,
            'promedios' => $this->promediar($totales, $filas->count()),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{ninos: int, ninas: int, maestros: int, total: int}
     */
    private function sumar(Collection $items): array
    {
        $totales = [];
        foreach (self::COLUMNAS as $columna) {
            $totales[$columna] = (int) $items->sum($columna);
        }

        return $totales;
    }
}

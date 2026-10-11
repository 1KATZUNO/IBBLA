<?php

namespace App\Services;

use App\Models\Persona;
use Carbon\Carbon;

/**
 * Datos del "Reporte General" de Gestion de Personas: lo dado vs lo esperado
 * (segun la promesa) por persona, mes y categoria con promesa, de enero al mes
 * indicado.
 *
 * Mantiene exactamente las reglas que tenia PersonaController::reporteGeneral:
 * - solo personas activas, ordenadas por nombre;
 * - "dado" = suma de los detalles de sus sobres en cultos del anio, por mes del
 *   culto, sin convertir moneda;
 * - "esperado" = monto de la promesa segun frecuencia (semanal x domingos del
 *   mes, quincenal x 2, mensual x 1); si hay dos promesas de la misma categoria
 *   vale la ultima;
 * - se omiten las personas sin nada dado ni esperado.
 *
 * Lo que cambia es el costo: cada sobre se recorre una sola vez (antes se
 * recorrian todos los sobres de la persona por cada mes), los domingos de cada
 * mes se cuentan una vez y solo se traen de la BD las columnas que se usan.
 */
class ReporteGeneralService
{
    public const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    /**
     * @return array{reporteData: array, totalesGenerales: array, categoriasConPromesa: array}
     */
    public function generar(int $anio, int $mesHasta): array
    {
        // Categorias con subdivision (dado/esperado): las que no se excluyen de promesas.
        $categoriasConPromesa = tenant_categories(['excluir_de_promesas' => false])->pluck('slug')->toArray();

        $personas = Persona::where('activo', true)
            ->select(['id', 'nombre'])
            ->with([
                'promesas:id,persona_id,categoria,monto,frecuencia',
                'sobres' => function ($query) use ($anio) {
                    $query->select(['id', 'persona_id', 'culto_id'])
                        ->whereHas('culto', function ($q) use ($anio) {
                            $q->whereYear('fecha', $anio);
                        })
                        ->with(['detalles:id,sobre_id,categoria,monto', 'culto:id,fecha']);
                },
            ])
            ->orderBy('nombre')
            ->get();

        $domingosPorMes = [];
        for ($mes = 1; $mes <= $mesHasta; $mes++) {
            $domingosPorMes[$mes] = $this->contarDomingos($anio, $mes);
        }

        $reporteData = [];

        foreach ($personas as $persona) {
            $datosPersona = [
                'nombre' => $persona->nombre,
                'meses' => [],
                'totales' => [
                    'dado' => 0,
                    'esperado' => 0,
                    'diferencia' => 0,
                ],
            ];

            // Mapa de promesas por categoria (si se repite la categoria, gana la ultima).
            $promesasMap = [];
            foreach ($persona->promesas as $promesa) {
                $promesasMap[$promesa->categoria] = $promesa;
            }

            // Sobres agrupados por mes del culto, conservando su orden original.
            $sobresPorMes = [];
            foreach ($persona->sobres as $sobre) {
                if ($sobre->culto) {
                    $sobresPorMes[Carbon::parse($sobre->culto->fecha)->month][] = $sobre;
                }
            }

            for ($mes = 1; $mes <= $mesHasta; $mes++) {
                $mesDatos = [
                    'nombre' => self::MESES[$mes],
                    'categorias' => [],
                    'total_dado' => 0,
                    'total_esperado' => 0,
                    'diferencia' => 0,
                ];

                // Lo dado por categoria en este mes.
                $dadoPorCategoria = array_fill_keys($categoriasConPromesa, 0);
                foreach ($sobresPorMes[$mes] ?? [] as $sobre) {
                    foreach ($sobre->detalles as $detalle) {
                        $cat = $detalle->categoria;
                        if (isset($dadoPorCategoria[$cat])) {
                            $dadoPorCategoria[$cat] += $detalle->monto;
                        }
                    }
                }

                // Esperado y acumulados de cada categoria con promesa.
                foreach ($categoriasConPromesa as $cat) {
                    $dado = $dadoPorCategoria[$cat];
                    $esperado = 0;

                    if (isset($promesasMap[$cat])) {
                        $esperado = $this->esperadoDelMes($promesasMap[$cat], $domingosPorMes[$mes]);
                    }

                    $mesDatos['categorias'][$cat] = [
                        'dado' => $dado,
                        'esperado' => $esperado,
                    ];

                    $mesDatos['total_dado'] += $dado;
                    $mesDatos['total_esperado'] += $esperado;
                }

                $mesDatos['diferencia'] = $mesDatos['total_dado'] - $mesDatos['total_esperado'];

                $datosPersona['meses'][] = $mesDatos;

                $datosPersona['totales']['dado'] += $mesDatos['total_dado'];
                $datosPersona['totales']['esperado'] += $mesDatos['total_esperado'];
            }

            $datosPersona['totales']['diferencia'] = $datosPersona['totales']['dado'] - $datosPersona['totales']['esperado'];

            // Solo personas con algo dado o esperado.
            if ($datosPersona['totales']['dado'] > 0 || $datosPersona['totales']['esperado'] > 0) {
                $reporteData[] = $datosPersona;
            }
        }

        return [
            'reporteData' => $reporteData,
            'totalesGenerales' => $this->totalesGenerales($reporteData, $categoriasConPromesa, $mesHasta),
            'categoriasConPromesa' => $categoriasConPromesa,
        ];
    }

    private function totalesGenerales(array $reporteData, array $categoriasConPromesa, int $mesHasta): array
    {
        $totalesGenerales = [
            'meses' => [],
            'total_dado' => 0,
            'total_esperado' => 0,
            'total_diferencia' => 0,
        ];

        for ($mes = 1; $mes <= $mesHasta; $mes++) {
            $totalesGenerales['meses'][$mes] = [
                'categorias' => array_fill_keys($categoriasConPromesa, ['dado' => 0, 'esperado' => 0]),
                'total_dado' => 0,
                'total_esperado' => 0,
                'diferencia' => 0,
            ];
        }

        foreach ($reporteData as $persona) {
            foreach ($persona['meses'] as $idx => $mesDatos) {
                $mes = $idx + 1;
                foreach ($categoriasConPromesa as $cat) {
                    $totalesGenerales['meses'][$mes]['categorias'][$cat]['dado'] += $mesDatos['categorias'][$cat]['dado'];
                    $totalesGenerales['meses'][$mes]['categorias'][$cat]['esperado'] += $mesDatos['categorias'][$cat]['esperado'];
                }
                $totalesGenerales['meses'][$mes]['total_dado'] += $mesDatos['total_dado'];
                $totalesGenerales['meses'][$mes]['total_esperado'] += $mesDatos['total_esperado'];
                $totalesGenerales['meses'][$mes]['diferencia'] += $mesDatos['diferencia'];
            }
            $totalesGenerales['total_dado'] += $persona['totales']['dado'];
            $totalesGenerales['total_esperado'] += $persona['totales']['esperado'];
            $totalesGenerales['total_diferencia'] += $persona['totales']['diferencia'];
        }

        return $totalesGenerales;
    }

    /**
     * Monto esperado de una promesa en un mes segun su frecuencia.
     */
    private function esperadoDelMes($promesa, int $domingosDelMes): float
    {
        switch ($promesa->frecuencia) {
            case 'semanal':
                return $promesa->monto * $domingosDelMes;

            case 'quincenal':
                return $promesa->monto * 2;

            case 'mensual':
            default:
                return $promesa->monto;
        }
    }

    private function contarDomingos(int $anio, int $mes): int
    {
        $domingos = 0;
        $fecha = Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes = $fecha->copy()->endOfMonth();

        while ($fecha->lte($finMes)) {
            if ($fecha->dayOfWeek === Carbon::SUNDAY) {
                $domingos++;
            }
            $fecha->addDay();
        }

        return $domingos;
    }
}

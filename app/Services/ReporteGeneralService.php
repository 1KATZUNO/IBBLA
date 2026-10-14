<?php

namespace App\Services;

use App\Models\Persona;
use App\Services\Reportes\Dinero;
use App\Services\Reportes\MotorReportes;
use App\Services\Reportes\PeriodoReporte;

/**
 * Datos del "Reporte General" de Gestion de Personas: lo dado vs lo esperado
 * (segun la promesa) por persona, mes y categoria de compromiso, de enero al
 * mes indicado.
 *
 * Los numeros salen del motor unico (Reportes\MotorReportes), el mismo del
 * estado de cuenta, Compromisos y el reporte de cumplimiento. Reglas propias de
 * este reporte, las de siempre:
 * - solo personas activas, ordenadas por nombre;
 * - columnas = categorias de compromiso (las no excluidas de promesas); el
 *   diezmo no entra;
 * - "dado" = lo de sus sobres en cultos del periodo, por mes del culto (los
 *   dolares ya convertidos a colones por el motor);
 * - "esperado" = promesa segun frecuencia (semanal x domingos del mes,
 *   quincenal x 2, mensual x 1), todos los meses del periodo incluido el mes en
 *   curso; si hay dos promesas de la misma categoria vale la ultima;
 * - se omiten las personas sin nada dado ni esperado.
 */
class ReporteGeneralService
{
    public const MESES = PeriodoReporte::NOMBRES_MESES;

    public function __construct(private readonly MotorReportes $motor) {}

    /**
     * @return array{reporteData: array, totalesGenerales: array, categoriasConPromesa: array}
     */
    public function generar(int $anio, int $mesHasta): array
    {
        $personas = Persona::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'activo']);

        $resultado = $this->motor->calcular(PeriodoReporte::deMeses($anio, 1, $mesHasta), $personas);
        $categoriasConPromesa = $resultado->categoriasCompromiso;

        $reporteData = [];

        foreach ($resultado->cuentas() as $cuenta) {
            $datosPersona = [
                'nombre' => $cuenta->nombre,
                'meses' => [],
                'totales' => [
                    'dado' => 0,
                    'esperado' => 0,
                    'diferencia' => 0,
                ],
            ];
            $dadoPersona = 0;
            $esperadoPersona = 0;

            foreach ($resultado->periodo->meses() as $mes) {
                $mesDatos = [
                    'nombre' => $mes->nombre,
                    'categorias' => [],
                    'total_dado' => 0,
                    'total_esperado' => 0,
                    'diferencia' => 0,
                ];
                $dadoMes = 0;
                $esperadoMes = 0;

                foreach ($categoriasConPromesa as $cat) {
                    $dado = $cuenta->dadoCentimos($mes->clave, [$cat]);
                    $esperado = $cuenta->esperadoCentimos($mes->clave, $cat);

                    $mesDatos['categorias'][$cat] = [
                        'dado' => Dinero::colones($dado),
                        'esperado' => Dinero::colones($esperado),
                    ];
                    $dadoMes += $dado;
                    $esperadoMes += $esperado;
                }

                $mesDatos['total_dado'] = Dinero::colones($dadoMes);
                $mesDatos['total_esperado'] = Dinero::colones($esperadoMes);
                $mesDatos['diferencia'] = Dinero::colones($dadoMes - $esperadoMes);
                $datosPersona['meses'][] = $mesDatos;

                $dadoPersona += $dadoMes;
                $esperadoPersona += $esperadoMes;
            }

            $datosPersona['totales'] = [
                'dado' => Dinero::colones($dadoPersona),
                'esperado' => Dinero::colones($esperadoPersona),
                'diferencia' => Dinero::colones($dadoPersona - $esperadoPersona),
            ];

            // Solo personas con algo dado o esperado.
            if ($dadoPersona > 0 || $esperadoPersona > 0) {
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
        // Se suma en centimos para que el gran total cuadre al centimo con la suma de las personas.
        $centimos = [];
        for ($mes = 1; $mes <= $mesHasta; $mes++) {
            $centimos[$mes] = [
                'categorias' => array_fill_keys($categoriasConPromesa, ['dado' => 0, 'esperado' => 0]),
                'total_dado' => 0,
                'total_esperado' => 0,
            ];
        }
        $totalDado = 0;
        $totalEsperado = 0;

        foreach ($reporteData as $persona) {
            foreach ($persona['meses'] as $idx => $mesDatos) {
                $mes = $idx + 1;
                foreach ($categoriasConPromesa as $cat) {
                    $centimos[$mes]['categorias'][$cat]['dado'] += Dinero::centimos($mesDatos['categorias'][$cat]['dado']);
                    $centimos[$mes]['categorias'][$cat]['esperado'] += Dinero::centimos($mesDatos['categorias'][$cat]['esperado']);
                }
                $centimos[$mes]['total_dado'] += Dinero::centimos($mesDatos['total_dado']);
                $centimos[$mes]['total_esperado'] += Dinero::centimos($mesDatos['total_esperado']);
            }
            $totalDado += Dinero::centimos($persona['totales']['dado']);
            $totalEsperado += Dinero::centimos($persona['totales']['esperado']);
        }

        $totalesGenerales = [
            'meses' => [],
            'total_dado' => Dinero::colones($totalDado),
            'total_esperado' => Dinero::colones($totalEsperado),
            'total_diferencia' => Dinero::colones($totalDado - $totalEsperado),
        ];

        foreach ($centimos as $mes => $datos) {
            $totalesGenerales['meses'][$mes] = [
                'categorias' => array_map(fn (array $c) => [
                    'dado' => Dinero::colones($c['dado']),
                    'esperado' => Dinero::colones($c['esperado']),
                ], $datos['categorias']),
                'total_dado' => Dinero::colones($datos['total_dado']),
                'total_esperado' => Dinero::colones($datos['total_esperado']),
                'diferencia' => Dinero::colones($datos['total_dado'] - $datos['total_esperado']),
            ];
        }

        return $totalesGenerales;
    }
}

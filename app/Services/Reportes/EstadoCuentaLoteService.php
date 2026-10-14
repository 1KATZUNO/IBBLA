<?php

namespace App\Services\Reportes;

use App\Services\InformePersonaService;

/**
 * Estados de cuenta de un grupo de personas (HU-R5): el motor calcula a todas en una sola pasada y
 * cada persona sale con las mismas cifras del informe individual (InformePersonaService), que es el
 * que se manda por WhatsApp.
 */
class EstadoCuentaLoteService
{
    /** Tope de personas por lote (PDF y Excel). Todas las activas de una iglesia caben de sobra. */
    public const MAX_PERSONAS = 300;

    public function __construct(
        private readonly MotorReportes $motor,
        private readonly InformePersonaService $informe,
    ) {}

    /**
     * @param  bool  $conDetalle  traer tambien un renglon por monto de cada sobre (hoja Detalle del Excel)
     */
    public function generar(PeriodoReporte $periodo, FiltroPersonas $filtro, bool $conDetalle = false): LoteEstadosCuenta
    {
        $personas = $filtro->personas();
        $total = $personas->count();
        $personas = $personas->take(self::MAX_PERSONAS);

        $resultado = $this->motor->calcular($periodo, $personas, $conDetalle);

        $estados = [];
        foreach ($personas as $persona) {
            $estados[] = $this->informe->datosDeCuenta($resultado->cuenta($persona->id), $resultado, $persona);
        }

        return new LoteEstadosCuenta($periodo, $filtro, $resultado, $estados, $total);
    }
}

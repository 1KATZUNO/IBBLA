<?php

namespace App\Services\Reportes;

use App\Models\TipoCambio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Motor unico de los reportes de dinero por persona: UNA sola forma de calcular, para un periodo,
 * lo dado por categoria y lo esperado por promesa. Lo usan el Reporte General, el estado de cuenta
 * (individual y en lote), el reporte de contribuciones, la pantalla de Compromisos y el reporte de
 * cumplimiento con corte, asi que todos dan el mismo numero.
 *
 * Reglas (las mismas que ya aplicaban esos reportes, ahora en un solo lugar):
 *  - Dado: detalles de los sobres de la persona en cultos con fecha dentro del periodo, por mes del
 *    culto y categoria. Los sobres en dolares se convierten con el tipo de cambio guardado en el
 *    sobre o, si no lo tiene, con el de hoy (antes el Reporte General y el de contribuciones sumaban
 *    los dolares como si fueran colones).
 *  - Esperado: por cada categoria de compromiso (las de la iglesia no excluidas de promesas) vale la
 *    ultima promesa de la persona; semanal x domingos del mes, quincenal x 2, mensual x 1. Las
 *    promesas en dolares se convierten con el tipo de cambio de hoy. Las promesas en categorias
 *    excluidas (p. ej. diezmo) no cuentan, como en el Reporte General.
 *  - Que meses se cobran lo decide el periodo (PeriodoReporte): solo los completos.
 *
 * Costo: una consulta para lo dado y otra para las promesas por cada 1000 personas, sin modelos de
 * Eloquent; ~5.000 detalles de un anio se procesan en milisegundos.
 */
final class MotorReportes
{
    /**
     * @param  iterable<object>  $personas  Personas (modelos o filas con id, nombre y activo), ya
     *                                      filtradas por iglesia. El resultado respeta su orden.
     * @param  bool  $conDetalle  guardar tambien un renglon por detalle de sobre (para el Excel de detalle)
     */
    public function calcular(PeriodoReporte $periodo, iterable $personas, bool $conDetalle = false): ResultadoMotor
    {
        $categorias = tenant_categories()->pluck('nombre', 'slug')->all();
        $deCompromiso = tenant_categories(['excluir_de_promesas' => false])->pluck('slug')->values()->all();
        $tipoCambioHoy = (float) (TipoCambio::hoy()?->venta ?? 0);

        $personasPorId = [];
        foreach ($personas as $persona) {
            $personasPorId[(int) $persona->id] = $persona;
        }

        $acumulado = ['dado' => [], 'ultimo' => [], 'usd' => [], 'detalle' => [], 'promesas' => []];
        foreach (array_chunk(array_keys($personasPorId), 1000) as $ids) {
            $this->leerDado($acumulado, $ids, $periodo, $deCompromiso, $tipoCambioHoy, $conDetalle);
            $this->leerPromesas($acumulado, $ids, $deCompromiso);
        }

        $cuentas = [];
        foreach ($personasPorId as $id => $persona) {
            $promesas = $acumulado['promesas'][$id] ?? [];

            $esperado = [];
            foreach ($promesas as $categoria => $promesa) {
                foreach ($periodo->meses() as $mes) {
                    $esperado[$mes->clave][$categoria] = $promesa->esperadoCentimos($mes->domingos, $tipoCambioHoy);
                }
            }

            $cuentas[$id] = new CuentaPersona(
                $id,
                (string) $persona->nombre,
                (bool) ($persona->activo ?? true),
                $periodo,
                $acumulado['dado'][$id] ?? [],
                $esperado,
                $promesas,
                $deCompromiso,
                isset($acumulado['ultimo'][$id]) ? CarbonImmutable::parse($acumulado['ultimo'][$id]) : null,
                $acumulado['usd'][$id] ?? false,
                $acumulado['detalle'][$id] ?? [],
            );
        }

        return new ResultadoMotor($periodo, $categorias, $deCompromiso, $tipoCambioHoy, $cuentas);
    }

    /**
     * Lo dado, con joins y sin modelos: no depende de los scopes por iglesia (las personas ya vienen
     * filtradas) y sirve tambien sin sesion (enlace publico del informe).
     *
     * @param  list<int>  $ids
     * @param  list<string>  $deCompromiso
     */
    private function leerDado(array &$acumulado, array $ids, PeriodoReporte $periodo, array $deCompromiso, float $tipoCambioHoy, bool $conDetalle): void
    {
        $columnas = ['s.persona_id', 'c.fecha', 'd.categoria', 'd.monto', 's.moneda', 's.tipo_cambio_venta'];
        if ($conDetalle) {
            array_push($columnas, 'c.tipo_culto', 's.numero_sobre', 's.metodo_pago');
        }

        $filas = DB::table('sobre_detalles as d')
            ->join('sobres as s', 's.id', '=', 'd.sobre_id')
            ->join('cultos as c', 'c.id', '=', 's.culto_id')
            ->whereIn('s.persona_id', $ids)
            ->whereBetween('c.fecha', [$periodo->desde->toDateString(), $periodo->hasta->toDateString()])
            ->orderBy('c.fecha')
            ->orderBy('s.id')
            ->orderBy('d.id')
            ->get($columnas);

        $esCompromiso = array_flip($deCompromiso);

        foreach ($filas as $fila) {
            $id = (int) $fila->persona_id;
            $fecha = substr((string) $fila->fecha, 0, 10);
            $clave = substr($fecha, 0, 7);
            $centimos = Dinero::aColones($fila->monto, $fila->moneda, $fila->tipo_cambio_venta, $tipoCambioHoy);

            $acumulado['dado'][$id][$clave][$fila->categoria] = ($acumulado['dado'][$id][$clave][$fila->categoria] ?? 0) + $centimos;

            if ($fila->moneda === 'USD') {
                $acumulado['usd'][$id] = true;
            }

            // Ultimo aporte: el ultimo culto en que dio algo en una categoria de compromiso.
            if ($centimos > 0 && isset($esCompromiso[$fila->categoria])) {
                $acumulado['ultimo'][$id] = $fecha;
            }

            if ($conDetalle) {
                $usd = $fila->moneda === 'USD';
                $acumulado['detalle'][$id][] = [
                    'fecha' => $fecha,
                    'culto' => (string) $fila->tipo_culto,
                    'sobre' => (int) $fila->numero_sobre,
                    'metodo_pago' => (string) $fila->metodo_pago,
                    'categoria' => (string) $fila->categoria,
                    'monto' => (float) $fila->monto,
                    'moneda' => $usd ? 'USD' : 'CRC',
                    'tipo_cambio' => $usd ? ((float) $fila->tipo_cambio_venta > 0 ? (float) $fila->tipo_cambio_venta : ($tipoCambioHoy > 0 ? $tipoCambioHoy : null)) : null,
                    'colones' => Dinero::colones($centimos),
                ];
            }
        }
    }

    /**
     * Promesas vigentes: una por categoria de compromiso (la ultima), en el orden de la iglesia.
     *
     * @param  list<int>  $ids
     * @param  list<string>  $deCompromiso
     */
    private function leerPromesas(array &$acumulado, array $ids, array $deCompromiso): void
    {
        $ultimas = [];
        $filas = DB::table('promesas')
            ->whereIn('persona_id', $ids)
            ->orderBy('id')
            ->get(['persona_id', 'categoria', 'monto', 'moneda', 'frecuencia']);

        foreach ($filas as $fila) {
            $ultimas[(int) $fila->persona_id][$fila->categoria] = $fila;
        }

        foreach ($ultimas as $id => $porCategoria) {
            foreach ($deCompromiso as $categoria) {
                if (isset($porCategoria[$categoria])) {
                    $fila = $porCategoria[$categoria];
                    $acumulado['promesas'][$id][$categoria] = new PromesaVigente(
                        $categoria,
                        (float) $fila->monto,
                        $fila->moneda ?: 'CRC',
                        (string) $fila->frecuencia,
                    );
                }
            }
        }
    }
}

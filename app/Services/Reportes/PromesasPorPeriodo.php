<?php

namespace App\Services\Reportes;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Unico lugar que decide QUE promesas rigen cada mes (promesas futuras, docs/plan-oct-2026/06-PROMESAS-FUTURAS.md).
 *
 * - La "frontera" de una iglesia es el mes de inicio de su ultimo corte de promesas ACTIVADO.
 * - Un mes anterior a la frontera usa las filas de `promesas_historial` cuyo tramo
 *   [vigente_desde, vigente_hasta] lo cubre (si la persona no tiene ninguna, ese mes no tenia promesa).
 * - Un mes desde la frontera (o cualquier mes si la iglesia nunca activo un corte) usa `promesas`.
 *
 * Los tramos siempre van de un dia 1 a un fin de mes, asi que se comparan por clave de mes ("2026-12").
 * Sin cortes activados todo sale de `promesas`, como siempre: una consulta liviana mas por lote
 * (la frontera) y ninguna otra diferencia. Con historial, una consulta mas por lote de personas.
 * No cambia ninguna regla de calculo: solo de donde salen las promesas de cada mes.
 */
final class PromesasPorPeriodo
{
    /**
     * Promesas de un lote de personas para un periodo.
     *
     * - vigentes: persona => categoria => PromesaVigente, de la tabla `promesas` (lo que rige hoy).
     * - porMes: solo para las personas con algun mes del periodo anterior a la frontera de su
     *   iglesia: persona => clave de mes => categoria => PromesaVigente (un mes sin promesas = []).
     *   Las demas personas no aparecen: para ellas todos los meses usan `vigentes`.
     *
     * @param  list<int>  $ids
     * @param  list<string>  $deCompromiso  categorias de compromiso, en el orden de la iglesia
     * @return array{vigentes: array<int, array<string, PromesaVigente>>, porMes: array<int, array<string, array<string, PromesaVigente>>>}
     */
    public function leer(array $ids, PeriodoReporte $periodo, array $deCompromiso): array
    {
        $vigentes = $this->agrupar(
            DB::table('promesas')->whereIn('persona_id', $ids)->orderBy('id')->get(['persona_id', 'categoria', 'monto', 'moneda', 'frecuencia']),
            $deCompromiso,
            fn () => '',
        );
        $vigentes = array_map(fn (array $porTramo) => $porTramo[''], $vigentes);

        $meses = $periodo->meses();
        $primerMes = $meses[0]->clave;
        $fronteras = array_filter($this->fronteras($ids), fn (string $frontera) => $primerMes < $frontera);
        if ($fronteras === []) {
            return ['vigentes' => $vigentes, 'porMes' => []];
        }

        $historial = $this->agrupar(
            DB::table('promesas_historial')
                ->whereIn('persona_id', array_keys($fronteras))
                ->where('vigente_hasta', '>=', $meses[0]->inicio()->toDateString())
                ->orderBy('id')
                ->get(['persona_id', 'categoria', 'monto', 'moneda', 'frecuencia', 'vigente_desde', 'vigente_hasta']),
            $deCompromiso,
            fn (object $fila) => ($fila->vigente_desde === null ? '' : substr((string) $fila->vigente_desde, 0, 7)).'|'.substr((string) $fila->vigente_hasta, 0, 7),
        );

        $porMes = [];
        foreach ($fronteras as $id => $frontera) {
            foreach ($meses as $mes) {
                if ($mes->clave >= $frontera) {
                    $porMes[$id][$mes->clave] = $vigentes[$id] ?? [];

                    continue;
                }
                $porMes[$id][$mes->clave] = [];
                foreach ($historial[$id] ?? [] as $tramo => $promesas) {
                    [$desde, $hasta] = explode('|', $tramo);
                    if (($desde === '' || $desde <= $mes->clave) && $hasta >= $mes->clave) {
                        $porMes[$id][$mes->clave] = $promesas;
                        break;
                    }
                }
            }
        }

        return ['vigentes' => $vigentes, 'porMes' => $porMes];
    }

    /**
     * Mes de inicio ("2027-01") del ultimo corte activado de la iglesia de cada persona; las personas
     * de iglesias sin cortes activados no aparecen.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function fronteras(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $fronteras = [];
        $filas = DB::table('personas as p')
            ->join('cortes_promesas as c', 'c.tenant_id', '=', 'p.tenant_id')
            ->whereIn('p.id', $ids)
            ->whereNotNull('c.activado_at')
            ->groupBy('p.id')
            ->selectRaw('p.id as id, MAX(c.inicia_el) as frontera')
            ->get();
        foreach ($filas as $fila) {
            $fronteras[(int) $fila->id] = substr((string) $fila->frontera, 0, 7);
        }

        return $fronteras;
    }

    /** Fecha de inicio (Y-m-d) del ultimo corte activado de una iglesia, o null si no tiene. */
    public function fronteraDeIglesia(int $tenantId): ?string
    {
        $frontera = DB::table('cortes_promesas')->where('tenant_id', $tenantId)->whereNotNull('activado_at')->max('inicia_el');

        return $frontera === null ? null : substr((string) $frontera, 0, 10);
    }

    /**
     * "Con promesa" en un periodo (selector y filtro de personas): tuvo alguna promesa con monto, en
     * una categoria de compromiso, que rigio en algun mes del periodo. Devuelve null si la iglesia no
     * tiene cortes activados que toquen el periodo (entonces vale la regla de siempre: tener promesa
     * en `promesas`). Si no: la subconsulta de personas con historial en esos meses y si ademas
     * cuentan las promesas de hoy (el periodo llega a la frontera).
     *
     * @param  list<string>  $categorias
     * @return array{historial: Builder, actuales: bool}|null
     */
    public function conPromesaEnPeriodo(int $tenantId, PeriodoReporte $periodo, array $categorias): ?array
    {
        $frontera = $this->fronteraDeIglesia($tenantId);
        $periodo = $periodo->recortadoAHoy();
        $meses = $periodo->meses();
        $inicio = $meses[0]->inicio()->toDateString();
        if ($frontera === null || $inicio >= $frontera) {
            return null;
        }
        $fin = $meses[count($meses) - 1]->fin()->toDateString();

        $historial = DB::table('promesas_historial')
            ->select('persona_id')
            ->where('tenant_id', $tenantId)
            ->whereIn('categoria', $categorias)
            ->where('monto', '>', 0)
            ->where('vigente_hasta', '>=', $inicio)
            ->where(fn ($q) => $q->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', $fin));

        return ['historial' => $historial, 'actuales' => $fin >= $frontera];
    }

    /**
     * Agrupa filas de promesas por persona y tramo, en PromesaVigente por categoria de compromiso
     * (todas las de la categoria, en el orden de la iglesia), igual que siempre.
     *
     * @param  iterable<object>  $filas
     * @param  list<string>  $deCompromiso
     * @param  callable(object): string  $tramo
     * @return array<int, array<string, array<string, PromesaVigente>>>
     */
    private function agrupar(iterable $filas, array $deCompromiso, callable $tramo): array
    {
        $crudo = [];
        foreach ($filas as $fila) {
            $crudo[(int) $fila->persona_id][$tramo($fila)][$fila->categoria][] = [
                'monto' => (float) $fila->monto,
                'moneda' => $fila->moneda ?: 'CRC',
                'frecuencia' => (string) $fila->frecuencia,
            ];
        }

        $resultado = [];
        foreach ($crudo as $id => $porTramo) {
            foreach ($porTramo as $clave => $porCategoria) {
                $resultado[$id][$clave] = [];
                foreach ($deCompromiso as $categoria) {
                    if (isset($porCategoria[$categoria])) {
                        $resultado[$id][$clave][$categoria] = new PromesaVigente($categoria, $porCategoria[$categoria]);
                    }
                }
            }
        }

        return $resultado;
    }
}

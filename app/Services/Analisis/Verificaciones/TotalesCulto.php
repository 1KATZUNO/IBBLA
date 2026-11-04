<?php

namespace App\Services\Analisis\Verificaciones;

use App\Services\Analisis\ContextoAnalisis;
use App\Services\Analisis\Formato;
use App\Services\Analisis\SeccionAnalisis;
use App\Services\Analisis\Verificacion;
use Illuminate\Support\Facades\DB;

/**
 * El total guardado de cada culto (totales_culto) contra lo que suman hoy sus sobres, ofrendas sueltas y
 * egresos. Se recalcula igual que CalculoTotalesCultoService, con el tipo de cambio que el culto guardó
 * al calcular sus totales: así solo se ve la deriva de los datos, no la del tipo de cambio.
 */
final class TotalesCulto implements Verificacion
{
    public function ejecutar(ContextoAnalisis $ctx): SeccionAnalisis
    {
        $cultos = $ctx->cultos()->leftJoin('totales_culto as t', 't.culto_id', '=', 'c.id')
            ->orderBy('c.fecha')->get(['c.id', 'c.fecha', 'c.tipo_culto', 'c.cerrado', 't.id as totales_id', 't.total_general', 't.cantidad_sobres', 't.tipo_cambio_venta as tc']);

        $ids = $cultos->pluck('id')->all();
        $porMoneda = fn (string $tabla) => $ids === [] ? collect() : DB::table($tabla)->whereIn('culto_id', $ids)
            ->selectRaw('culto_id, moneda, SUM(monto) as total')->groupBy('culto_id', 'moneda')->get()->groupBy('culto_id');
        $sueltas = $porMoneda('ofrenda_suelta');
        $egresos = $porMoneda('egresos');
        $sobres = $ids === [] ? collect() : DB::table('sobres as s')->leftJoin('sobre_detalles as d', 'd.sobre_id', '=', 's.id')->whereIn('s.culto_id', $ids)
            ->selectRaw('s.culto_id, s.moneda, COUNT(DISTINCT s.id) as sobres, COALESCE(SUM(d.monto), 0) as total')->groupBy('s.culto_id', 's.moneda')->get()->groupBy('culto_id');

        $suma = fn ($grupo, string $moneda, string $campo = 'total') => (float) ($grupo?->firstWhere('moneda', $moneda)?->{$campo} ?? 0);

        $filas = [];
        $sinTotales = [];
        $sinTc = [];
        $difNeta = 0.0;

        foreach ($cultos as $culto) {
            $delCulto = $sobres->get($culto->id);
            $nSobres = (int) ($delCulto?->sum('sobres') ?? 0);
            $usd = $suma($delCulto, 'USD') + $suma($sueltas->get($culto->id), 'USD') - $suma($egresos->get($culto->id), 'USD');
            $crc = $suma($delCulto, 'CRC') + $suma($sueltas->get($culto->id), 'CRC') - $suma($egresos->get($culto->id), 'CRC');
            $etiqueta = Formato::fecha($culto->fecha).' '.$culto->tipo_culto;

            if ($culto->totales_id === null) {
                if ($nSobres > 0 || $crc != 0.0 || $usd != 0.0) {
                    $sinTotales[] = [$etiqueta, $nSobres, Formato::crc($crc), Formato::usd($usd)];
                }

                continue;
            }

            $tc = (float) $culto->tc;
            if ($usd != 0.0 && $tc <= 0) {
                $sinTc[] = [$etiqueta, Formato::usd($usd), 'los dólares no entran al total del culto'];
            }
            $esperado = round($crc + ($tc > 0 ? $usd * $tc : 0), 2);
            $dif = round($esperado - (float) $culto->total_general, 2);
            // Se redondea por categoría al guardar, así que unos pocos colones de diferencia con USD son esperables.
            $margen = $usd != 0.0 ? max($ctx->tolerancia, 1.0) : $ctx->tolerancia;

            if (abs($dif) > $margen || $nSobres !== (int) $culto->cantidad_sobres) {
                $difNeta += $dif;
                $filas[] = ['abs' => abs($dif), 'fila' => [
                    $etiqueta, $culto->cerrado ? 'cerrado' : 'abierto', Formato::crc($culto->total_general), Formato::crc($esperado), Formato::diferencia($dif),
                    (int) $culto->cantidad_sobres.' / '.$nSobres,
                ]];
            }
        }

        usort($filas, fn ($a, $b) => $b['abs'] <=> $a['abs']);

        return new SeccionAnalisis(
            'totales-culto', 'Totales de cada culto contra sus sobres',
            'Que el total guardado del culto (el que se ve en Recuento y en los reportes) sea lo que suman hoy sus sobres, ofrendas sueltas y egresos.',
            $cultos->count(), count($filas) + count($sinTotales) + count($sinTc),
            SeccionAnalisis::ALERTA,
            [
                SeccionAnalisis::tabla('Cultos con total guardado distinto del recalculado', ['Culto', 'Estado', 'Total guardado', 'Total recalculado', 'Diferencia', 'Sobres guardados / reales'], array_column($filas, 'fila'), $ctx->limite, 'Ordenados por la diferencia más grande. «Sobres guardados / reales» distintos también cuentan como hallazgo.'),
                SeccionAnalisis::tabla('Cultos con movimiento pero sin fila de totales', ['Culto', 'Sobres', 'Colones', 'Dólares'], $sinTotales, $ctx->limite, 'Los reportes que leen totales_culto los muestran en cero.'),
                SeccionAnalisis::tabla('Cultos con dólares y sin tipo de cambio en sus totales', ['Culto', 'Dólares', 'Efecto'], $sinTc, $ctx->limite),
            ],
            ['Cultos revisados' => (string) $cultos->count(), 'Cultos con total distinto' => (string) count($filas), 'Diferencia neta (recalculado - guardado)' => Formato::diferencia($difNeta)],
            'Guardar de nuevo cualquier sobre del culto afectado (o cerrar y reabrir el culto) vuelve a calcular sus totales con CalculoTotalesCultoService.',
        );
    }
}

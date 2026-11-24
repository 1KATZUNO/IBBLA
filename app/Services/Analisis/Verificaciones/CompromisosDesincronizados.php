<?php

namespace App\Services\Analisis\Verificaciones;

use App\Services\Analisis\ContextoAnalisis;
use App\Services\Analisis\Formato;
use App\Services\Analisis\SeccionAnalisis;
use App\Services\Analisis\Verificacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * La tabla compromisos es una copia calculada (hoy la escriben las pantallas al abrirse). Se compara con lo que
 * dicen los sobres y las promesas: lo dado, lo prometido, promesas sin fila y filas de categorías sin promesa.
 * Solo se miran meses en los que hubo cultos, que son los que la app calcula.
 */
final class CompromisosDesincronizados implements Verificacion
{
    public function ejecutar(ContextoAnalisis $ctx): SeccionAnalisis
    {
        $anio = $ctx->anioEstadoCuenta;
        $tc = $ctx->tipoCambioReciente();

        $mesActual = $ctx->hoy->year === $anio ? $ctx->hoy->month : ($ctx->hoy->year > $anio ? 12 : 0);
        $mesesConCultos = $ctx->cultos()->whereBetween('c.fecha', [$anio.'-01-01', $anio.'-12-31'])->pluck('c.fecha')
            ->map(fn ($f) => (int) Carbon::parse($f)->format('n'))->filter(fn ($m) => $m <= $mesActual)->unique()->sort()->values()->all();
        // El mes en curso no se cobra: su fila puede no existir todavía sin que sea un fallo.
        $mesesCompletos = array_values(array_filter($mesesConCultos, fn ($m) => $ctx->hoy->year > $anio || $m < $ctx->hoy->month));

        $promesas = DB::table('promesas as p')->join('personas as pe', 'pe.id', '=', 'p.persona_id')->where('pe.tenant_id', $ctx->iglesia->id)
            ->get(['p.persona_id', 'p.categoria', 'p.monto', 'p.moneda', 'p.frecuencia']);
        $compromisos = DB::table('compromisos as k')->join('personas as pe', 'pe.id', '=', 'k.persona_id')->where('pe.tenant_id', $ctx->iglesia->id)
            ->where('k.año', $anio)->get(['k.persona_id', 'k.categoria', 'k.mes', 'k.monto_prometido', 'k.monto_prometido_crc', 'k.monto_dado_crc', 'k.moneda_promesa', 'k.tipo_cambio_usado']);

        $filasPorClave = [];
        foreach ($compromisos as $k) {
            $filasPorClave[$k->persona_id.'|'.mb_strtolower($k->categoria).'|'.$k->mes] = $k;
        }
        $porCategoria = $promesas->groupBy(fn ($p) => $p->persona_id.'|'.mb_strtolower($p->categoria));
        $categoriasPrometidas = $porCategoria->map(fn () => true)->all();

        $dadoViejo = [];
        $dadoNeto = 0.0;
        foreach ($compromisos as $k) {
            if (! in_array((int) $k->mes, $mesesConCultos, true)) {
                continue;
            }
            $real = round($ctx->dadoAnual()[(int) $k->persona_id][mb_strtolower($k->categoria)][(int) $k->mes] ?? 0, 2);
            if ($ctx->difiere($real, (float) $k->monto_dado_crc)) {
                $dadoNeto += $real - (float) $k->monto_dado_crc;
                $dadoViejo[] = ['abs' => abs($real - (float) $k->monto_dado_crc), 'fila' => [
                    $ctx->nombrePersona((int) $k->persona_id), $k->categoria, sprintf('%02d/%d', $k->mes, $anio), Formato::crc($k->monto_dado_crc), Formato::crc($real), Formato::diferencia($real - (float) $k->monto_dado_crc),
                ]];
            }
        }
        usort($dadoViejo, fn ($a, $b) => $b['abs'] <=> $a['abs']);

        // «Prometido»: una persona puede tener varias promesas en la misma categoría (por ejemplo una quincenal y una
        // semanal) y la tabla guarda una sola fila por categoría y mes: se compara contra la suma de todas.
        $prometidoViejo = [];
        $sinFila = [];
        $multiples = 0;
        foreach ($porCategoria as $clave => $grupo) {
            [$persona, $categoria] = explode('|', $clave);
            $multiples += $grupo->count() > 1 ? 1 : 0;
            foreach ($mesesConCultos as $mes) {
                $k = $filasPorClave[$clave.'|'.$mes] ?? null;
                if ($k === null) {
                    if (in_array($mes, $mesesCompletos, true)) {
                        $sinFila[$clave][] = $mes;
                    }

                    continue;
                }
                $tcFila = (float) $k->tipo_cambio_usado > 0 ? (float) $k->tipo_cambio_usado : $tc;
                $esperado = 0.0;
                foreach ($grupo as $p) {
                    $esperado += $ctx->aColones($this->esperadoMes((float) $p->monto, (string) $p->frecuencia, $anio, $mes), $p->moneda, 0.0, $tcFila);
                }
                if (abs(round($esperado, 2) - (float) $k->monto_prometido_crc) > max($ctx->tolerancia, 1.0)) {
                    $prometidoViejo[] = [$ctx->nombrePersona((int) $persona), $categoria, sprintf('%02d/%d', $mes, $anio), Formato::crc($k->monto_prometido_crc),
                        Formato::crc($esperado).($grupo->count() > 1 ? ' ('.$grupo->count().' promesas)' : '')];
                }
            }
        }

        $filasSinFila = [];
        foreach ($sinFila as $clave => $meses) {
            [$persona, $categoria] = explode('|', $clave);
            $filasSinFila[] = [$ctx->nombrePersona((int) $persona), $categoria, count($meses), implode(', ', $meses)];
        }

        $huerfanos = [];
        foreach ($compromisos as $k) {
            if (! isset($categoriasPrometidas[$k->persona_id.'|'.mb_strtolower($k->categoria)]) && in_array((int) $k->mes, $mesesConCultos, true)) {
                $huerfanos[] = [$ctx->nombrePersona((int) $k->persona_id), $k->categoria, sprintf('%02d/%d', $k->mes, $anio), Formato::crc($k->monto_prometido_crc)];
            }
        }

        return new SeccionAnalisis(
            'compromisos', "Compromisos desincronizados ({$anio})",
            'Que la tabla compromisos (copia calculada) coincida con lo que dicen hoy los sobres y las promesas en los meses con cultos.',
            $compromisos->count(), count($dadoViejo) + count($prometidoViejo) + count($filasSinFila) + count($huerfanos),
            SeccionAnalisis::ALERTA,
            [
                SeccionAnalisis::tabla('Filas cuyo «dado» ya no coincide con los sobres', ['Persona', 'Categoría', 'Mes', 'Guardado', 'Según los sobres', 'Diferencia'], array_column($dadoViejo, 'fila'), $ctx->limite, 'Pasa cuando se edita o borra un sobre después de que se calculó el compromiso.'),
                SeccionAnalisis::tabla('Filas cuyo «prometido» no coincide con la promesa actual', ['Persona', 'Categoría', 'Mes', 'Guardado', 'Según la promesa'], $prometidoViejo, $ctx->limite, 'Pasa cuando se cambia una promesa y nadie vuelve a abrir el compromiso, o cuando la persona tiene dos promesas en la misma categoría: la pantalla de compromisos guarda una sola fila por categoría y mes, y la última promesa pisa a la anterior.'),
                SeccionAnalisis::tabla('Promesas sin fila de compromiso en meses completos con cultos', ['Persona', 'Categoría', 'Meses sin fila', 'Cuáles'], $filasSinFila, $ctx->limite, 'Las pantallas las crean al abrirse; los reportes que leen la tabla las ven en cero hasta entonces.'),
                SeccionAnalisis::tabla('Filas de categorías que la persona ya no promete', ['Persona', 'Categoría', 'Mes', 'Prometido (colones)'], $huerfanos, $ctx->limite, 'Suman al «prometido» de los reportes aunque la promesa se haya borrado.'),
            ],
            [
                'Filas de compromiso del año' => (string) $compromisos->count(),
                'Meses con cultos en el año' => (string) count($mesesConCultos),
                'Filas con dado distinto' => (string) count($dadoViejo),
                'Diferencia neta del dado (sobres - guardado)' => Formato::diferencia($dadoNeto),
                'Filas con prometido distinto' => (string) count($prometidoViejo),
                'Categorías con más de una promesa' => (string) $multiples,
                'Promesas con meses sin fila' => (string) count($filasSinFila),
                'Filas huérfanas' => (string) count($huerfanos),
                'Tipo de cambio más reciente' => $tc > 0 ? number_format($tc, 2) : 'ninguno',
            ],
            'Recalcular los compromisos (el botón «Recalcular» de Compromisos o, cuando exista, php artisan compromisos:sincronizar) y volver a correr este análisis. Lo que siga apareciendo es un dato mal cargado o una persona con dos promesas en la misma categoría, que hoy se pisan: conviene dejar una sola promesa por categoría.',
        );
    }

    private function esperadoMes(float $monto, string $frecuencia, int $anio, int $mes): float
    {
        return match ($frecuencia) {
            'semanal' => $monto * $this->domingos($anio, $mes),
            'quincenal' => $monto * 2,
            default => $monto,
        };
    }

    private function domingos(int $anio, int $mes): int
    {
        $dia = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = $dia->copy()->endOfMonth();
        $n = 0;
        for (; $dia->lte($fin); $dia->addDay()) {
            $n += $dia->isSunday() ? 1 : 0;
        }

        return $n;
    }
}

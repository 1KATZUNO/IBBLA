<?php

namespace App\Services\Analisis\Verificaciones;

use App\Services\Analisis\ContextoAnalisis;
use App\Services\Analisis\Formato;
use App\Services\Analisis\SeccionAnalisis;
use App\Services\Analisis\Verificacion;
use Illuminate\Support\Facades\DB;

/**
 * El total de cada sobre contra la suma de sus detalles. Los totales del culto y los estados de cuenta
 * se calculan con los detalles, y la ficha de la persona suma total_declarado: si no coinciden, cada
 * pantalla cuenta una historia distinta.
 */
final class CuadreSobresDetalles implements Verificacion
{
    public function ejecutar(ContextoAnalisis $ctx): SeccionAnalisis
    {
        $sobres = $ctx->sobres()
            ->leftJoin('sobre_detalles as d', 'd.sobre_id', '=', 's.id')
            ->groupBy('s.id', 's.numero_sobre', 's.persona_id', 's.moneda', 's.total_declarado', 'c.id', 'c.fecha')
            ->orderBy('c.fecha')->orderBy('s.numero_sobre')
            ->get([
                's.id', 's.numero_sobre', 's.persona_id', 's.moneda', 's.total_declarado', 'c.id as culto_id', 'c.fecha',
                DB::raw('COUNT(d.id) as n_detalles'), DB::raw('COALESCE(SUM(d.monto), 0) as suma_detalles'),
            ]);

        $descuadres = [];
        $vacios = [];
        $netas = ['CRC' => 0.0, 'USD' => 0.0];
        $declarado = ['CRC' => 0.0, 'USD' => 0.0];
        $detallado = ['CRC' => 0.0, 'USD' => 0.0];

        foreach ($sobres as $s) {
            $moneda = $s->moneda === 'USD' ? 'USD' : 'CRC';
            $dif = round((float) $s->total_declarado - (float) $s->suma_detalles, 2);
            $declarado[$moneda] += (float) $s->total_declarado;
            $detallado[$moneda] += (float) $s->suma_detalles;

            if ($ctx->difiere((float) $s->total_declarado, (float) $s->suma_detalles)) {
                $netas[$moneda] += $dif;
                $descuadres[] = ['abs' => abs($dif), 'fila' => [
                    Formato::fecha($s->fecha), '#'.$s->numero_sobre, $ctx->nombrePersona($s->persona_id ? (int) $s->persona_id : null), $s->moneda,
                    Formato::monto($s->total_declarado, $s->moneda), Formato::monto($s->suma_detalles, $s->moneda),
                    Formato::diferencia($dif, $s->moneda), (int) $s->n_detalles === 0 ? 'sin detalles' : (int) $s->n_detalles,
                ]];
            } elseif ((int) $s->n_detalles === 0) {
                $vacios[] = [Formato::fecha($s->fecha), '#'.$s->numero_sobre, $ctx->nombrePersona($s->persona_id ? (int) $s->persona_id : null), $s->moneda];
            }
        }

        usort($descuadres, fn ($a, $b) => $b['abs'] <=> $a['abs']);
        $filasDescuadre = array_column($descuadres, 'fila');

        $malos = $ctx->sobres()->join('sobre_detalles as d', 'd.sobre_id', '=', 's.id')->where('d.monto', '<=', 0)
            ->orderBy('c.fecha')->get(['s.numero_sobre', 's.persona_id', 's.moneda', 'c.fecha', 'd.categoria', 'd.monto'])
            ->map(fn ($r) => [Formato::fecha($r->fecha), '#'.$r->numero_sobre, $ctx->nombrePersona($r->persona_id ? (int) $r->persona_id : null), $r->categoria, Formato::monto($r->monto, $r->moneda)])->all();

        $desconocidas = [];
        if ($ctx->categorias() !== []) {
            $desconocidas = $ctx->sobres()->join('sobre_detalles as d', 'd.sobre_id', '=', 's.id')
                ->groupBy(DB::raw('LOWER(d.categoria)'))
                ->selectRaw('LOWER(d.categoria) as categoria, COUNT(*) as veces, SUM(d.monto) as total')->get()
                ->filter(fn ($r) => ! in_array((string) $r->categoria, $ctx->categorias(), true))
                ->map(fn ($r) => [$r->categoria, (int) $r->veces, number_format((float) $r->total, 2)])->values()->all();
        }

        $subsecciones = [
            SeccionAnalisis::tabla('Sobres cuyo total no es la suma de sus detalles', ['Culto', 'Sobre', 'Persona', 'Moneda', 'Total declarado', 'Suma de detalles', 'Diferencia', 'Detalles'], $filasDescuadre, $ctx->limite, 'Ordenados por la diferencia más grande.'),
            SeccionAnalisis::tabla('Detalles con monto en cero o negativo', ['Culto', 'Sobre', 'Persona', 'Categoría', 'Monto'], $malos, $ctx->limite),
            SeccionAnalisis::tabla('Categorías de los detalles que la iglesia no tiene configuradas', ['Categoría', 'Detalles', 'Monto sumado (sin convertir)'], $desconocidas, $ctx->limite, 'Esos montos igual cuentan en los totales del culto, pero no salen en las columnas de los reportes por categoría.'),
            SeccionAnalisis::tabla('Sobres vacíos (en cero y sin detalles)', ['Culto', 'Sobre', 'Persona', 'Moneda'], $vacios, $ctx->limite, 'No son un descuadre, pero ocupan un número de sobre.'),
        ];

        return new SeccionAnalisis(
            'sobres-detalles', 'Sobres contra sus detalles',
            'Que el total de cada sobre sea la suma de sus detalles (diezmo, misiones...) y que los detalles tengan montos y categorías válidos.',
            $sobres->count(), count($descuadres) + count($malos) + count($desconocidas),
            SeccionAnalisis::ALERTA, $subsecciones,
            [
                'Sobres revisados' => (string) $sobres->count(),
                'Sobres con total distinto de sus detalles' => (string) count($descuadres),
                'Diferencia neta en colones (declarado - detalles)' => Formato::diferencia($netas['CRC']),
                'Diferencia neta en dólares (declarado - detalles)' => Formato::diferencia($netas['USD'], 'USD'),
                'Total declarado / detallado (colones)' => Formato::crc($declarado['CRC']).' / '.Formato::crc($detallado['CRC']),
                'Total declarado / detallado (dólares)' => Formato::usd($declarado['USD']).' / '.Formato::usd($detallado['USD']),
            ],
            'Revisar cada sobre en Recuento. Los totales del culto y los estados de cuenta salen de los detalles, y la ficha de la persona suma el total declarado: '
            .'si lo declarado es lo correcto hay que corregir los detalles; si no, el total. Referencia, sin ejecutar: '
            .'UPDATE sobres SET total_declarado = (SELECT COALESCE(SUM(monto), 0) FROM sobre_detalles WHERE sobre_id = sobres.id) WHERE id IN (...); '
            .'y después recalcular los totales de los cultos afectados (guardar de nuevo cualquier sobre del culto los recalcula).',
        );
    }
}

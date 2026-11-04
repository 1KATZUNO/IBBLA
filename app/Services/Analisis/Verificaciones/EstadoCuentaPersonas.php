<?php

namespace App\Services\Analisis\Verificaciones;

use App\Services\Analisis\ContextoAnalisis;
use App\Services\Analisis\Formato;
use App\Services\Analisis\SeccionAnalisis;
use App\Services\Analisis\Verificacion;
use Illuminate\Support\Facades\DB;

/**
 * El estado de cuenta de cada persona (lo que suman los detalles de sus sobres en el año, que es lo que muestran
 * su informe y la pantalla de compromisos) contra la suma del total declarado de esos mismos sobres (lo que
 * suma su ficha). Todo en colones, con la regla de la app para los dólares.
 */
final class EstadoCuentaPersonas implements Verificacion
{
    public function ejecutar(ContextoAnalisis $ctx): SeccionAnalisis
    {
        $tcReciente = $ctx->tipoCambioReciente();

        $declarado = [];
        $sobresPorPersona = [];
        $sobres = DB::table('sobres as s')->join('cultos as c', 'c.id', '=', 's.culto_id')
            ->where('c.tenant_id', $ctx->iglesia->id)->whereNotNull('s.persona_id')
            ->whereBetween('c.fecha', [$ctx->anioEstadoCuenta.'-01-01', $ctx->anioEstadoCuenta.'-12-31'])
            ->get(['s.persona_id', 's.moneda', 's.total_declarado', 's.tipo_cambio_venta']);

        foreach ($sobres as $s) {
            $persona = (int) $s->persona_id;
            $declarado[$persona] = ($declarado[$persona] ?? 0) + $ctx->aColones((float) $s->total_declarado, $s->moneda, (float) $s->tipo_cambio_venta, $tcReciente);
            $sobresPorPersona[$persona] = ($sobresPorPersona[$persona] ?? 0) + 1;
        }

        $filas = [];
        $totalDado = 0.0;
        $totalDeclarado = 0.0;
        foreach ($declarado as $persona => $monto) {
            $dado = 0.0;
            foreach ($ctx->dadoAnual()[$persona] ?? [] as $meses) {
                $dado += array_sum($meses);
            }
            $totalDado += $dado;
            $totalDeclarado += $monto;
            if ($ctx->difiere($dado, $monto)) {
                $filas[] = ['abs' => abs($monto - $dado), 'fila' => [
                    $ctx->nombrePersona($persona), $sobresPorPersona[$persona], Formato::crc($dado), Formato::crc($monto), Formato::diferencia($monto - $dado),
                ]];
            }
        }
        usort($filas, fn ($a, $b) => $b['abs'] <=> $a['abs']);

        return new SeccionAnalisis(
            'estado-cuenta', "Estado de cuenta de cada persona contra sus sobres ({$ctx->anioEstadoCuenta})",
            'Que lo que suma el estado de cuenta (detalles de los sobres) sea lo mismo que lo declarado en esos sobres.',
            count($declarado), count($filas),
            SeccionAnalisis::ALERTA,
            [SeccionAnalisis::tabla('Personas cuyo estado de cuenta no coincide con sus sobres', ['Persona', 'Sobres', 'Estado de cuenta (detalles)', 'Declarado en sus sobres', 'Diferencia'], array_column($filas, 'fila'), $ctx->limite, 'Ordenadas por la diferencia más grande. Casi siempre viene de un sobre cuyo total no es la suma de sus detalles (ver la sección de sobres contra detalles).')],
            [
                'Personas con sobres en el año' => (string) count($declarado),
                'Personas con diferencia' => (string) count($filas),
                'Total de estados de cuenta (colones)' => Formato::crc($totalDado),
                'Total declarado en sus sobres (colones)' => Formato::crc($totalDeclarado),
            ],
            'Corregir los sobres que descuadran; el estado de cuenta de cada persona se arregla solo porque se calcula en el momento.',
        );
    }
}

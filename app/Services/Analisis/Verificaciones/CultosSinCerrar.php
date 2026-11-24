<?php

namespace App\Services\Analisis\Verificaciones;

use App\Services\Analisis\ContextoAnalisis;
use App\Services\Analisis\Formato;
use App\Services\Analisis\SeccionAnalisis;
use App\Services\Analisis\Verificacion;

/** Cultos con sobres que siguen abiertos pasados unos días, y cultos cerrados con dólares sin tipo de cambio congelado. */
final class CultosSinCerrar implements Verificacion
{
    public function ejecutar(ContextoAnalisis $ctx): SeccionAnalisis
    {
        $limite = $ctx->hoy->copy()->subDays($ctx->diasSinCerrar)->toDateString();
        $hoy = $ctx->hoy->toDateString();

        $cultos = $ctx->cultos()->leftJoin('sobres as s', 's.culto_id', '=', 'c.id')
            ->groupBy('c.id', 'c.fecha', 'c.tipo_culto', 'c.cerrado', 'c.tipo_cambio_venta')->orderBy('c.fecha')
            ->selectRaw("c.id, c.fecha, c.tipo_culto, c.cerrado, c.tipo_cambio_venta, COUNT(s.id) as sobres, SUM(CASE WHEN s.moneda = 'USD' THEN 1 ELSE 0 END) as sobres_usd")->get();

        $viejos = [];
        $futurosAbiertos = 0;
        $sinCongelar = [];
        foreach ($cultos as $c) {
            $fecha = (string) $c->fecha;
            if (! $c->cerrado && (int) $c->sobres > 0 && $fecha < $limite) {
                $viejos[] = [Formato::fecha($fecha), $c->tipo_culto, (int) $c->sobres, (int) $ctx->hoy->diffInDays($fecha, true).' días'];
            }
            if (! $c->cerrado && $fecha > $hoy) {
                $futurosAbiertos++;
            }
            if ($c->cerrado && (int) $c->sobres_usd > 0 && (float) $c->tipo_cambio_venta <= 0) {
                $sinCongelar[] = [Formato::fecha($fecha), $c->tipo_culto, (int) $c->sobres_usd];
            }
        }

        return new SeccionAnalisis(
            'cultos-sin-cerrar', 'Cultos sin cerrar',
            "Cultos con sobres que siguen abiertos {$ctx->diasSinCerrar} días después de su fecha, y cultos cerrados con dólares pero sin tipo de cambio congelado.",
            $cultos->count(), count($viejos) + count($sinCongelar),
            SeccionAnalisis::AVISO,
            [
                SeccionAnalisis::tabla("Cultos con sobres y más de {$ctx->diasSinCerrar} días sin cerrar", ['Culto', 'Tipo', 'Sobres', 'Antigüedad'], $viejos, $ctx->limite, 'Un culto abierto sigue usando el tipo de cambio de hoy y sus sobres se pueden editar.'),
                SeccionAnalisis::tabla('Cultos cerrados con dólares y sin tipo de cambio congelado', ['Culto', 'Tipo', 'Sobres en USD'], $sinCongelar, $ctx->limite),
            ],
            ['Cultos revisados' => (string) $cultos->count(), 'Abiertos con sobres y fecha vieja' => (string) count($viejos), 'Cultos futuros aún abiertos (normal)' => (string) $futurosAbiertos],
            'Revisar el recuento de cada culto viejo y cerrarlo (con las firmas) para congelar su tipo de cambio.',
        );
    }
}

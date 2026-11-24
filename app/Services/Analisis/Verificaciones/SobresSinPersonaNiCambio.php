<?php

namespace App\Services\Analisis\Verificaciones;

use App\Services\Analisis\ContextoAnalisis;
use App\Services\Analisis\Formato;
use App\Services\Analisis\SeccionAnalisis;
use App\Services\Analisis\Verificacion;

/**
 * Dos huecos de datos: sobres sin persona (no suman a ningún estado de cuenta) y sobres en dólares sin tipo
 * de cambio guardado (se convierten con el de la fecha del culto o el de hoy, y su valor en colones cambia).
 */
final class SobresSinPersonaNiCambio implements Verificacion
{
    public function ejecutar(ContextoAnalisis $ctx): SeccionAnalisis
    {
        $sobres = $ctx->sobres()->orderBy('c.fecha')->orderBy('s.numero_sobre')
            ->get(['s.id', 's.numero_sobre', 's.persona_id', 's.moneda', 's.total_declarado', 's.tipo_cambio_venta', 'c.fecha']);

        $anonimos = $sobres->whereNull('persona_id');
        $porCulto = [];
        $totalAnonimo = ['CRC' => 0.0, 'USD' => 0.0];
        foreach ($anonimos as $s) {
            $moneda = $s->moneda === 'USD' ? 'USD' : 'CRC';
            $porCulto[$s->fecha]['n'] = ($porCulto[$s->fecha]['n'] ?? 0) + 1;
            $porCulto[$s->fecha][$moneda] = ($porCulto[$s->fecha][$moneda] ?? 0) + (float) $s->total_declarado;
            $totalAnonimo[$moneda] += (float) $s->total_declarado;
        }
        $filasAnonimos = [];
        foreach ($porCulto as $fecha => $x) {
            $filasAnonimos[] = [Formato::fecha($fecha), $x['n'], Formato::crc($x['CRC'] ?? 0), Formato::usd($x['USD'] ?? 0)];
        }

        $usdSinCambio = $sobres->filter(fn ($s) => $s->moneda === 'USD' && (float) $s->tipo_cambio_venta <= 0);
        $reciente = $ctx->tipoCambioReciente();
        $filasUsd = [];
        $sinNingunCambio = 0;
        foreach ($usdSinCambio as $s) {
            $delDia = $ctx->tipoCambioDe((string) $s->fecha);
            $sinNingunCambio += $delDia === null && $reciente <= 0 ? 1 : 0;
            $filasUsd[] = [Formato::fecha($s->fecha), '#'.$s->numero_sobre, $ctx->nombrePersona($s->persona_id ? (int) $s->persona_id : null), Formato::usd($s->total_declarado),
                $delDia !== null ? 'el del día del culto ('.number_format($delDia, 2).')' : ($reciente > 0 ? 'el más reciente ('.number_format($reciente, 2).')' : 'ninguno: se cuenta sin convertir')];
        }

        return new SeccionAnalisis(
            'sin-persona-ni-cambio', 'Sobres sin persona y dólares sin tipo de cambio',
            'Sobres que no suman a ninguna persona y sobres en USD que no guardaron el tipo de cambio con el que se recibieron.',
            $sobres->count(), $anonimos->count() + $usdSinCambio->count(),
            SeccionAnalisis::AVISO,
            [
                SeccionAnalisis::tabla('Sobres sin persona, por culto', ['Culto', 'Sobres', 'Colones', 'Dólares'], $filasAnonimos, $ctx->limite, 'Cuentan en el total del culto pero en ningún estado de cuenta. Son normales si el aporte fue anónimo.'),
                SeccionAnalisis::tabla('Sobres en dólares sin tipo de cambio guardado', ['Culto', 'Sobre', 'Persona', 'Monto', 'Se convierte con'], $filasUsd, $ctx->limite, 'Su valor en colones se mueve cada vez que cambia el tipo de cambio.'),
            ],
            [
                'Sobres sin persona' => (string) $anonimos->count(),
                'Monto sin persona (colones / dólares)' => Formato::crc($totalAnonimo['CRC']).' / '.Formato::usd($totalAnonimo['USD']),
                'Sobres en USD sin tipo de cambio' => (string) $usdSinCambio->count(),
                'De ellos, sin ningún tipo de cambio disponible' => (string) $sinNingunCambio,
                'Tipo de cambio más reciente registrado' => $reciente > 0 ? number_format($reciente, 2) : 'ninguno',
            ],
            'Asignar la persona en Recuento cuando se sepa quién dio. Para los dólares, guardar el tipo de cambio en el sobre (el del día del culto) para que sus colones dejen de moverse.',
        );
    }
}

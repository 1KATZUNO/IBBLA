<?php

namespace App\Services\Reportes;

use App\Models\Persona;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Totales de promesas de la iglesia (o de un grupo) por categoria y por mes, para el Dashboard de
 * promesas y el Reporte de promesas (QE-008). No calcula nada por su cuenta: suma las cuentas del
 * motor unico, con su misma regla:
 *
 *  - Prometido = lo de los meses COMPLETOS antes del corte (los que se cobran). Es la suma del
 *    "le correspondía" del Cumplimiento de promesas para las mismas personas y el mismo corte.
 *  - Mes en curso = lo prometido del mes del corte, aparte: todavia no se cobra, asi que no entra en
 *    el faltante ni en el porcentaje.
 *  - Dado = lo que cada persona dio en las categorias que prometio, hasta el corte (incluido el mes en
 *    curso): la suma del "ha dado" del Cumplimiento.
 *
 * Personas: las activas con promesa (las mismas del Cumplimiento con "Con promesa"), o las que se
 * pasen (el reporte por clase).
 */
final class ResumenPromesasService
{
    public function __construct(private readonly MotorReportes $motor) {}

    /**
     * @param  iterable<object>|null  $personas  null = activas con promesa
     */
    public function generar(PeriodoReporte $periodo, ?iterable $personas = null, ?string $categoria = null): ResumenPromesas
    {
        $hoy = CarbonImmutable::today();
        if ($periodo->desde->gt($hoy)) {
            return ResumenPromesas::vacio($periodo);
        }

        $personas ??= FiltroPersonas::desde(['grupo' => FiltroPersonas::CON_PROMESA])->personas($periodo);
        $resultado = $this->motor->calcular($periodo, $personas);
        $periodo = $resultado->periodo;

        $porCategoria = [];
        $porMes = [];
        foreach ($periodo->meses() as $mes) {
            $porMes[$mes->clave] = ['mes' => $mes, 'prometido' => 0, 'dado' => 0];
        }
        $porPersona = [];

        foreach ($resultado->cuentas() as $cuenta) {
            $categorias = $cuenta->categoriasConPromesa();
            if ($categoria !== null) {
                $categorias = array_values(array_intersect($categorias, [$categoria]));
            }
            if ($categorias === []) {
                continue;
            }

            // Lo dado de cada mes cuenta en las categorias que prometio ESE mes (igual que "ha dado" en
            // Cumplimiento); sin cortes de promesas en el periodo son las mismas todos los meses.
            $prometioEnMes = [];
            foreach ($periodo->meses() as $mes) {
                $prometioEnMes[$mes->clave] = array_flip($cuenta->categoriasConPromesa($mes->clave));
            }

            $prometidoPersona = 0;
            $dadoPersona = 0;
            foreach ($categorias as $cat) {
                $fila = $porCategoria[$cat] ?? ['slug' => $cat, 'nombre' => $resultado->nombreCategoria($cat), 'prometido' => 0, 'en_curso' => 0, 'dado' => 0, 'dado_en_curso' => 0];
                foreach ($periodo->meses() as $mes) {
                    $delMes = $cuenta->esperadoDelMesCentimos($mes->clave, $cat);
                    $dado = isset($prometioEnMes[$mes->clave][$cat]) ? $cuenta->dadoCentimos($mes->clave, [$cat]) : 0;
                    if ($mes->cobrable) {
                        $fila['prometido'] += $delMes;
                        $prometidoPersona += $delMes;
                    } else {
                        $fila['en_curso'] += $delMes;
                        $fila['dado_en_curso'] += $dado;
                    }
                    $fila['dado'] += $dado;
                    $dadoPersona += $dado;
                    $porMes[$mes->clave]['prometido'] += $delMes;
                    $porMes[$mes->clave]['dado'] += $dado;
                }
                $porCategoria[$cat] = $fila;
            }
            $porPersona[] = ['id' => $cuenta->id, 'nombre' => $cuenta->nombre, 'prometido' => $prometidoPersona, 'dado' => $dadoPersona];
        }

        // Orden de la iglesia para las categorias.
        $orden = array_flip($resultado->categoriasCompromiso);
        uksort($porCategoria, fn ($a, $b) => ($orden[$a] ?? PHP_INT_MAX) <=> ($orden[$b] ?? PHP_INT_MAX) ?: strcmp($a, $b));

        return new ResumenPromesas($periodo, array_values($porCategoria), array_values($porMes), $porPersona);
    }

    /** Personas activas de una lista de ids (reporte por clase), con el formato que espera el motor. */
    public static function personasDe(array $ids): Collection
    {
        return Persona::whereIn('id', $ids)->where('activo', true)->orderBy('nombre')->orderBy('id')->get(['id', 'nombre', 'activo']);
    }
}

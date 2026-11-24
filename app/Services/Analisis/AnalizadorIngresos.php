<?php

namespace App\Services\Analisis;

use App\Models\Tenant;
use App\Services\Analisis\Verificaciones\CompromisosDesincronizados;
use App\Services\Analisis\Verificaciones\CuadreSobresDetalles;
use App\Services\Analisis\Verificaciones\CultosSinCerrar;
use App\Services\Analisis\Verificaciones\DuplicadosSobres;
use App\Services\Analisis\Verificaciones\EstadoCuentaPersonas;
use App\Services\Analisis\Verificaciones\SobresSinPersonaNiCambio;
use App\Services\Analisis\Verificaciones\TotalesCulto;

/**
 * Corre las verificaciones del análisis de ingresos sobre una iglesia y arma el informe en Markdown. Solo lee
 * la base de datos: no corrige nada; cada sección dice qué corregir y cómo.
 */
final class AnalizadorIngresos
{
    /** @return list<Verificacion> */
    public function verificaciones(): array
    {
        return [
            new CuadreSobresDetalles,
            new TotalesCulto,
            new EstadoCuentaPersonas,
            new DuplicadosSobres,
            new SobresSinPersonaNiCambio,
            new CultosSinCerrar,
            new CompromisosDesincronizados,
        ];
    }

    /** @return list<SeccionAnalisis> */
    public function analizar(ContextoAnalisis $contexto): array
    {
        return array_map(fn (Verificacion $v) => $v->ejecutar($contexto), $this->verificaciones());
    }

    /**
     * Informe completo de una o varias iglesias.
     *
     * @param  list<array{0: ContextoAnalisis, 1: list<SeccionAnalisis>}>  $resultados
     */
    public function informe(array $resultados, string $generado): string
    {
        $md = "# Análisis de ingresos\n\nGenerado el {$generado}. Solo lectura: ninguna cifra de la base de datos fue modificada.\n";

        foreach ($resultados as [$ctx, $secciones]) {
            $md .= $this->iglesia($ctx, $secciones);
        }

        return rtrim($md)."\n";
    }

    /** @param  list<SeccionAnalisis>  $secciones */
    private function iglesia(ContextoAnalisis $ctx, array $secciones): string
    {
        $nombre = $ctx->iglesia instanceof Tenant ? $ctx->iglesia->nombre : '';
        $md = "\n## {$nombre}\n\nRango de cultos: {$ctx->rango()}. Estados de cuenta y compromisos del año {$ctx->anioEstadoCuenta}. Fecha de corte: ".Formato::fecha($ctx->hoy).".\n\n";

        $md .= "### Resumen\n\n| Revisión | Revisados | Hallazgos | Estado |\n|---|---:|---:|---|\n";
        foreach ($secciones as $s) {
            $md .= '| '.Formato::celda($s->titulo).' | '.$s->revisados.' | '.$s->hallazgos.' | '.$this->etiqueta($s->estado())." |\n";
        }

        foreach ($secciones as $s) {
            $md .= "\n### {$s->titulo}\n\n{$s->que}\n\n";

            if ($s->totales !== []) {
                foreach ($s->totales as $concepto => $valor) {
                    $md .= "- {$concepto}: **{$valor}**\n";
                }
                $md .= "\n";
            }

            foreach ($s->subsecciones as $sub) {
                $md .= $this->tabla($sub);
            }

            if ($s->hallazgos > 0 && $s->propuesta) {
                $md .= "**Qué corregir.** {$s->propuesta}\n\n";
            }
            foreach ($s->notas as $nota) {
                $md .= "> {$nota}\n";
            }
        }

        return $md;
    }

    private function etiqueta(string $estado): string
    {
        return match ($estado) {
            SeccionAnalisis::OK => 'OK',
            SeccionAnalisis::AVISO => 'Aviso',
            default => 'ALERTA',
        };
    }

    /** @param  array<string, mixed>  $sub */
    private function tabla(array $sub): string
    {
        if ($sub['total_filas'] === 0) {
            return "#### {$sub['titulo']}\n\nSin casos.\n\n";
        }

        $md = "#### {$sub['titulo']} ({$sub['total_filas']})\n\n";
        $md .= '| '.implode(' | ', array_map([Formato::class, 'celda'], $sub['columnas']))." |\n";
        $md .= '|'.str_repeat('---|', count($sub['columnas']))."\n";
        foreach ($sub['filas'] as $fila) {
            $md .= '| '.implode(' | ', array_map([Formato::class, 'celda'], $fila))." |\n";
        }
        $resto = $sub['total_filas'] - count($sub['filas']);
        $md .= $resto > 0 ? "\n_Se muestran {$resto} casos menos de los {$sub['total_filas']}: suba --limite para verlos._\n" : '';
        $md .= $sub['nota'] ? "\n_{$sub['nota']}_\n" : '';

        return $md."\n";
    }
}

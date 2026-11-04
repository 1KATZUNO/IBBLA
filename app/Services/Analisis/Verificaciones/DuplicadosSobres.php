<?php

namespace App\Services\Analisis\Verificaciones;

use App\Services\Analisis\ContextoAnalisis;
use App\Services\Analisis\Formato;
use App\Services\Analisis\SeccionAnalisis;
use App\Services\Analisis\Verificacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Posibles duplicados: sobres de la misma persona en el mismo culto con el mismo total y método (doble clic o
 * doble captura), números de sobre repetidos dentro de un culto y personas cargadas dos veces con el mismo nombre.
 */
final class DuplicadosSobres implements Verificacion
{
    public function ejecutar(ContextoAnalisis $ctx): SeccionAnalisis
    {
        $sobres = $ctx->sobres()->orderBy('c.fecha')->orderBy('s.id')
            ->get(['s.id', 's.numero_sobre', 's.persona_id', 's.metodo_pago', 's.moneda', 's.total_declarado', 's.created_at', 'c.id as culto_id', 'c.fecha']);

        $repetidos = [];
        $totalRepetidos = 0.0;
        $clave = fn ($s) => implode('|', [$s->culto_id, $s->persona_id, $s->moneda, $s->metodo_pago, number_format((float) $s->total_declarado, 2, '.', '')]);
        foreach ($sobres->whereNotNull('persona_id')->groupBy($clave) as $grupo) {
            $primero = $grupo->first();
            if ($grupo->count() < 2 || (float) $primero->total_declarado <= 0) {
                continue;
            }
            $segundos = abs(Carbon::parse($grupo->min('created_at'))->diffInSeconds(Carbon::parse($grupo->max('created_at'))));
            $peso = (float) $primero->total_declarado * ($grupo->count() - 1);
            $totalRepetidos += $peso;
            $repetidos[] = ['peso' => $peso, 'fila' => [
                Formato::fecha($primero->fecha), $ctx->nombrePersona((int) $primero->persona_id), Formato::monto($primero->total_declarado, $primero->moneda), $primero->metodo_pago,
                $grupo->map(fn ($s) => '#'.$s->numero_sobre)->implode(', '), $segundos <= 10 ? 'sí ('.(int) $segundos.' s)' : 'no ('.$this->lapso((int) $segundos).')',
            ]];
        }
        usort($repetidos, fn ($a, $b) => $b['peso'] <=> $a['peso']);

        $numeros = [];
        foreach ($sobres->groupBy(fn ($s) => $s->culto_id.'|'.$s->numero_sobre) as $grupo) {
            if ($grupo->count() > 1) {
                $numeros[] = [Formato::fecha($grupo->first()->fecha), '#'.$grupo->first()->numero_sobre, $grupo->count(), $grupo->map(fn ($s) => $ctx->nombrePersona($s->persona_id ? (int) $s->persona_id : null))->implode(', ')];
            }
        }

        $nombres = [];
        foreach (DB::table('personas')->where('tenant_id', $ctx->iglesia->id)->orderBy('id')->get(['id', 'nombre', 'activo']) as $p) {
            if (trim((string) $p->nombre) !== '') {
                $nombres[Formato::nombreNormalizado($p->nombre)][] = $p;
            }
        }
        $personasRepetidas = [];
        foreach ($nombres as $grupo) {
            if (count($grupo) > 1) {
                $personasRepetidas[] = [$grupo[0]->nombre, count($grupo), implode(', ', array_map(fn ($p) => '#'.$p->id.($p->activo ? '' : ' (inactiva)'), $grupo))];
            }
        }

        return new SeccionAnalisis(
            'duplicados', 'Posibles duplicados',
            'Sobres repetidos (misma persona, culto, total y método), números de sobre repetidos en un culto y personas cargadas dos veces.',
            $sobres->count(), count($repetidos) + count($numeros) + count($personasRepetidas),
            SeccionAnalisis::ALERTA,
            [
                SeccionAnalisis::tabla('Sobres con la misma persona, culto, total y método', ['Culto', 'Persona', 'Total', 'Método', 'Sobres', 'En menos de 10 s'], array_column($repetidos, 'fila'), $ctx->limite, 'Pueden ser un doble clic o dos aportes legítimos iguales: «en menos de 10 s» es la señal de duplicado por error.'),
                SeccionAnalisis::tabla('Números de sobre repetidos dentro de un mismo culto', ['Culto', 'Número', 'Veces', 'Personas'], $numeros, $ctx->limite),
                SeccionAnalisis::tabla('Personas con el mismo nombre', ['Nombre', 'Veces', 'Registros'], $personasRepetidas, $ctx->limite, 'Si son la misma persona sus aportes quedan partidos en dos estados de cuenta.'),
            ],
            [
                'Sobres revisados' => (string) $sobres->count(),
                'Grupos de sobres repetidos' => (string) count($repetidos),
                'Números de sobre repetidos' => (string) count($numeros),
                'Nombres de persona repetidos' => (string) count($personasRepetidas),
                'Monto de los repetidos sin contar el primero (números sin convertir)' => number_format($totalRepetidos, 2),
            ],
            'Revisar cada grupo en Recuento y eliminar el que sobre. Para personas repetidas, unificar las fichas (mover sobres y promesas a una sola) antes de borrar la otra.',
        );
    }

    private function lapso(int $segundos): string
    {
        return match (true) {
            $segundos < 3600 => intdiv($segundos, 60).' min',
            $segundos < 86400 => intdiv($segundos, 3600).' h',
            default => intdiv($segundos, 86400).' días',
        };
    }
}

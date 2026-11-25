<?php

namespace App\Services\Promesas\Importacion;

use App\Models\ClaseAsistencia;
use App\Models\CortePromesas;
use App\Models\Persona;
use App\Models\PromesaProgramada;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aplica una importacion revisada, en una transaccion:
 *  1. crea las categorias nuevas INACTIVAS y las anota en categorias_nuevas del corte;
 *  2. crea las personas nuevas (con su clase si existe una de ese nombre);
 *  3. inserta las promesas programadas SOLO de quienes no estaban en el corte: a quien ya esta no se le
 *     sobrescribe ni se le duplica nada.
 * Nunca escribe en promesas ni en compromisos, ni activa el corte.
 */
class AplicadorImportacion
{
    /**
     * @param  array<string, mixed>  $analisis  resultado de AnalizadorImportacion::analizar (recien calculado)
     * @param  array<string, string>  $decCategorias  col => 'nueva' | 'omitir' | slug de una categoria del corte
     * @param  array<int|string, string>  $decFilas  numero de fila => 'nueva' | 'omitir' | 'persona:ID'
     * @return array{agregadas: int, ya_estaban: int, omitidas: int, personas_creadas: int, promesas: int, categorias_creadas: list<string>}
     *
     * @throws ValidationException si falta una decision o es invalida
     */
    public function aplicar(CortePromesas $corte, array $analisis, array $decCategorias, array $decFilas, string $frecuencia, string $moneda): array
    {
        $slugsValidos = CategoriasCorte::de($corte)->pluck('slug')->all();
        $errores = [];

        // Columnas: destino de cada una (slug existente, 'nueva' o null = no importar)
        $destino = [];
        foreach ($analisis['categorias'] as $col) {
            $dec = $decCategorias[$col['col']] ?? ($col['slug'] ?? 'nueva');
            if ($dec === 'omitir') {
                $destino[$col['col']] = null;
            } elseif ($dec === 'nueva') {
                $destino[$col['col']] = $col['slug'] ?? 'nueva'; // si ya existe, se usa la existente
            } elseif (in_array($dec, $slugsValidos, true)) {
                $destino[$col['col']] = $dec;
            } else {
                $errores["categorias.{$col['col']}"] = "Elige qué hacer con la columna «{$col['titulo']}».";
            }
        }

        // Filas: a quien va cada una
        $plan = [];
        $permitidas = [];
        foreach ($analisis['filas'] as $fila) {
            $n = $fila['fila'];
            $estado = $fila['estado'];
            if (in_array($estado, [AnalizadorImportacion::SIN_MONTOS, AnalizadorImportacion::REPETIDA], true)) {
                $plan[$n] = ['omitir'];

                continue;
            }
            if ($estado === AnalizadorImportacion::FRECUENCIA_INVALIDA) {
                // Solo se puede omitir: para importarla hay que corregir el archivo y volver a subirlo
                if (($decFilas[$n] ?? null) === 'omitir') {
                    $plan[$n] = ['omitir'];
                } else {
                    $errores["filas.{$n}"] = "Fila {$n} ({$fila['nombre']}): la frecuencia «{$fila['frecuencia_invalida']}» no es Semanal, Quincenal ni Mensual. Corrige el archivo y vuelve a subirlo, u omite la fila.";
                }

                continue;
            }
            if (in_array($estado, [AnalizadorImportacion::CALZA, AnalizadorImportacion::EN_CORTE], true)) {
                $plan[$n] = ['persona', (int) $fila['persona_id']];

                continue;
            }
            $porDefecto = match (true) {
                $estado === AnalizadorImportacion::NO_EXISTE => 'nueva',
                ! empty($fila['sugerida']) => 'persona:'.$fila['sugerida'], // ya esta en el corte: se deja como esta
                default => null,
            };
            $dec = $decFilas[$n] ?? $porDefecto;
            if ($dec === 'omitir') {
                $plan[$n] = ['omitir'];
            } elseif ($dec === 'nueva') {
                $plan[$n] = ['nueva'];
            } elseif (is_string($dec) && preg_match('/^persona:(\d+)$/', $dec, $m)) {
                $plan[$n] = ['persona', (int) $m[1]];
                $permitidas[] = (int) $m[1];
            } else {
                $errores["filas.{$n}"] = "Fila {$n} ({$fila['nombre']}): elige a quién corresponde, crea una persona nueva u omítela.";
            }
        }
        if ($permitidas !== []) {
            $existen = Persona::where('tenant_id', $corte->tenant_id)->whereIn('id', $permitidas)->pluck('id')->flip();
            foreach ($plan as $n => $p) {
                if ($p[0] === 'persona' && ! isset($existen[$p[1]]) && in_array($p[1], $permitidas, true)) {
                    $errores["filas.{$n}"] = "Fila {$n}: la persona elegida no existe en esta iglesia.";
                }
            }
        }
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        $porFila = collect($analisis['filas'])->keyBy('fila');

        $titulos = collect($analisis['categorias'])->pluck('titulo', 'col')->all();

        return DB::transaction(function () use ($corte, $destino, $plan, $porFila, $frecuencia, $moneda, $titulos) {
            $creadas = [];
            foreach ($destino as $col => $slug) {
                if ($slug === 'nueva') {
                    $cat = CategoriasCorte::crearInactiva($corte, $titulos[$col] ?? $col);
                    $destino[$col] = $cat->slug;
                    $creadas[] = $cat->nombre;
                }
            }
            if ($creadas !== []) {
                $corte->save();
            }

            $clases = ClaseAsistencia::where('tenant_id', $corte->tenant_id)->get(['id', 'nombre'])
                ->keyBy(fn ($c) => Normalizador::clave($c->nombre));
            $yaEnCorte = PromesaProgramada::where('corte_id', $corte->id)->distinct()->pluck('persona_id')->flip()->all();

            $r = ['agregadas' => 0, 'ya_estaban' => 0, 'omitidas' => 0, 'personas_creadas' => 0, 'promesas' => 0, 'categorias_creadas' => $creadas];
            $ahora = now();
            $insertar = [];
            foreach ($plan as $n => $p) {
                $fila = $porFila[$n];
                if ($p[0] === 'omitir') {
                    $r['omitidas']++;

                    continue;
                }
                if ($p[0] === 'nueva') {
                    $persona = Persona::create(['nombre' => $fila['nombre'], 'activo' => true, 'tenant_id' => $corte->tenant_id]);
                    $clase = $clases[Normalizador::clave($fila['clase'] ?? '')] ?? null;
                    if ($clase && ($fila['clase'] ?? '') !== '') {
                        $persona->clasesAsistencia()->attach($clase->id, ['es_maestro' => false]);
                    }
                    $r['personas_creadas']++;
                    $personaId = $persona->id;
                } else {
                    $personaId = $p[1];
                }

                if (isset($yaEnCorte[$personaId])) {
                    $r['ya_estaban']++;

                    continue;
                }

                $filasPromesa = [];
                foreach ($fila['montos'] as $col => $monto) {
                    $slug = $destino[$col] ?? null;
                    if ($slug === null || $monto <= 0) {
                        continue;
                    }
                    $filasPromesa[] = [
                        'corte_id' => $corte->id,
                        'persona_id' => $personaId,
                        'categoria' => $slug,
                        'monto' => round((float) $monto, 2),
                        'moneda' => $moneda,
                        // La de su fila (columna Frecuencia); si no trae, la elegida al subir
                        'frecuencia' => $fila['frecuencia'] ?? $frecuencia,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
                if ($filasPromesa === []) {
                    $r['omitidas']++; // solo traia montos en columnas que no se importan

                    continue;
                }
                array_push($insertar, ...$filasPromesa);
                $yaEnCorte[$personaId] = true;
                $r['agregadas']++;
                $r['promesas'] += count($filasPromesa);
            }

            foreach (array_chunk($insertar, 500) as $lote) {
                PromesaProgramada::insert($lote);
            }

            return $r;
        });
    }
}

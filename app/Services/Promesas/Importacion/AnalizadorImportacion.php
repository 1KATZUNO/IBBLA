<?php

namespace App\Services\Promesas\Importacion;

use App\Models\CortePromesas;
use App\Models\Persona;
use App\Models\PromesaProgramada;

/**
 * Revision de un Excel ya leido contra la iglesia y el corte, sin guardar nada:
 *  - cada titulo de columna con la categoria de igual nombre (sin tildes ni mayusculas) o "nueva";
 *  - cada fila con su estado: calza, en_corte, parecido, no_existe, sin_montos o repetida.
 */
class AnalizadorImportacion
{
    public const CALZA = 'calza';

    public const EN_CORTE = 'en_corte';

    public const PARECIDO = 'parecido';

    public const NO_EXISTE = 'no_existe';

    public const SIN_MONTOS = 'sin_montos';

    public const REPETIDA = 'repetida';

    /** Parecido minimo (0-100) para ofrecer a alguien como candidata. */
    private const UMBRAL = 80;

    /**
     * @param  array{categorias: list<array{col: string, titulo: string}>, filas: list<array<string, mixed>>}  $lectura
     * @return array{categorias: list<array<string, mixed>>, filas: list<array<string, mixed>>, personas: array<int, array<string, mixed>>, conteo: array<string, int>}
     */
    public function analizar(CortePromesas $corte, array $lectura): array
    {
        // Categorias
        $porClave = [];
        foreach (CategoriasCorte::de($corte) as $cat) {
            $porClave[Normalizador::clave($cat->nombre)] ??= $cat;
            $porClave[Normalizador::clave($cat->slug)] ??= $cat;
        }
        $categorias = [];
        foreach ($lectura['categorias'] as $col) {
            $cat = $porClave[Normalizador::clave($col['titulo'])] ?? null;
            $categorias[] = $col + [
                'slug' => $cat?->slug,
                'nombre_categoria' => $cat?->nombre,
                'inactiva' => $cat !== null && ! $cat->activa,
            ];
        }

        // Personas de la iglesia (activas o no: nunca se duplica a nadie)
        $personas = Persona::query()
            ->where('tenant_id', $corte->tenant_id)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'pin', 'telefono', 'activo']);
        $enCorte = PromesaProgramada::where('corte_id', $corte->id)->distinct()->pluck('persona_id')->flip();

        $indice = [];
        $claves = [];
        $fichas = [];
        foreach ($personas as $p) {
            $clave = Normalizador::clave($p->nombre);
            $indice[$clave][] = $p->id;
            $claves[$p->id] = $clave;
            $fichas[$p->id] = [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'pin' => $p->pin,
                'telefono' => $p->telefono,
                'activo' => (bool) $p->activo,
                'en_corte' => isset($enCorte[$p->id]),
            ];
        }

        $filas = [];
        $vistos = [];
        $conteo = array_fill_keys([self::CALZA, self::EN_CORTE, self::PARECIDO, self::NO_EXISTE, self::SIN_MONTOS, self::REPETIDA], 0);
        foreach ($lectura['filas'] as $fila) {
            $clave = Normalizador::clave($fila['nombre']);
            $fila['persona_id'] = null;
            $fila['candidatas'] = [];

            if (isset($vistos[$clave])) {
                $fila['estado'] = self::REPETIDA;
                $fila['primera'] = $vistos[$clave];
            } elseif ($fila['montos'] === []) {
                $fila['estado'] = self::SIN_MONTOS;
            } else {
                $exactas = $indice[$clave] ?? [];
                if (count($exactas) === 1) {
                    $fila['persona_id'] = $exactas[0];
                    $fila['estado'] = $fichas[$exactas[0]]['en_corte'] ? self::EN_CORTE : self::CALZA;
                } elseif (count($exactas) > 1) {
                    // Dos personas con el mismo nombre: hay que elegir
                    $fila['estado'] = self::PARECIDO;
                    $fila['candidatas'] = $exactas;
                } else {
                    $fila['candidatas'] = $this->parecidas($clave, $claves);
                    $fila['estado'] = $fila['candidatas'] === [] ? self::NO_EXISTE : self::PARECIDO;
                }
            }
            $vistos[$clave] ??= $fila['fila'];
            $conteo[$fila['estado']]++;
            $filas[] = $fila;
        }

        // Solo las fichas que la pantalla necesita
        $usadas = [];
        foreach ($filas as $f) {
            foreach (array_merge($f['candidatas'], array_filter([$f['persona_id']])) as $id) {
                $usadas[$id] = $fichas[$id];
            }
        }

        return ['categorias' => $categorias, 'filas' => $filas, 'personas' => $usadas, 'conteo' => $conteo];
    }

    /**
     * Ids de las personas con nombre parecido, de la mas a la menos parecida (hasta 5).
     *
     * @param  array<int, string>  $claves
     * @return list<int>
     */
    private function parecidas(string $clave, array $claves): array
    {
        if ($clave === '') {
            return [];
        }
        $palabras = explode(' ', $clave);
        $puntos = [];
        foreach ($claves as $id => $otra) {
            similar_text($clave, $otra, $porcentaje);
            $otras = explode(' ', $otra);
            // "Ana Mora" y "Ana Mora Solís": todas las palabras del nombre corto estan en el largo
            $corto = count($palabras) <= count($otras) ? $palabras : $otras;
            $largo = count($palabras) <= count($otras) ? $otras : $palabras;
            if (count($corto) >= 2 && array_diff($corto, $largo) === []) {
                $porcentaje = max($porcentaje, 90);
            }
            if ($porcentaje >= self::UMBRAL) {
                $puntos[$id] = $porcentaje;
            }
        }
        arsort($puntos);

        return array_slice(array_keys($puntos), 0, 5);
    }
}

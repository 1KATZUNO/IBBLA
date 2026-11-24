<?php

namespace App\Services\Reportes;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Vista previa de un reporte en pantalla (HU-R4): las MISMAS filas que van al PDF y al Excel,
 * ordenadas por la columna elegida y paginadas de 100 en 100. El orden y la pagina viajan en la URL
 * (?orden=dado&dir=desc&pagina=2) para poder compartir el enlace y volver atras. Nunca recalcula: solo
 * ordena y corta lo que ya calculo el motor.
 */
final class VistaPrevia
{
    public const POR_PAGINA = 100;

    public readonly string $orden;

    public readonly string $dir;

    /** @var LengthAwarePaginator<int, mixed> */
    public readonly LengthAwarePaginator $pagina;

    /**
     * @param  list<mixed>  $filas  en el orden por defecto
     * @param  array<string, callable(mixed): (int|float|string|null)>  $claves  columna => valor para ordenar
     */
    public function __construct(array $filas, array $claves, Request $request, string $ordenPorDefecto, string $dirPorDefecto = 'asc')
    {
        $orden = (string) $request->query('orden', $ordenPorDefecto);
        $this->orden = isset($claves[$orden]) ? $orden : $ordenPorDefecto;
        $dir = (string) $request->query('dir', $this->orden === $ordenPorDefecto ? $dirPorDefecto : 'asc');
        $this->dir = $dir === 'desc' ? 'desc' : 'asc';

        // Orden estable: a igual valor manda el orden por defecto (el del PDF).
        $clave = $claves[$this->orden];
        $indexadas = [];
        foreach (array_values($filas) as $i => $fila) {
            $indexadas[] = [$clave($fila), $i, $fila];
        }
        $signo = $this->dir === 'desc' ? -1 : 1;
        usort($indexadas, function ($a, $b) use ($signo) {
            $va = $a[0];
            $vb = $b[0];
            if ($va === null && $vb !== null) {
                return 1;
            }
            if ($vb === null && $va !== null) {
                return -1;
            }
            $comparacion = is_string($va) && is_string($vb) ? strcmp($va, $vb) : ($va <=> $vb);

            return $comparacion !== 0 ? $signo * $comparacion : $a[1] <=> $b[1];
        });
        $ordenadas = array_column($indexadas, 2);

        $total = count($ordenadas);
        $ultima = max(1, (int) ceil($total / self::POR_PAGINA));
        $numero = min($ultima, max(1, (int) $request->query('pagina', 1)));

        $this->pagina = new LengthAwarePaginator(
            array_slice($ordenadas, ($numero - 1) * self::POR_PAGINA, self::POR_PAGINA),
            $total,
            self::POR_PAGINA,
            $numero,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'pagina'],
        );
    }

    /** @return list<mixed> filas de la pagina actual */
    public function filas(): array
    {
        return array_values($this->pagina->items());
    }

    /** Numero de la primera fila de la pagina (para la columna #). */
    public function desde(): int
    {
        return ($this->pagina->currentPage() - 1) * self::POR_PAGINA + 1;
    }

    public function paginada(): bool
    {
        return $this->pagina->total() > self::POR_PAGINA;
    }

    /**
     * Enlace para ordenar por una columna: si ya se ordena por ella, invierte la direccion. Vuelve a
     * la pagina 1.
     *
     * @param  array<string, mixed>  $query  parametros actuales de la URL
     */
    public function enlaceOrden(string $columna, array $query, string $ruta): string
    {
        $dir = $this->orden === $columna && $this->dir === 'asc' ? 'desc' : 'asc';
        unset($query['pagina']);

        return route($ruta, array_merge($query, ['orden' => $columna, 'dir' => $dir]));
    }

    /** aria-sort de la cabecera: ascending, descending o none. */
    public function ariaSort(string $columna): string
    {
        if ($this->orden !== $columna) {
            return 'none';
        }

        return $this->dir === 'asc' ? 'ascending' : 'descending';
    }

    public function flecha(string $columna): string
    {
        if ($this->orden !== $columna) {
            return '↕';
        }

        return $this->dir === 'asc' ? '▲' : '▼';
    }

    public static function claveNombre(string $nombre): string
    {
        return mb_strtolower(\Illuminate\Support\Str::ascii($nombre));
    }
}
